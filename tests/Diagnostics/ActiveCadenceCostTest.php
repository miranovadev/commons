<?php

namespace Tests\Diagnostics;

use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Feature\AttestationBeatContinuityTest;

/** Opt-in identical event-cost probe for main and corrected code; Closure is isolated by its parent. */
class ActiveCadenceCostTest extends AttestationBeatContinuityTest
{
    public function test_profile_identical_cadence_events(): void
    {
        $invoke = fn (string $method, ...$args) => (new ReflectionMethod(AttestationBeatContinuityTest::class, $method))->invoke($this, ...$args);
        [$origin, $parent] = $invoke('start');
        $invoke('act', $parent);
        $invoke('act', $parent);
        $invoke('act', $parent);
        $samples = [];
        $profile = function (string $event, callable $operation) use (&$samples) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $start = hrtime(true);
            $result = $operation();
            $elapsed = (hrtime(true) - $start) / 1e6;
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $samples[$event][] = ['queries' => count($queries),
                'sql_ms' => array_sum(array_column($queries, 'time')), 'elapsed_ms' => $elapsed];

            return $result;
        };
        for ($repeat = 0; $repeat < 5; $repeat++) {
            $child = $profile('expressive_birth', fn () => $invoke('child', $parent, $origin, false));
            $profile('first_own_attestation', fn () => $invoke('act', $child));
            $profile('second_own_attestation', fn () => $invoke('act', $child, 'refute'));
            $this->assertSame(2, $child->attestations()->count());
        }
        file_put_contents(getenv('COMMONS_CADENCE_COST_FILE') ?: '/tmp/cadence-event-cost.json',
            json_encode($samples, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
