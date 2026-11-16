<?php

namespace Tests\Unit;

use App\Enums\ContextDomain;
use App\Enums\HorizonCloudMode;
use App\Enums\ObservableDirection;
use App\Services\HorizonCloudDirection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HorizonCloudDirectionTest extends TestCase
{
    public function test_settlement_journeys_when_aggregate_turns_positive_and_settles_when_negative(): void
    {
        $resolver = new HorizonCloudDirection;
        $this->assertSame(HorizonCloudMode::Dwell, $resolver->resolve(ContextDomain::Settlement, -.3, ObservableDirection::Negative));
        $this->assertSame(HorizonCloudMode::Journey, $resolver->resolve(ContextDomain::Settlement, .2, ObservableDirection::Negative));
        $this->assertSame(HorizonCloudMode::Dwell, $resolver->resolve(ContextDomain::Settlement, -.1, ObservableDirection::Positive));
    }

    public function test_emergence_has_complementary_signs(): void
    {
        $resolver = new HorizonCloudDirection;
        $this->assertSame(HorizonCloudMode::Dwell, $resolver->resolve(ContextDomain::Emergence, .3, ObservableDirection::Positive));
        $this->assertSame(HorizonCloudMode::Journey, $resolver->resolve(ContextDomain::Emergence, -.2, ObservableDirection::Positive));
        $this->assertSame(HorizonCloudMode::Dwell, $resolver->resolve(ContextDomain::Emergence, .1, ObservableDirection::Negative));
    }

    public function test_zero_retains_the_horizons_own_historical_direction(): void
    {
        $resolver = new HorizonCloudDirection;
        $this->assertSame(HorizonCloudMode::Journey, $resolver->resolve(ContextDomain::Settlement, 0, ObservableDirection::Positive));
        $this->assertSame(HorizonCloudMode::Dwell, $resolver->resolve(ContextDomain::Settlement, 0, ObservableDirection::Negative));
        $this->assertSame(HorizonCloudMode::Journey, $resolver->resolve(ContextDomain::Emergence, 0, ObservableDirection::Negative));
    }

    public function test_invalid_measurement_cannot_select_question(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new HorizonCloudDirection)->resolve(ContextDomain::Settlement, INF, ObservableDirection::Positive);
    }
}
