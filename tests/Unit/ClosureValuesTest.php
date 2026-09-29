<?php

namespace Tests\Unit;

use App\Domain\Observables\ClosureValues;
use App\Enums\ObservableDirection as Direction;
use PHPUnit\Framework\TestCase;

class ClosureValuesTest extends TestCase
{
    public function test_alignment_and_zero_retention_are_independent_of_the_average(): void
    {
        $this->assertTrue((new ClosureValues(-1, -1))->resolve()['admissible']);
        $this->assertTrue((new ClosureValues(1, 0.2))->resolve()['admissible']);
        $this->assertFalse((new ClosureValues(-0.2, 1))->resolve()['admissible']);
        $this->assertSame(0.4, (new ClosureValues(-0.2, 1))->average());
        $this->assertFalse((new ClosureValues(0, 0))->resolve(Direction::Negative, Direction::Positive)['admissible']);
        $this->assertTrue((new ClosureValues(0, 1))->resolve(Direction::Positive, Direction::Negative)['admissible']);
        $this->assertSame('negative', (new ClosureValues(0, 0))->resolve()['depth_direction']);
    }

    public function test_frozen_historical_closure_is_preserved_even_when_it_differs_from_the_pure_mean(): void
    {
        $this->assertSame(-0.7, (new ClosureValues(-0.8, -0.6))->average());
        $this->assertSame(0.25, (new ClosureValues(-0.8, -0.6, 0.25))->average());
    }

    public function test_invalid_component_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ClosureValues(NAN, 0))->resolve();
    }
}
