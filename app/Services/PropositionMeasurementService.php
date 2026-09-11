<?php

namespace App\Services;

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
        private readonly MeasurementDirectionService $directions,
        private readonly \App\Services\Observables\ChiralityCalculator $chirality,
    ) {}

    public function recalculate(
        Proposition $proposition,
        ?DateTimeInterface $calculatedAt = null,
    ): PropositionMeasurement {
        $values = $this->observables->calculate($proposition);

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
    ): ?PropositionMeasurement {
        if (! ContextHorizonMembership::query()->whereKey($proposition->getKey())->exists()) {
            return null;
        }

        return $this->recalculate($proposition, $calculatedAt);
    }

    /**
     * Populate missing or obsolete disposable measurement projections.
     * Current-version projections are left untouched, and propositions outside a
     * Context Horizon remain deliberately unmeasured.
     *
     * @param  Collection<int, Proposition>  $propositions
     */
    public function repairMissing(Collection $propositions): void
    {
        foreach ($propositions as $proposition) {
            $proposition->loadMissing('measurement');

            if ($proposition->measurement === null
                || $proposition->measurement->calculator_version !== LineageMeasurementService::VERSION) {
                $this->recalculateIfMeasurable($proposition);
            }
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

        return \Illuminate\Support\Facades\DB::transaction(function () use ($proposition, $correspondence, $chirality, $closure, $calculatedAt): PropositionMeasurement {
            // Use the same lock order as contribution dispatch and ledger recording.
            \App\Models\Substrate::query()->whereKey($proposition->phase->cycle->participant->substrate_id)->lockForUpdate()->firstOrFail();
            $this->directions->record($proposition, [
                'correspondence' => $correspondence,
                'chirality' => $chirality,
                'closure' => $closure,
                'lean' => $this->chirality->leanFor(collect([$proposition->id]), $proposition->phase->cycle->participant->substrate),
            ]);
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
