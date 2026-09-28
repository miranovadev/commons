<?php

namespace App\Services;

use App\Enums\ObservableDirection;
use App\Domain\Observables\ChiralityValues;
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
        $components = $this->observables->components($proposition, $context);
        $values = $components->observables();

        return $this->project(
            $proposition,
            $values->correspondence,
            $values->chirality,
            $values->closure,
            $calculatedAt,
            $components->chiralityComponents,
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
                    $components = $snapshot === null ? $this->observables->components($proposition, $context) : null;
                    $values = $snapshot ?? $components->observables();
                    $attributes = $snapshot !== null ? $snapshot->only([
                        'orientation', 'attestation_cadence', 'orientation_direction', 'attestation_cadence_direction',
                    ]) : ($components->chiralityComponents === null ? [] : $this->componentAttributes($proposition, $components->chiralityComponents, $context));
                    $measurement = new PropositionMeasurement;
                    $measurement->forceFill([
                        ...$attributes,
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
        ?ChiralityValues $chiralityComponents = null,
    ): PropositionMeasurement {
        $this->assertMeasurement('correspondence', $correspondence);
        $this->assertMeasurement('chirality', $chirality);
        $this->assertMeasurement('closure', $closure);

        return DB::transaction(function () use ($proposition, $correspondence, $chirality, $closure, $calculatedAt, $chiralityComponents): PropositionMeasurement {
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
            $componentAttributes = $snapshot !== null ? $snapshot->only([
                'orientation', 'attestation_cadence', 'orientation_direction', 'attestation_cadence_direction',
            ]) : ($chiralityComponents === null ? [] : $this->componentAttributes($proposition, $chiralityComponents));
            PropositionMeasurement::query()->upsert(
                [[
                    ...$componentAttributes,
                    'proposition_id' => $proposition->getKey(),
                    'correspondence' => $correspondence,
                    'chirality' => $chirality,
                    'closure' => $closure,
                    'calculated_at' => $calculatedAt ?? now(),
                    'calculator_version' => LineageMeasurementService::VERSION,
                ]],
                ['proposition_id'],
                [
                    ...array_keys($componentAttributes),
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

    private function componentAttributes(Proposition $proposition, ChiralityValues $values, ?ParticipationContext $context = null): array
    {
        $prior = $context === null ? PropositionMeasurement::find($proposition->id) : $context->propositions->get($proposition->id)?->measurement;
        $entry = $proposition->phase->cycle->entry_committal_id;
        $previous = $entry === null ? null : ($context === null ? CommitmentSnapshot::find($entry) : $context->snapshots->firstWhere('proposition_id', $entry));

        return ['orientation' => $values->orientation, 'attestation_cadence' => $values->cadence,
            ...$values->resolveDirections(
                ObservableDirection::from($prior?->orientation_direction ?? $previous?->orientation_direction ?? 'positive'),
                ObservableDirection::from($prior?->attestation_cadence_direction ?? $previous?->attestation_cadence_direction ?? 'positive'),
            )];
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

        return ObservableDirection::defaultFor('correspondence');
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
