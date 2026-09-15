<?php

namespace App\Console\Commands;

use App\Models\Participant;
use App\Services\PotentialChargeService;
use Illuminate\Console\Command;

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
        $this->line(json_encode([
            'participant_id' => $participant->id,
            'position_id' => $participant->current_proposition_id,
            ...$inspection,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
