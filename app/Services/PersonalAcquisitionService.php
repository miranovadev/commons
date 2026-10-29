<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\EvidenceExecution;
use App\Models\Phase;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

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
                ->orderBy('id');
            // At most two rows are hydrated, regardless of abandoned attempts or later Cycles.
            $completed = (clone $history)->recordedObservation()->whereHas('receipt')->first(['id', 'outcome']);
            $pending = $completed === null ? $history->where('phase_id', $phase->id)
                ->whereIn('status', ['pending', 'presented'])->first(['id']) : null;

            return ['role' => $role, 'gate_id' => $phase->gate_proposition_id,
                'status' => $completed ? 'completed' : ($pending ? 'pending' : 'required'),
                'execution_id' => $completed?->id ?? $pending?->id,
                'outcome' => $completed?->outcome];
        };

        return $context === null ? $read() : $context->remember('personal-acquisition:'.$phase->id.':'.$phase->gate_proposition_id, $read);
    }

    /** Same durable completion predicate, batched for exploration's selected routes. */
    public function completedInContext(Phase $phase, ParticipationContext $context): Collection
    {
        return $context->remember('personal-acquisition-completions:'.$phase->cycle->participant_id, fn () => EvidenceExecution::query()
            ->whereIn('proposition_id', $context->propositions->keys())
            ->whereHas('cycle', fn ($q) => $q->where('participant_id', $phase->cycle->participant_id))
            ->whereHas('phase', fn ($q) => $q->whereIn('kind', [1, 2, 4]))
            ->where(fn ($q) => $q->where('type', 'instrument')->orWhere(fn ($q) => $q->where('type', 'prepare')
                ->whereHas('phase', fn ($q) => $q->whereIn('kind', [2, 4]))))
            ->recordedObservation()->whereHas('receipt')
            ->join('phases as acquisition_phase', 'acquisition_phase.id', '=', 'evidence_executions.phase_id')
            ->select(['evidence_executions.proposition_id', 'acquisition_phase.kind'])->distinct()->toBase()->get()
            ->keyBy(fn ($execution) => $execution->kind.':'.$execution->proposition_id));
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
