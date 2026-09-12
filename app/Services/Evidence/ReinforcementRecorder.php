<?php

namespace App\Services\Evidence;

use App\Models\Receipt;
use App\Models\Reinforcement;
use App\Services\PropositionStateService;

final readonly class ReinforcementRecorder
{
    public function __construct(private PropositionStateService $state) {}

    /** Receipt ledger lock serializes allocation within a substrate. Never erase an event. */
    public function record(Receipt $receipt): string
    {
        $execution = $receipt->instrumentExecution;
        if ($receipt->signature === null || $receipt->type !== 'hyperlink' || $execution->status !== 'completed') {
            return 'non-reinforcing';
        }
        $proposition = $execution->proposition;
        if ($execution->phase_id === $proposition->phase_id) {
            return 'originating-evidence';
        }
        $participantId = $execution->phase->cycle->participant_id;
        if (Reinforcement::whereIn('proposition_id', $this->state->sourceIds($proposition))
            ->where('participant_id', $participantId)->exists()) {
            return 'participant-already-counted';
        }
        $reinforcement = new Reinforcement;
        $reinforcement->forceFill([
            'proposition_id' => $proposition->id, 'participant_id' => $participantId,
            'receipt_id' => $receipt->id,
        ])->save();

        return 'counted';
    }
}
