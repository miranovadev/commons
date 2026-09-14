<?php

namespace App\Services;

use App\Enums\LedgerEntryKind;
use App\Enums\PhaseKind;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Substrate;
use App\Models\SubstrateLedgerEntry;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ParticipationService
{
    public function __construct(
        private ContributionService $contributions,
        private ContributionDispatcher $dispatcher,
        private PhaseProgressionService $progression,
        private CycleEntryService $cycleEntry,
        private PotentialChargeService $potential,
        private InputPlanService $plans,
        private ParticipantContextSerializer $contextSerializer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function stateFor(Participant $participant): array
    {
        return $this->state($participant, false);
    }

    /** Human-only display state, fetched separately from autonomous participation. */
    public function workspaceFor(Participant $participant): array
    {
        return $this->state($participant, true);
    }

    private function state(Participant $participant, bool $workspace): array
    {
        $state = $this->currentStateFor($participant, $workspace);
        $snapshot = CommitmentSnapshot::query()
            ->where('commitment_snapshots.substrate_id', $participant->substrate_id)
            ->whereHas('proposition.phase.cycle', fn ($query) => $query->where('participant_id', $participant->getKey()))
            ->join('substrate_ledger_entries as entry', 'entry.id', '=', 'commitment_snapshots.ledger_entry_id')
            ->orderByDesc('entry.sequence')->select('commitment_snapshots.*')->first();
        $horizonId = $state['cycle']['context_horizon_id'] ?? null;
        $horizon = $horizonId === null
            ? $participant->cycles()->latest('sequence')->first()?->contextHorizon
            : ContextHorizon::findOrFail($horizonId);

        return [
            ...$state,
            'current_proposition_id' => $participant->fresh()->current_proposition_id,
            'domain' => $horizon?->domain->value,
            'horizon_sequence' => $horizon?->sequence,
            'context_horizon_id' => $horizon?->id,
            'last_commitment' => $snapshot === null ? null : [
                ...$snapshot->only(['proposition_id', 'depth', 'cadence', 'closure', 'tension_before']),
                'evaluated_at' => $snapshot->evaluated_at->toISOString(),
                'depth_direction' => $snapshot->depth_direction?->value,
                'cadence_direction' => $snapshot->cadence_direction?->value,
                'domain_before' => $snapshot->direction_before->value,
                'domain_after' => $snapshot->direction_after->value,
                'tension_after' => $snapshot->tension,
                'closure_boundary' => $snapshot->isClosureBoundary(),
                'closure_history_proposition_id' => $snapshot->closure_history_proposition_id,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function currentStateFor(Participant $participant, bool $workspace): array
    {
        $cycle = Cycle::query()
            ->whereBelongsTo($participant)
            ->whereNull('completed_at')
            ->latest('sequence')
            ->first();

        if ($cycle === null) {
            $completed = $participant->cycles()->whereNotNull('completed_at')->latest('sequence')->first();
            $finalPhase = $completed?->phases()->whereNotNull('completed_at')->latest('id')->first();

            return [...$this->waiting(
                'cycle_not_started',
                'The Participant is waiting for a Cycle to begin.',
                entry: $this->entryFor($participant),
            ), 'completion' => $completed !== null && $completed->rested_at === null && $finalPhase?->kind === PhaseKind::Order ? [
                'cycle_id' => $completed->id,
                'phase_id' => $finalPhase->id,
                'completed_at' => $completed->completed_at->toISOString(),
                'proposition_id' => $participant->fresh()->current_proposition_id,
            ] : null];
        }

        $phase = Phase::query()
            ->whereBelongsTo($cycle)
            ->whereNull('completed_at')
            ->latest('kind')
            ->first();

        if ($phase === null) {
            return $this->waiting(
                'cycle_has_no_active_phase',
                'The current Cycle has no active Phase.',
                $cycle,
            );
        }

        if ($phase->gate_proposition_id === null) {
            return $this->waiting(
                'potential_selection_pending',
                'The Phase is waiting for Potential to select its Gate.',
                $cycle,
            );
        }

        $plan = $workspace ? $this->plans->forWorkspace($participant) : $this->contributions->planFor($participant);
        $blocked = $phase->kind === PhaseKind::Order
            && ! isset($plan['schema']['$defs']['actions']['proposition.commit'])
            && ! isset($plan['schema']['$defs']['actions']['proposition.traverse'])
            && ! $plan['tragedy'];

        return [
            'status' => 'ready',
            'cycle' => $this->cycle($cycle),
            'plan' => $workspace ? $plan : $this->contextSerializer->plan($plan),
            'perspective' => $this->perspective($participant),
            'reason' => $blocked ? [
                'code' => in_array('source_already_committed', $plan['commitment']['reasons'], true)
                    ? 'source_already_committed' : 'commitment_preconditions_unsatisfied',
                'message' => in_array('source_already_committed', $plan['commitment']['reasons'], true)
                    ? 'This source has already been committed. Continue through its Committal result.'
                    : 'Commitment requires grounding and aligned Depth/Cadence directions. Use an action available in the current Phase.',
            ] : ($plan['tragedy'] ? [
                'code' => 'tragic_region',
                'message' => 'No admissible non-Rest action remains in the current Phase. Rest is the only available action.',
            ] : null),
            'entry' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $origin
     * @return array<string, mixed>
     */
    public function enter(Participant $participant, ?array $origin = null): array
    {
        $this->cycleEntry->enter($participant, $origin);

        return $this->stateFor($participant);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function contribute(
        Participant $participant,
        string $phaseId,
        array $input,
    ): array {
        return DB::transaction(function () use (
            $participant,
            $phaseId,
            $input,
        ): array {
            // Acquire the shared root before any participant/phase or contribution lock.
            // Cycle entry, ledger writes, and horizon projection use the same order.
            Substrate::query()->lockForUpdate()->findOrFail($participant->substrate_id);
            $lockedParticipant = Participant::query()
                ->lockForUpdate()
                ->findOrFail($participant->getKey());
            $cycle = Cycle::query()
                ->whereBelongsTo($lockedParticipant)
                ->whereNull('completed_at')
                ->latest('sequence')
                ->lockForUpdate()
                ->first();

            if ($cycle === null) {
                throw new ConflictHttpException(
                    'The Participant has no active Cycle.'
                );
            }

            $phase = Phase::query()
                ->whereBelongsTo($cycle)
                ->whereNull('completed_at')
                ->latest('kind')
                ->lockForUpdate()
                ->first();

            if ($phase === null || $phase->getKey() !== $phaseId) {
                throw new ConflictHttpException(
                    'The participation state changed. Refresh before contributing.'
                );
            }

            $this->contributions->validate($lockedParticipant, $input);
            if ($input['action'] === 'participant.rest') {
                return $this->rest($lockedParticipant, $phase);
            }
            $gate = $this->dispatcher->dispatch(
                $lockedParticipant,
                $phase,
                $input,
            );
            if ($input['action'] !== 'receipt.read') {
                $this->progression->advance($phase, $gate);
            }

            return $this->stateFor($lockedParticipant);
        });
    }

    /** The validated plan exposes Rest only in a Tragic Region. */
    private function rest(Participant $participant, Phase $phase): array
    {
        $participant->current_proposition_id = $phase->gate_proposition_id;
        $participant->save();
        $phase->completed_at = now();
        $phase->save();
        $phase->cycle->rested_at = now();
        $phase->cycle->completed_at = now();
        $phase->cycle->save();

        // Keep the exhausted position until the next Cycle is explicitly entered.
        return $this->stateFor($participant);
    }

    /**
     * @return array<string, mixed>
     */
    private function waiting(
        string $code,
        string $message,
        ?Cycle $cycle = null,
        ?array $entry = null,
    ): array {
        return [
            'status' => 'waiting',
            'cycle' => $cycle === null ? null : $this->cycle($cycle),
            'plan' => null,
            'perspective' => null,
            'reason' => compact('code', 'message'),
            'entry' => $entry,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entryFor(Participant $participant): array
    {
        $participant = $participant->fresh();
        if ($participant->current_proposition_id !== null) {
            $continuation = $this->cycleEntry->continuationFor($participant);
            if ($continuation !== null) {
                return [
                    'mode' => 'continuity',
                    'available' => true,
                    'proposition_id' => $continuation->id,
                    'message' => 'Continue the next Cycle from the local Committal Proposition.',
                ];
            }
            $selection = $this->potential->recoverFrom($participant->position()->firstOrFail());

            return [
                'mode' => 'potential',
                'selection' => 'recovery',
                'available' => $selection !== null,
                'proposition_id' => ($selection['proposition'] ?? null)?->getKey(),
                'distance' => $selection['distance'] ?? null,
                'potential_charge' => $selection['potential_charge'] ?? 0.0,
                'message' => $selection === null
                    ? 'Resting until a structurally reachable Committal region has positive Potential Charge.'
                    : 'Begin the next Cycle at the nearest viable Committal distance.',
            ];
        }

        $hasOrigin = SubstrateLedgerEntry::query()
            ->where('substrate_id', $participant->substrate_id)
            ->where('kind', LedgerEntryKind::Proposition->value)
            ->exists();

        if ($hasOrigin) {
            $available = CommitmentSnapshot::query()
                ->where('substrate_id', $participant->substrate_id)
                ->whereHas('proposition', fn ($query) => $query->where('tier', 'committal'))
                ->exists();

            return [
                'mode' => 'potential',
                'available' => $available,
                'message' => $available
                    ? 'Potential will select the Committal Gate with the greatest available reduction in Tension.'
                    : 'Potential is waiting for a measured Committal Proposition.',
            ];
        }

        return [
            'mode' => 'origin',
            'available' => true,
            'message' => 'Establish the first Committal Proposition to enter the substrate.',
            'requirements' => ['statement', 'attestation', 'receipt'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cycle(Cycle $cycle): array
    {
        return [
            'id' => $cycle->getKey(),
            'sequence' => $cycle->sequence,
            'context_horizon_id' => $cycle->context_horizon_id,
            'created_at' => $cycle->created_at,
        ];
    }

    /**
     * Read limits are participant-specific. Attenuation metadata describes the
     * substrate's measurement geometry; this boundary applies no extra discount.
     *
     * @return array<string, mixed>
     */
    private function perspective(Participant $participant): array
    {
        return [
            'max_read_count' => $participant->max_read_count,
            'max_token_count' => $participant->max_token_count,
            'attenuation' => [
                'status' => 'active',
                'value' => 1.0,
                'distance' => 0,
                'formula' => '1 / (1 + structural_distance)^2',
                'scope' => 'substrate',
                'applied_at' => 'measurement-lineage',
            ],
        ];
    }
}
