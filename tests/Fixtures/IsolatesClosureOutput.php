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
    private array $componentClosureInputs = [];

    protected function componentClosureInput(Proposition $head, array $input): void
    {
        $this->componentClosureInputs[$head->id] = $input;
    }

    protected function isolateClosureOutput(): void
    {
        $real = app(ProspectiveClosureService::class);
        $origins = [];
        $this->partialMock(ProspectiveClosureService::class, function ($mock) use ($real, &$origins): void {
            $mock->shouldReceive('initialize')->andReturnUsing(function (Proposition $head) use ($real, &$origins): void {
                if ($head->tier === 'committal' && $head->phase->cycle->entry_committal_id === null) {
                    $real->initialize($head);
                    $origins[$head->id] = $real->stored($head);
                }
            });
            $mock->shouldReceive('entered')->andReturnNull();
            $mock->shouldReceive('stored')->andReturnUsing(function (Proposition $head) use (&$origins): array {
                if (isset($origins[$head->id])) {
                    return $origins[$head->id];
                }
                // Explicit neutral numerical input, not reconstructed history.
                // No per-head database query belongs to this numerical double.
                $value = 0.0;
                $direction = $value > 0 ? 'positive' : 'negative';

                return [
                    'depth' => $value, 'cadence' => $value, 'closure' => $value,
                    'historical_extent' => $value, 'historical_rhythm' => $value,
                    'depth_direction' => $direction, 'cadence_direction' => $direction,
                    'historical_extent_direction' => $direction, 'historical_rhythm_direction' => $direction,
                    'cadence_interval' => null, 'history_proposition_id' => null,
                    'admissible' => true, 'fixture' => 'isolated_closure_output',
                    ...($this->componentClosureInputs[$head->id] ?? []),
                ];
            });
            $mock->shouldReceive('preserve')->andThrow(new LogicException(
                'A component Closure double cannot establish canonical Commit preservation.'
            ));
        });
    }
}
