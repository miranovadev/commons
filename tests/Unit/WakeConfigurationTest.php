<?php

namespace Tests\Unit;

use App\Services\WakeOffsetResolver;
use App\Support\WakeConfiguration;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WakeConfigurationTest extends TestCase
{
    public static function configurations(): array
    {
        $cases = [];
        foreach (['phase', 'cycle', 'rest'] as $mode) {
            $cases["termination $mode"] = [['wake' => ['termination' => $mode]], true];
        }
        $cases['invalid termination'] = [['wake' => ['termination' => 'forever']], false];
        foreach ([1, 600, 1800, 0, -1, 1801, 1.5, 'invalid'] as $seconds) {
            $cases["timeout $seconds"] = [['wake' => ['timeout_seconds' => $seconds]], in_array($seconds, [1, 600, 1800], true)];
        }
        foreach (['seconds', 'random', 'invalid'] as $mode) {
            $cases["offset mode $mode"] = [['trigger' => ['offset' => ['mode' => $mode]]], $mode !== 'invalid'];
        }
        foreach (['seconds', 'random_from', 'random_to'] as $key) {
            foreach ([0, 60, -1, 61, 1.5, 'invalid'] as $seconds) {
                $cases["$key $seconds"] = [['trigger' => ['offset' => ['mode' => 'random', $key => $seconds]]], in_array($seconds, [0, 60], true)];
            }
        }
        $cases['equal range'] = [['trigger' => ['offset' => ['mode' => 'random', 'random_from' => 30, 'random_to' => 30]]], true];
        $cases['reversed range'] = [['trigger' => ['offset' => ['mode' => 'random', 'random_from' => 40, 'random_to' => 30]]], false];

        return $cases;
    }

    #[DataProvider('configurations')]
    public function test_validation(array $configuration, bool $valid): void
    {
        $validator = Validator::make(['configuration' => $configuration], WakeConfiguration::rules());
        $validator->after(fn ($validator) => WakeConfiguration::validateRange($validator, $configuration));
        $this->assertSame($valid, $validator->passes(), $validator->errors()->toJson());
    }

    public function test_defaults_and_inclusive_random_bounds(): void
    {
        $configuration = WakeConfiguration::resolve(null);
        $this->assertSame(['termination' => 'phase', 'timeout_seconds' => 600], $configuration['wake']);
        $this->assertSame(['mode' => 'seconds', 'seconds' => 0, 'random_from' => 0, 'random_to' => 60], $configuration['trigger']['offset']);
        $resolver = new WakeOffsetResolver;
        $this->assertSame(0, $resolver->seconds(null));
        $this->assertSame(30, $resolver->seconds(['trigger' => ['offset' => ['mode' => 'random', 'random_from' => 30, 'random_to' => 30]]]));
        for ($i = 0; $i < 50; $i++) {
            $sample = $resolver->seconds(['trigger' => ['offset' => ['mode' => 'random', 'random_from' => 30, 'random_to' => 40]]]);
            $this->assertGreaterThanOrEqual(30, $sample);
            $this->assertLessThanOrEqual(40, $sample);
        }
    }
}
