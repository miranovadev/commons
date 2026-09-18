<?php

namespace App\Console\Commands;

use App\Enums\PhaseKind;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\PhasePositionService;
use Illuminate\Console\Command;

/** Diagnostics only: no ledger, projection, phase or proposition writes. */
final class ValidatePhasePositions extends Command
{
    protected $signature = 'commons:validate-phase-positions {--substrate= : Limit to one substrate ID}';

    protected $description = 'Report historical register/phase mismatches and operational edges at invalid producing positions';

    public function handle(PhasePositionService $positions): int
    {
        $violations = 0;
        $report = function (string $kind, string $id, array $details) use (&$violations): void {
            $violations++;
            $this->line(json_encode(['violation' => $kind, 'id' => $id, ...$details], JSON_THROW_ON_ERROR));
        };
        Proposition::query()->when($this->option('substrate'), fn ($query, $id) => $query
            ->whereHas('phase.cycle.participant', fn ($query) => $query->where('substrate_id', $id)))
            ->with('phase.cycle.contextHorizon')->chunkById(200, function ($nodes) use ($report): void {
                foreach ($nodes as $node) {
                    // Origin is an explicit initialization boundary, not an expressive output.
                    if ($node->tier === 'committal' && $node->phase->kind === PhaseKind::Distinguish) {
                        continue;
                    }
                    $expected = $node->phase->cycle->contextHorizon->domain->registerFor($node->phase->kind);
                    if ($node->register !== $expected || $node->phase->write_register !== $expected) {
                        $report('register_phase_mismatch', $node->id, ['producing_phase' => $node->phase->kind->value,
                            'register' => $node->register, 'expected_register' => $expected]);
                    }
                }
            });
        Supersession::query()->when($this->option('substrate'), fn ($query, $id) => $query
            ->whereHas('phase.cycle.participant', fn ($query) => $query->where('substrate_id', $id)))
            ->with(['phase.cycle.contextHorizon', 'source.phase.cycle', 'target.phase.cycle'])
            ->chunkById(200, function ($edges) use ($positions, $report): void {
                foreach ($edges as $edge) {
                    $phase = $edge->phase;
                    $source = $edge->source;
                    $target = $edge->target;
                    $action = 'proposition.'.$edge->type;
                    $valid = $source !== null && $target !== null
                        && in_array($action, config('phases.'.$phase->kind->value.'.actions', []), true)
                        && $positions->matches($target, $phase, $phase->kind, $edge->type === 'commit' ? 'committal' : 'expressive')
                        && ($edge->type === 'revise'
                            ? $positions->matches($source, $phase, $phase->kind)
                            : $positions->acceptsGate($phase, $source));
                    if (! $valid) {
                        $report('invalid_operational_edge', $edge->id, ['operation' => $edge->type,
                            'source_id' => $edge->source_proposition_id, 'source_phase' => $source?->phase->kind->value,
                            'target_id' => $edge->target_proposition_id, 'operation_phase' => $phase->kind->value]);
                    }
                }
            });
        $this->info($violations.' phase-position violation(s). Historical data was not changed.');

        return $violations === 0 ? self::SUCCESS : self::FAILURE;
    }
}
