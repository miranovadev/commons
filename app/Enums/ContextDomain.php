<?php

namespace App\Enums;

use InvalidArgumentException;

enum ContextDomain: string
{
    case Emergence = 'emergence';
    case Settlement = 'settlement';

    public function opposite(): self
    {
        return $this === self::Emergence ? self::Settlement : self::Emergence;
    }

    public function registerFor(PhaseKind $kind): string
    {
        $emergence = $this === self::Emergence;

        return match ($kind) {
            PhaseKind::Distinguish, PhaseKind::Disperse => $emergence ? 'activity' : 'signal',
            PhaseKind::Persist, PhaseKind::Mediate => $emergence ? 'frontier' : 'stance',
            PhaseKind::Constrain, PhaseKind::Bias, PhaseKind::Order => $emergence ? 'canon' : 'concord',
        };
    }

    /** Resolve a vocabulary slot; repeated labels never confer producing Phase identity. */
    public function registerAt(RegisterPosition $position): string
    {
        return match ($position) {
            RegisterPosition::First => $this === self::Emergence ? 'activity' : 'signal',
            RegisterPosition::Second => $this === self::Emergence ? 'frontier' : 'stance',
            RegisterPosition::Third => $this === self::Emergence ? 'canon' : 'concord',
        };
    }

    public static function after(
        float $tension,
        self $hystereticDirection,
    ): self {
        if (! is_finite($tension) || $tension < -1 || $tension > 1) {
            throw new InvalidArgumentException(
                'Tension must be between -1 and 1.'
            );
        }

        return match (true) {
            $tension > 0 => self::Emergence,
            $tension < 0 => self::Settlement,
            default => $hystereticDirection,
        };
    }
}
