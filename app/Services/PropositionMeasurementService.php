<?php

namespace App\Services;

use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use App\Services\Observables\ObservableService;
use DateTimeInterface;
use InvalidArgumentException;

final class PropositionMeasurementService
{
    public function __construct(
        private readonly ObservableService $observables,
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

        PropositionMeasurement::query()->upsert(
            [[
                'proposition_id' => $proposition->getKey(),
                'correspondence' => $correspondence,
                'chirality' => $chirality,
                'closure' => $closure,
                'calculated_at' => $calculatedAt ?? now(),
            ]],
            ['proposition_id'],
            [
                'correspondence',
                'chirality',
                'closure',
                'calculated_at',
            ],
        );

        $measurement = PropositionMeasurement::query()
            ->findOrFail($proposition->getKey());

        $proposition->setRelation('measurement', $measurement);

        return $measurement;
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
