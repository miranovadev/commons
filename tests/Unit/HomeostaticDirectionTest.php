<?php

namespace Tests\Unit;

use App\Enums\ObservableDirection;
use App\Services\HomeostaticDirectionService;
use InvalidArgumentException;
use Tests\TestCase;

class HomeostaticDirectionTest extends TestCase
{
    public function test_magnitude_aggregates_instead_of_counting_positive_and_negative_votes(): void
    {
        $result = app(HomeostaticDirectionService::class)->aggregate(['a' => .1, 'b' => .1, 'c' => -.9]);
        $this->assertSame(ObservableDirection::Negative, $result->direction);
        $this->assertSame(-.2333333, $result->mean);
    }

    public function test_zero_retains_explicit_history_and_never_invents_an_initial_direction(): void
    {
        $service = app(HomeostaticDirectionService::class);
        foreach ([['a' => .7, 'b' => -.7], ['a' => 0.0, 'b' => 0.0]] as $values) {
            $this->assertNull($service->aggregate($values)->direction);
            foreach (ObservableDirection::cases() as $previous) {
                $result = $service->aggregate($values, $previous);
                $this->assertSame(0.0, $result->mean);
                $this->assertSame($previous, $result->direction);
            }
        }
        $this->assertSame(ObservableDirection::Positive,
            $service->aggregate(['a' => .1], ObservableDirection::Negative)->direction);
    }

    public function test_missing_values_are_not_zero_mass_and_empty_populations_have_no_direction(): void
    {
        $service = app(HomeostaticDirectionService::class);
        $this->assertSame(.5, $service->aggregate(['a' => 1.0, 'b' => 0.0, 'c' => null])->mean);
        foreach ([[], ['a' => null]] as $values) {
            $result = $service->aggregate($values, ObservableDirection::Positive);
            $this->assertNull($result->mean);
            $this->assertNull($result->direction);
        }
    }

    public function test_out_of_range_measurements_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(HomeostaticDirectionService::class)->aggregate(['a' => 1.1]);
    }

    public function test_unknown_measurement_names_are_rejected_before_loading_a_population(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(HomeostaticDirectionService::class)->forPosition(new \App\Models\Proposition, 'potential');
    }
}
