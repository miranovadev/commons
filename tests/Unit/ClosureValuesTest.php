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

    public function test_each_component_retains_either_direction_at_zero_and_flips_only_across_it(): void
    {
        foreach ([Direction::Positive, Direction::Negative] as $previous) {
            $same = $previous === Direction::Positive ? 0.4 : -0.4;
            foreach ([[0, $same], [$same, 0], [0, 0]] as [$depth, $cadence]) {
                $result = (new ClosureValues($depth, $cadence))->resolve($previous, $previous);
                $this->assertTrue($result['admissible']);
                $this->assertSame($previous->value, $result['depth_direction']);
                $this->assertSame($previous->value, $result['cadence_direction']);
            }
            $this->assertFalse((new ClosureValues(-$same, 0))->resolve($previous, $previous)['admissible']);
            $this->assertFalse((new ClosureValues(0, -$same))->resolve($previous, $previous)['admissible']);
        }
    }

    public function test_invalid_component_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ClosureValues(NAN, 0))->resolve();
    }
}
