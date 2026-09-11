<?php

namespace App\Services;

use App\Services\Participation\ParticipationContext;

use App\Models\ContextHorizonMembership;
use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use App\Services\Observables\ObservableService;
use App\Services\Observables\LineageMeasurementService;
use DateTimeInterface;
use Illuminate\Support\Collection;
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
     * Populate missing or obsolete disposable measurement projections.
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

    public function project(
        Proposition $proposition,
        ?float $correspondence,
        ?float $chirality,
        ?float $closure,
        ?DateTimeInterface $calculatedAt = null,
        ?ParticipationContext $context = null,
    ): PropositionMeasurement {
        $this->assertMeasurement('correspondence', $correspondence);
        $this->assertMeasurement('chirality', $chirality);
        $this->assertMeasurement('closure', $closure);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($proposition, $correspondence, $chirality, $closure, $calculatedAt): PropositionMeasurement {
            // Lock the Proposition so the numeric projection and retained direction move together.
            Proposition::query()->whereKey($proposition->id)->lockForUpdate()->firstOrFail();
            if ($correspondence !== null && $correspondence !== 0.0) {
                \Illuminate\Support\Facades\DB::table('propositions')->where('id', $proposition->id)->update([
                    'correspondence_direction' => \App\Enums\ObservableDirection::after($correspondence)->value,
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
    public function correspondenceDirectionFor(Proposition $position, ?ParticipationContext $context = null): \App\Enums\ObservableDirection
    {
        $seen = [];
        $substrateId = $position->phase->cycle->participant->substrate_id;
        while ($position !== null) {
            if (isset($seen[$position->id]) || $position->phase->cycle->participant->substrate_id !== $substrateId) {
                throw new \DomainException('Correspondence lineage must be acyclic and within one substrate.');
            }
            $seen[$position->id] = true;
            $position = $context?->proposition($position) ?? $position;
            if ($context === null) $position->load('measurement');
            $this->repairMissing(collect([$position]), $context);
            $value = $position->measurement?->correspondence;
            if ($value !== null && $value !== 0.0) {
                return \App\Enums\ObservableDirection::after($value);
            }
            $retained = $context !== null ? $position->correspondence_direction : Proposition::query()->whereKey($position->id)->value('correspondence_direction');
            if ($retained !== null) {
                return \App\Enums\ObservableDirection::from($retained);
            }
            $position = $context !== null
                ? $context->nodes($context->parents->get($position->id, collect())->sortBy([['created_at', 'asc'], ['id', 'asc']])->pluck('source_proposition_id'))->first()
                : $position->parents()->orderBy('supersessions.created_at')->orderBy('supersessions.id')->first();
        }
        return \App\Enums\ObservableDirection::Positive;
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
