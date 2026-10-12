<?php

namespace Tests\Fixtures;

use App\Models\Proposition;
use App\Services\Observables\ProspectiveClosureService;

/**
 * Component-only input boundary. Authored graph/snapshot rows are read-model
 * inputs, not evidence that the contribution lifecycle produced those rows.
 * Never use this trait to test Closure, Commit success, or historical maturity.
 */
trait IsolatesClosureOutput
{
    protected function isolateClosureOutput(): void
    {
        $real = app(ProspectiveClosureService::class);
        $this->partialMock(ProspectiveClosureService::class, function ($mock) use ($real): void {
            $mock->shouldReceive('initialize')->andReturnUsing(function (Proposition $head) use ($real): void {
                if ($head->tier === 'committal' && $head->phase->cycle->entry_committal_id === null) {
                    $real->initialize($head);
                }
            });
            $mock->shouldReceive('entered')->andReturnNull();
            $mock->shouldReceive('stored')->andReturnUsing(function (Proposition $head): array {
                // Explicit neutral numerical input, not reconstructed history.
                // A read-model snapshot can explicitly author a different scalar.
                $value = (float) ($head->commitmentSnapshot?->closure ?? 0);
                $direction = $value > 0 ? 'positive' : 'negative';

                return [
                    'depth' => $value, 'cadence' => $value, 'closure' => $value,
                    'historical_extent' => $value, 'historical_rhythm' => $value,
                    'depth_direction' => $direction, 'cadence_direction' => $direction,
                    'historical_extent_direction' => $direction, 'historical_rhythm_direction' => $direction,
                    'cadence_interval' => $head->commitmentSnapshot?->cadence_interval,
                    'admissible' => true, 'fixture' => 'isolated_closure_output',
                ];
            });
            $mock->shouldReceive('preserve')->andThrow(new \LogicException(
                'A component Closure double cannot establish canonical Commit preservation.'
            ));
        });
    }
}
