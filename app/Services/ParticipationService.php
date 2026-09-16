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
use InvalidArgumentException;
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
        try {
            $state = $this->currentStateFor($participant, $workspace);
        } catch (\App\Exceptions\PotentialProjectionUnavailable) {
            $state = $this->waiting('potential_projection_pending',
                'The field is updating. Your position is preserved. Please retry shortly.');
        }
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

            $recovering = $completed?->rested_at !== null;
            $entry = $this->entryFor($participant);
            $recoveryReady = $recovering && ($entry['available'] ?? false);
            $retirementPending = $recovering && ! $recoveryReady && DB::table('origin_branch_memberships as membership')
                ->join('origin_branches as branch', 'branch.root_proposition_id', '=', 'membership.root_proposition_id')
                ->where('membership.proposition_id', $participant->current_proposition_id)->whereNull('branch.pruned_at')->exists();

            return [...$this->waiting(
                $recovering ? ($recoveryReady || $retirementPending ? 'recovery_pending' : 'recovery_unavailable') : 'cycle_not_started',
                $recovering ? ($recoveryReady ? 'Rest completed; recovery is ready for the next entry attempt.'
                    : ($retirementPending ? 'Rest completed; branch exhaustion will be reconsidered from fresh state.'
                        : 'Rest completed; no valid recovery basin entry can be established.')) : 'The Participant is waiting for a Cycle to begin.',
                entry: $entry,
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

        $version = app(PotentialProjectionService::class)->assertCurrent($participant->substrate_id);
        $plan = $this->plans->forPhase($participant, $phase, $workspace);
        if ($version !== app(PotentialProjectionService::class)->assertCurrent($participant->substrate_id)) {
            throw new \App\Exceptions\PotentialProjectionUnavailable($participant->substrate_id);
        }
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

    public function prepare(Participant $participant): array
    {
        $this->cycleEntry->prepare($participant);

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
        ?array $expectedSchema = null,
    ): array {
        // Invalid model output is still an error; only a formerly valid action
        // rejected by fresh admissibility is a retryable participation conflict.
        if ($expectedSchema !== null) {
            $this->contributions->validateSchema($expectedSchema, $input);
        }

        $observedPhase = Phase::with('cycle')->find($phaseId);
        if ($observedPhase === null || $observedPhase->cycle->participant_id !== $participant->id
            || $observedPhase->completed_at !== null || $observedPhase->cycle->completed_at !== null) {
            throw new ConflictHttpException('The participation state changed. Refresh before contributing.');
        }

        // Validate before network I/O, then guard the prepared plan by version.
        // An observation is discarded when the Phase or admissibility changed.
        $observed = null;
        if (in_array($input['action'] ?? null, ['instrument.execute', 'receipt.replay'], true)) {
            $plan = $this->plans->forPhase($participant, $observedPhase);
            try {
                $this->contributions->validateSchema($plan['schema'], $input);
            } catch (InvalidArgumentException $exception) {
                if ($expectedSchema !== null) {
                    throw new ConflictHttpException('The participation options changed. Rebuild state before contributing.', $exception);
                }
                throw $exception;
            }
            $expectedSchema ??= $plan['schema'];
            $observed = $this->dispatcher->observeInstrument($participant, $observedPhase, $input);
        }

        // Build admissibility optimistically before acquiring the shared root.
        // The version check below makes this exact schema authoritative for the
        // guarded write without hydrating a graph while other writers wait.
        $validatedVersion = app(PotentialProjectionService::class)->assertCurrent($participant->substrate_id);
        $validationPlan = $this->plans->forValidation($participant);
        try {
            $this->contributions->validateSchema($validationPlan['schema'], $input);
        } catch (InvalidArgumentException $exception) {
            if ($expectedSchema !== null) {
                throw new ConflictHttpException('The participation options changed. Rebuild state before contributing.', $exception);
            }
            throw $exception;
        }

        $tragedy = null;
        if (($input['action'] ?? null) === 'participant.rest') {
            $restPhase = Phase::with('cycle.participant')->find($phaseId);
            if ($restPhase !== null && $restPhase->cycle->participant_id === $participant->id) {
                $tragedy = app(TragedyService::class)->inspect($restPhase);
            }
        }

        DB::transaction(function () use (
            $participant,
            $phaseId,
            $input,
            $expectedSchema,
            $observed,
            $tragedy,
            $validatedVersion,
        ): void {
            // Acquire the shared root before any participant/phase or contribution lock.
            // Cycle entry, ledger writes, and horizon projection use the same order.
            $substrate = Substrate::query()->lockForUpdate()->findOrFail($participant->substrate_id);
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

            if ((int) $substrate->field_version !== $validatedVersion) {
                throw new ConflictHttpException('The active field changed during contribution validation. Retry from fresh state.');
            }
            if ($input['action'] === 'participant.rest') {
                // Rest changes lifecycle only; it adds no observation/measurement.
                // A stale proof is discarded and can be retried after this Rest.
                $validProof = $tragedy !== null && $tragedy['stable']
                    && $tragedy['field_version'] === (int) $substrate->field_version;
                $this->rest($lockedParticipant, $phase);
                if ($validProof) {
                    app(TragedyService::class)->retire($phase, $tragedy);
                }

                return;
            }
            $gate = $this->dispatcher->dispatch(
                $lockedParticipant,
                $phase,
                $input,
                $observed,
            );
            if ($input['action'] !== 'receipt.read') {
                $this->progression->advance($phase, $gate);
            }

        });

        app(PotentialProjectionService::class)->refresh($participant->substrate_id);
        $this->cycleEntry->prepare($participant->fresh());

        return $this->stateFor($participant->fresh());
    }

    /** The validated plan exposes Rest only in a Tragic Region. */
    private function rest(Participant $participant, Phase $phase): void
    {
        $participant->current_proposition_id = $phase->gate_proposition_id;
        $participant->save();
        $phase->completed_at = now();
        $phase->save();
        $phase->cycle->rested_at = now();
        $phase->cycle->completed_at = now();
        $phase->cycle->save();
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
            $selection = $this->potential->recoverFrom(
                $participant->position()->firstOrFail(),
                $participant->cycles()->latest('sequence')->first()?->contextHorizon,
            );

            return [
                'mode' => 'potential',
                'selection' => $selection['reason'] ?? 'recovery',
                'available' => $selection !== null,
                'proposition_id' => ($selection['proposition'] ?? null)?->getKey(),
                'distance' => $selection['distance'] ?? null,
                'potential_charge' => $selection['potential_charge'] ?? 0.0,
                'message' => $selection === null
                    ? 'Resting until a reachable recovery basin has positive Potential Charge.'
                    : (($selection['reason'] ?? null) === 'origin_renewal'
                        ? 'The exhausted branch is historical. Begin a fresh Distinction at Origin.'
                        : 'Begin the next Cycle at the Committal with greatest attenuated Potential Charge in the viable recovery basin.'),
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
