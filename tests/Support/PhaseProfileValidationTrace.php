<?php
namespace Tests\Support;
use Illuminate\Support\Facades\DB;
final class PhaseProfileValidationTrace
{
    public static function start(): float
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        return microtime(true);
    }
    public static function finish(float $start, string $test, array $state, array $input, ?array $next = null): void
    {
        $elapsed = microtime(true) - $start;
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();
        $profile = $queries->filter(fn ($q) => str_contains($q['query'], 'phase_profile_observations'));
        $gate = $state['plan']['gate_id'] ?? null;
        $measurement = $gate === null ? null : DB::table('proposition_measurements')->where('proposition_id', $gate)->first();
        $fields = ['correspondence', 'shape_topology', 'phase_profile', 'cardinal_position', 'shape_topology_direction', 'phase_profile_direction', 'cardinal_position_direction'];
        $values = $measurement === null ? [] : array_intersect_key((array) $measurement, array_flip($fields));
        fwrite(STDERR, 'PP_TRACE '.json_encode(['test' => $test, 'cycle' => $state['cycle']['sequence'] ?? null,
            'phase' => $state['plan']['phase']['number'] ?? $state['plan']['phase']['kind'] ?? null,
            'action' => $input['action'], 'seconds' => round($elapsed, 4), 'queries' => $queries->count(),
            'sql_ms' => round($queries->sum('time'), 2), 'profile_queries' => $profile->count(),
            'profile_sql_ms' => round($profile->sum('time'), 2), 'gate_components' => $values,
            'coherence' => $state['plan']['correspondence_admissibility'] ?? null,
            'next_coherence' => $next['plan']['correspondence_admissibility'] ?? null,
            'actions' => array_keys($state['plan']['schema']['$defs']['actions'] ?? []),
            'next_actions' => array_keys($next['plan']['schema']['$defs']['actions'] ?? [])]).PHP_EOL);
    }
}
