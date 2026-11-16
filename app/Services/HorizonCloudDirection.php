<?php

namespace App\Services;

use App\Enums\ContextDomain;
use App\Enums\HorizonCloudMode;
use App\Enums\ObservableDirection;
use InvalidArgumentException;

/**
 * Resolve a Horizon's inquiry mode from its own aggregate field observation.
 *
 * The caller must supply the Horizon's historically retained preceding direction.
 * A lineage-local direction, a parent Domain, or a recalculated final snapshot
 * cannot substitute for that history. This class does not measure lineage heads.
 */
final class HorizonCloudDirection
{
    public function resolve(
        ContextDomain $domain,
        float $horizonTension,
        ObservableDirection $previous,
    ): HorizonCloudMode {
        if (! is_finite($horizonTension) || $horizonTension < -1 || $horizonTension > 1) {
            throw new InvalidArgumentException('Horizon Tension must be between -1 and 1.');
        }

        $direction = ObservableDirection::after($horizonTension, $previous);
        $origin = $domain === ContextDomain::Emergence
            ? ObservableDirection::Positive : ObservableDirection::Negative;

        return $direction === $origin ? HorizonCloudMode::Dwell : HorizonCloudMode::Journey;
    }
}
