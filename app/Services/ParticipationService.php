<?php

namespace App\Services;

use App\Enums\LedgerEntryKind;
use App\Enums\PhaseKind;
use App\Enums\RestReason;
use App\Exceptions\PotentialProjectionUnavailable;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\EvidenceExecution;
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
        } catch (PotentialProjectionUnavailable) {
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
            'participant' => $participant->only(['id', 'callsign', 'designation']),
            'current_proposition_id' => $participant->fresh()->current_proposition_id,
            'domain' => $horizon?->domain->value,
            'horizon_sequence' => $horizon?->sequence,
            'context_horizon_id' => $horizon?->id,
            'last_commitment' => $snapshot === null ? null : [
                ...$snapshot->only(['proposition_id', 'depth', 'cadence', 'closure', 'tension_before']),
                'evaluated_at' => $snapshot->evaluated_at->toISOString(),
                ...($snapshot->closure_observation ?? []),
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

            $recovering = ($completed?->rested_at !== null || $completed?->deferred_at !== null);
            $entry = $this->entryFor($participant);
            $recoveryReady = $recovering && ($entry['available'] ?? false);

            return [...$this->waiting(
                $recovering ? ($recoveryReady ? 'recovery_pending' : 'recovery_unavailable') : 'cycle_not_started',
                $recovering ? ($recoveryReady ? 'The Cycle completed; recovery is ready for the next entry attempt.'
                    : 'The Cycle completed; no valid recovery basin entry can be established.') : 'The Participant is waiting for a Cycle to begin.',
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

        app(AcquisitionAttemptService::class)->recover($phase);
        app(PreparationService::class)->recover($participant, $phase);
        $version = app(PotentialProjectionService::class)->assertCurrent($participant->substrate_id);
        $plan = $this->plans->forPhase($participant, $phase, $workspace);
        if ($version !== app(PotentialProjectionService::class)->assertCurrent($participant->substrate_id)) {
            throw new PotentialProjectionUnavailable($participant->substrate_id);
        }
        $blocked = $phase->kind === PhaseKind::Order
            && ! isset($plan['schema']['$defs']['actions']['proposition.commit'])
            && ! isset($plan['schema']['$defs']['actions']['proposition.traverse'])
            && ! $plan['rest_available'];

        if ($workspace) {
            $plan['receipt_cognition'] = $this->contextSerializer->receiptCognition([
                'participant' => $participant->only(['id']),
                'plan' => $this->contextSerializer->plan($plan),
            ]);
        }

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
                    : 'Commitment requires grounding and aligned Historical Extent/Rhythm directions. Use an action available in the current Phase.',
            ] : ($plan['rest_available'] ? [
                'code' => $plan['rest_reason'],
                'message' => $plan['rest_reason'] === 'no_remaining_potential'
                    ? 'No admissible action remains in this Phase, and its Commit Region has zero measured Potential. Rest does not create Tragedy.'
                    : 'No admissible non-Rest action remains in this Phase. Rest does not prove Horizon failure.',
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
        ?string $expectedClosureObservation = null,
    ): array {
        // Invalid model output is still an error; only a formerly valid action
        // rejected by fresh admissibility is a retryable participation conflict.
        if ($expectedSchema !== null) {
            $this->contributions->validateSchema($expectedSchema, $input);
        }

        $observedPhase = Phase::with('cycle')->find($phaseId);
        $execution = isset($input['execution_id']) ? EvidenceExecution::findOrFail($input['execution_id']) : null;
        $resolvedRetry = $execution !== null && $execution->phase_id === $phaseId && ($execution->attempt_resolution !== null || ($execution->attempt_admitted_at === null && $execution->status === 'completed'));
        if ($observedPhase === null || $observedPhase->cycle->participant_id !== $participant->id
            || (! $resolvedRetry && ($observedPhase->completed_at !== null || $observedPhase->cycle->completed_at !== null))) {
            throw new ConflictHttpException('The participation state changed. Refresh before contributing.');
        }

        if (($input['action'] ?? null) === 'preparation.start') {
            if (($input['proposition_id'] ?? null) !== $observedPhase->gate_proposition_id
                || array_diff(array_keys($input), ['action', 'proposition_id', 'purpose', 'artifacts']) !== []) {
                throw new ConflictHttpException('Preparation requires the exact current Gate and validated selections.');
            }
            app(PreparationService::class)->start($participant, $observedPhase, array_intersect_key($input, array_flip(['purpose', 'artifacts'])));

            return $this->stateFor($participant->fresh());
        }
        if (isset($input['execution_id'])) {
            if ($execution->phase_id !== $phaseId) {
                throw new ConflictHttpException('The Preparation does not belong to this Phase.');
            }
            if ($execution->type === 'instrument' || $execution->status === 'failed') {
                app(AcquisitionAttemptService::class)->resolve($participant, $execution, $input);
            } elseif (($input['action'] ?? null) === 'preparation.defer') {
                $schema = app(PreparationService::class)->resolutionSchema($participant, $observedPhase, $execution);
                $this->contributions->validateSchema($schema, $input);
                app(PreparationService::class)->defer($participant, $execution, $input['reason']);
            } else {
                app(PreparationService::class)->contribute($participant, $execution, $input);
            }
            if ($resolvedRetry) {
                return $this->stateFor($participant->fresh());
            }
            app(PotentialProjectionService::class)->refresh($participant->substrate_id);
            $this->cycleEntry->prepare($participant->fresh());

            return $this->stateFor($participant->fresh());
        }
        if (app(PreparationService::class)->active($observedPhase) !== null) {
            throw new ConflictHttpException('Resolve the unfinished Preparation before ordinary participation.');
        }

        // Validate before network I/O, then guard the prepared plan by version.
        // An observation is discarded when the Phase or admissibility changed.
        $observed = null;
        if (($input['action'] ?? null) === 'instrument.execute') {
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
        $expectedClosureObservation ??= $expectedSchema['$comment'] ?? null;
        if (($input['action'] ?? null) === 'proposition.commit' && $expectedClosureObservation !== null
            && $expectedClosureObservation !== ($validationPlan['schema']['$comment'] ?? null)) {
            throw new ConflictHttpException('The observed Closure changed. Retry from fresh state.');
        }
        try {
            if (($input['action'] ?? null) !== 'instrument.execute') {
                $this->contributions->validateSchema($validationPlan['schema'], $input);
            }
        } catch (InvalidArgumentException $exception) {
            if ($expectedSchema !== null) {
                throw new ConflictHttpException('The participation options changed. Rebuild state before contributing.', $exception);
            }
            throw $exception;
        }

        $explorationWitness = null;
        if (($input['action'] ?? null) === 'participant.rest') {
            $restPhase = Phase::with('cycle.participant')->find($phaseId);
            if ($restPhase !== null && $restPhase->cycle->participant_id === $participant->id) {
                $explorationWitness = app(ExplorationEventService::class)->inspect($restPhase);
            }
        }

        DB::transaction(function () use (
            $participant,
            $phaseId,
            $input,
            $observed,
            $explorationWitness,
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
            if (app(PreparationService::class)->active($phase) !== null) {
                throw new ConflictHttpException('A Preparation started while this contribution was pending.');
            }
            if ($input['action'] === 'participant.rest') {
                if (app(ContinuationOpportunityService::class)->pendingEvidence($phase)) {
                    throw new ConflictHttpException('Resolve the pending evidence execution before Rest.');
                }
                // The Rest witness describes the guarded pre-completion field.
                // A stale proof is discarded and can be retried after this Rest.
                $validProof = $explorationWitness !== null && $explorationWitness['stable']
                    && $explorationWitness['field_version'] === (int) $substrate->field_version;
                $this->rest($lockedParticipant, $phase, RestReason::from($explorationWitness['rest_reason'] ?? 'no_available_move'));
                if ($validProof) {
                    app(ExplorationEventService::class)->publish($phase, $explorationWitness);
                }
                // Publish the existing Rest outcome before completion invalidates
                // future Closure previews; both remain in this root-locked transaction.
                $phase->cycle->forceFill(['completed_at' => now()])->save();

                return;
            }
            $gate = $this->dispatcher->dispatch(
                $lockedParticipant,
                $phase,
                $input,
                $observed,
            );
            if ($input['action'] !== 'instrument.execute') {
                $this->progression->advance($phase, $gate);
            }

        });

        app(PotentialProjectionService::class)->refresh($participant->substrate_id);
        $this->cycleEntry->prepare($participant->fresh());

        return $this->stateFor($participant->fresh());
    }

    /** The validated plan exposes Rest only in a Phase with no meaningful remaining continuation. */
    private function rest(Participant $participant, Phase $phase, RestReason $reason): void
    {
        $participant->current_proposition_id = $phase->gate_proposition_id;
        $participant->save();
        $phase->completed_at = now();
        $phase->save();
        $phase->cycle->rested_at = now();
        $phase->cycle->rest_reason = $reason;
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
                $participant,
            );

            return [
                'mode' => 'potential',
                'selection' => $selection['reason'] ?? 'recovery',
                'available' => $selection !== null,
                'proposition_id' => ($selection['proposition'] ?? null)?->getKey(),
                'distance' => $selection['distance'] ?? null,
                'potential_charge' => $selection['potential_charge'] ?? 0.0,
                'message' => $selection === null
                    ? 'No eligible non-exhausted Committal is currently reachable along your backward causal lineage.'
                    : (($selection['reason'] ?? null) === 'origin_renewal'
                        ? 'The exhausted branch is historical. Begin a fresh Distinction at Origin.'
                        : 'Begin the next Cycle at the nearest non-exhausted eligible Committal on your backward causal lineage.'),
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
            'requirements' => ['statement', 'attestation'],
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
