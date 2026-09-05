<?php

namespace App\Services;

use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use DateTimeInterface;
use InvalidArgumentException;

final class PropositionMeasurementService
{
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
