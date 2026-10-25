<?php

namespace App\Console\Commands;

use App\Models\Participant;
use App\Services\ExhaustionService;
use App\Services\ParticipantExplorationService;
use App\Services\ParticipantHistoricalContext;
use App\Services\PotentialChargeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectRecovery extends Command
{
    protected $signature = 'commons:recovery-inspect {participant : Participant ID}';

    protected $description = 'Inspect a hypothetical Rest recovery without changing the Participant or substrate.';

    public function handle(PotentialChargeService $potential, ParticipantHistoricalContext $history): int
    {
        $participant = Participant::find($this->argument('participant'));
        if ($participant?->current_proposition_id === null) {
            $this->error('A positioned Participant is required.');

            return self::FAILURE;
        }
        $inspection = $potential->inspectRecoveryFrom(
            $participant->position()->firstOrFail(),
            $participant->cycles()->latest('sequence')->first()?->contextHorizon,
            $participant,
        );
        $selection = $inspection['selection'];
        $inspection['selection'] = $selection === null ? null : [
            'proposition_id' => $selection['proposition']->id,
            ...array_diff_key($selection, ['proposition' => true]),
        ];
        $phase = $participant->cycles()->latest('sequence')->first()?->phases()->orderByDesc('kind')->first();
        $exhaustion = $phase === null ? null : app(ExhaustionService::class)->inspect($phase);
        $this->line(json_encode([
            'participant_id' => $participant->id,
            'position_id' => $participant->current_proposition_id,
            'current_path_explored' => $phase !== null && app(ParticipantExplorationService::class)->explored($participant->position, $phase),
            'current_horizon_id' => $phase?->cycle->context_horizon_id,
            'opening_region_exhausted' => $phase !== null && app(ExhaustionService::class)->boundaryExhausted($phase->cycle->contextHorizon),
            'parent_horizon_id' => $phase?->cycle->contextHorizon->parent_context_horizon_id,
            'exhausted_alternatives' => $phase === null || app(ExhaustionService::class)->unavailable($participant->position)
                ? $history->position($participant->position) : $history->forPhase($phase),
            'origin_lineages' => DB::table('origin_branch_memberships')->where('proposition_id', $participant->current_proposition_id)->pluck('root_proposition_id')->all(),
            'would_tragedy' => false, // Rest and inspection never publish terminal encounters.
            ...$inspection,
            'horizon_failure' => $exhaustion,

        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
