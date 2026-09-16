<?php

namespace App\Services;

use App\Enums\ObservableDirection;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizonMembership;
use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use App\Models\Substrate;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\ObservableService;
use App\Services\Participation\ParticipationContext;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PropositionMeasurementService
{
    public function __construct(
        private readonly ObservableService $observables,
    ) {}

    public function recalculate(
        Proposition $proposition,
        ?DateTimeInterface $calculatedAt = null,
        ?ParticipationContext $context = null,
    ): PropositionMeasurement {
        $snapshot = $proposition->tier === 'committal'
            ? ($context !== null ? $context->snapshots->firstWhere('proposition_id', $proposition->id) : CommitmentSnapshot::find($proposition->id)) : null;
        if ($snapshot !== null) {
            return $this->project($proposition, $snapshot->correspondence, $snapshot->chirality, $snapshot->closure, $calculatedAt);
        }
        $values = $this->observables->calculate($proposition, $context);

        return $this->project(
            $proposition,
            $values->correspondence,
            $values->chirality,
            $values->closure,
            $calculatedAt,
        );
    }

    public function recalculateIfMeasurable(
        Proposition $proposition,
        ?DateTimeInterface $calculatedAt = null,
        ?ParticipationContext $context = null,
    ): ?PropositionMeasurement {
        if ($context !== null ? ! $context->memberships->has($proposition->id) : ! ContextHorizonMembership::query()->whereKey($proposition->getKey())->exists()) {
            return null;
        }

        return $this->recalculate($proposition, $calculatedAt, $context);
    }

    /**
     * Mutation/maintenance only: populate missing or obsolete projections.
     * Callers must hold the Substrate lock before loading calculation state.
     * Never call this from ordinary graph/detail serialization.
     * Current-version projections are left untouched, and propositions outside a
     * Context Horizon remain deliberately unmeasured.
     *
     * @param  Collection<int, Proposition>  $propositions
     */
    public function repairMissing(Collection $propositions, ?ParticipationContext $context = null): void
    {
        foreach ($propositions as $original) {
            $proposition = $context?->proposition($original) ?? $original;
            $proposition->loadMissing('measurement');

            if ($proposition->measurement === null
                || $proposition->measurement->calculator_version !== LineageMeasurementService::VERSION) {
                $this->recalculateIfMeasurable($proposition, context: $context);
            }
            $original->setRelation('measurement', $proposition->measurement);
        }
    }

    /** Derive missing/stale values for this observation without writing projections or directions. */
    public function observeMissing(Collection $propositions, ?ParticipationContext $context = null): void
    {
        foreach ($propositions as $original) {
            $proposition = $context?->proposition($original) ?? $original;
            $proposition->loadMissing('measurement');
            if ($proposition->measurement === null
                || $proposition->measurement->calculator_version !== LineageMeasurementService::VERSION) {
                $measurable = $context !== null ? $context->memberships->has($proposition->id)
                    : ContextHorizonMembership::whereKey($proposition->id)->exists();
                if ($measurable) {
                    $snapshot = $proposition->tier === 'committal'
                        ? ($context !== null ? $context->snapshots->firstWhere('proposition_id', $proposition->id) : CommitmentSnapshot::find($proposition->id)) : null;
                    $values = $snapshot ?? $this->observables->calculate($proposition, $context);
                    $measurement = new PropositionMeasurement;
                    $measurement->forceFill([
                        'proposition_id' => $proposition->id,
                        'correspondence' => $values->correspondence,
                        'chirality' => $values->chirality,
                        'closure' => $values->closure,
                        'calculated_at' => now(),
                        'calculator_version' => LineageMeasurementService::VERSION,
                    ]);
                    $proposition->setRelation('measurement', $measurement);
                }
            }
            $original->setRelation('measurement', $proposition->measurement);
        }
    }

    public function project(
        Proposition $proposition,
        ?float $correspondence,
        ?float $chirality,
        ?float $closure,
        ?DateTimeInterface $calculatedAt = null,
    ): PropositionMeasurement {
        $this->assertMeasurement('correspondence', $correspondence);
        $this->assertMeasurement('chirality', $chirality);
        $this->assertMeasurement('closure', $closure);

        return DB::transaction(function () use ($proposition, $correspondence, $chirality, $closure, $calculatedAt): PropositionMeasurement {
            // Maintenance and contribution writes use the same root-before-node order.
            Substrate::query()->lockForUpdate()->findOrFail($proposition->phase->cycle->participant->substrate_id);
            // Lock the Proposition so the numeric projection and retained direction move together.
            Proposition::query()->whereKey($proposition->id)->lockForUpdate()->firstOrFail();
            app(FieldMutationService::class)->touch($proposition->phase, [$proposition->id], measurementsDirty: false);
            $snapshot = $proposition->tier === 'committal' ? CommitmentSnapshot::find($proposition->id) : null;
            if ($snapshot !== null) {
                [$correspondence, $chirality, $closure] = [$snapshot->correspondence, $snapshot->chirality, $snapshot->closure];
            }

            if ($correspondence !== null && $correspondence !== 0.0) {
                DB::table('propositions')->where('id', $proposition->id)->update([
                    'correspondence_direction' => ObservableDirection::after($correspondence)->value,
                ]);
            }
            PropositionMeasurement::query()->upsert(
                [[
                    'proposition_id' => $proposition->getKey(),
                    'correspondence' => $correspondence,
                    'chirality' => $chirality,
                    'closure' => $closure,
                    'calculated_at' => $calculatedAt ?? now(),
                    'calculator_version' => LineageMeasurementService::VERSION,
                ]],
                ['proposition_id'],
                [
                    'correspondence',
                    'chirality',
                    'closure',
                    'calculated_at',
                    'calculator_version',
                ],
            );

            $measurement = PropositionMeasurement::query()
                ->findOrFail($proposition->getKey());

            $proposition->setRelation('measurement', $measurement);

            return $measurement;
        });
    }

    /** Correspondence has its own hysteresis; Attestation polarity is unrelated. */
    public function correspondenceDirectionFor(Proposition $position, ?ParticipationContext $context = null): ObservableDirection
    {
        $seen = [];
        $substrateId = $position->phase->cycle->participant->substrate_id;
        while ($position !== null) {
            if (isset($seen[$position->id]) || $position->phase->cycle->participant->substrate_id !== $substrateId) {
                throw new \DomainException('Correspondence lineage must be acyclic and within one substrate.');
            }
            $seen[$position->id] = true;
            $position = $context?->proposition($position) ?? $position;
            if ($context === null) {
                $position->load('measurement');
            }
            $value = $position->measurement?->correspondence;
            if ($value !== null && $value !== 0.0) {
                return ObservableDirection::after($value);
            }
            $retained = $context !== null ? $position->correspondence_direction : Proposition::query()->whereKey($position->id)->value('correspondence_direction');
            if ($retained !== null) {
                return ObservableDirection::from($retained);
            }
            $position = $context !== null
                ? $context->nodes($context->parents->get($position->id, collect())->sortBy([['created_at', 'asc'], ['id', 'asc']])->pluck('source_proposition_id'))->first()
                : $position->parents()->orderBy('supersessions.created_at')->orderBy('supersessions.id')->first();
        }

        return ObservableDirection::Positive;
    }

    private function assertMeasurement(string $name, ?float $value): void
    {
        if ($value === null) {
            return;
        }

        if (! is_finite($value) || $value < -1 || $value > 1) {
            throw new InvalidArgumentException(
                "{$name} must be between -1 and 1, or null."
            );
        }
    }
}
