<?php

namespace App\Services;

use App\Enums\MeasurementAxis;
use App\Enums\ObservableDirection;
use App\Models\Proposition;
use Illuminate\Support\Facades\DB;

/** Durable directional memory, separate from replaceable numeric projections. */
final class MeasurementDirectionService
{
    /** Record established directions only: zero/null cannot erase history. */
    public function record(Proposition $proposition, array $values): void
    {
        $directions = [];
        foreach ($values as $name => $value) {
            $axis = MeasurementAxis::from($name);
            if ($value !== null && (float) $value !== 0.0) {
                $directions[$axis->value] = ObservableDirection::after((float) $value)->value;
            }
        }
        if ($directions !== []) {
            DB::table('proposition_directions')->upsert(
                [['proposition_id' => $proposition->id, ...$directions]],
                ['proposition_id'], array_keys($directions),
            );
        }
    }

    /**
     * Current sign, then own established direction, then primary-parent lineage.
     * Resolution is read-only; inherited defaults are not an established local flip.
     *
     * @param callable(Proposition): ?float $sample
     */
    public function forProposition(Proposition $position, MeasurementAxis $axis, callable $sample): ObservableDirection
    {
        $substrateId = $position->phase->cycle->participant->substrate_id;
        $seen = [];
        while ($position !== null) {
            if (isset($seen[$position->id])
                || $position->phase->cycle->participant->substrate_id !== $substrateId) {
                throw new \DomainException('Directional lineage must be acyclic and within one substrate.');
            }
            $seen[$position->id] = true;
            $value = $sample($position);
            if ($value !== null && $value !== 0.0) {
                return ObservableDirection::after($value);
            }
            $retained = DB::table('proposition_directions')->where('proposition_id', $position->id)->value($axis->value);
            if ($retained !== null) {
                return ObservableDirection::from($retained);
            }
            $position = $position->parents()->orderBy('supersessions.created_at')->orderBy('supersessions.id')->first();
        }

        return $axis->initialDirection();
    }
}
