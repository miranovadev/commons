<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\EvidenceExecution;
use App\Models\Phase;
use App\Services\Participation\ParticipationContext;

/** Durable participation at an exact Gate; never Receipt consumption or exploration authority. */
final class PersonalAcquisitionService
{
    public function role(Phase $phase): ?string
    {
        return match ($phase->kind) {
            PhaseKind::Distinguish => 'distinguish_discovery',
            PhaseKind::Persist => 'persist_examination',
            PhaseKind::Disperse => 'disperse_acquisition',
            default => null,
        };
    }

    public function state(Phase $phase, ?ParticipationContext $context = null): array
    {
        $role = $this->role($phase);
        if ($role === null || $phase->gate_proposition_id === null) {
            return ['role' => $role, 'status' => 'not_required'];
        }
        $read = function () use ($phase, $role): array {
            $history = EvidenceExecution::where('proposition_id', $phase->gate_proposition_id)
                ->whereHas('cycle', fn ($q) => $q->where('participant_id', $phase->cycle->participant_id))
                ->whereHas('phase', fn ($q) => $q->where('kind', $phase->kind->value))
                ->whereIn('type', $phase->kind === PhaseKind::Distinguish ? ['instrument'] : ['instrument', 'prepare'])
                ->withExists('receipt')->orderBy('id')->get(['id', 'type', 'status', 'outcome', 'phase_id']);
            $completed = $history->first(fn ($e) => $e->receipt_exists && $e->observationRecorded());
            $pending = $history->first(fn ($e) => $e->phase_id === $phase->id && in_array($e->status, ['pending', 'presented'], true));

            return ['role' => $role, 'gate_id' => $phase->gate_proposition_id,
                'status' => $completed ? 'completed' : ($pending ? 'pending' : 'required'),
                'execution_id' => $completed?->id ?? $pending?->id,
                'outcome' => $completed?->outcome];
        };

        return $context === null ? $read() : $context->remember('personal-acquisition:'.$phase->id.':'.$phase->gate_proposition_id, $read);
    }

    public function allows(Phase $phase, string $action, ?ParticipationContext $context = null): bool
    {
        if ($this->state($phase, $context)['status'] !== 'required') {
            return false;
        }
        return match ($action) {
            'instrument.execute' => $phase->kind !== PhaseKind::Persist
                || ! app(PreparationService::class)->hasReadableArtifacts($phase, $context),
            'preparation.start' => in_array($phase->kind, [PhaseKind::Persist, PhaseKind::Disperse], true)
                && app(PreparationService::class)->hasReadableArtifacts($phase, $context),
            default => false,
        };
    }
}
