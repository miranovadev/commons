<?php

namespace App\Console\Commands;

use App\Models\Participant;
use App\Services\ExhaustionService;
use App\Services\PotentialChargeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectRecovery extends Command
{
    protected $signature = 'commons:recovery-inspect {participant : Participant ID}';

    protected $description = 'Inspect a hypothetical Rest recovery without changing the Participant or substrate.';

    public function handle(PotentialChargeService $potential): int
    {
        $participant = Participant::find($this->argument('participant'));
        if ($participant?->current_proposition_id === null) {
            $this->error('A positioned Participant is required.');

            return self::FAILURE;
        }
        $inspection = $potential->inspectRecoveryFrom(
            $participant->position()->firstOrFail(),
            $participant->cycles()->latest('sequence')->first()?->contextHorizon,
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
            'current_path_exhausted' => DB::table('exhaustion_states')->where('proposition_id', $participant->current_proposition_id)->where('kind', 'excursion')->exists(),
            'current_horizon_id' => $phase?->cycle->context_horizon_id,
            'current_horizon_exhausted' => $phase?->cycle->contextHorizon->exhausted_at !== null,
            'parent_horizon_id' => $phase?->cycle->contextHorizon->parent_context_horizon_id,
            'exhausted_alternatives' => app(ExhaustionService::class)->context($participant->position),
            'origin_lineages' => DB::table('origin_branch_memberships')->where('proposition_id', $participant->current_proposition_id)->pluck('root_proposition_id')->all(),
            'would_tragedy' => collect($exhaustion['branches'] ?? [])->contains('exhausted', true),
            ...$inspection,
            'branch_exhaustion' => $exhaustion,
            'origin_renewal_after_tragedy' => $selection === null && ($exhaustion['stable'] ?? false)
                && collect($exhaustion['branches'] ?? [])->contains('exhausted', true),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
