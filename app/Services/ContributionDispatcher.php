<?php

namespace App\Services;

use App\Domain\Evidence\InstrumentObservation;
use App\Domain\Observables\ObservableValues;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\Observables\CanonicalMeasurementService;
use App\Services\Observables\LineageMeasurementService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ContributionDispatcher
{
    public function __construct(
        private LedgerService $ledger,
        private OperationalInstrumentService $instruments,
        private CommitmentAdmissibilityService $admissibility,
        private PropositionMeasurementService $measurements,
        private ProjectionService $projections,
        private CommitmentSnapshotService $commitments,
        private ReconciliationOptionService $reconciliation,
        private BiasGateService $bias,
        private CommitRegionService $regions,
        private CommitRegionPolarityService $polarity,
        private AttestationEvidenceService $evidence,
        private PhaseGenerationService $generation,
        private PropositionOptionService $options,
        private PropositionOwnershipService $ownership,
        private ReceiptContextService $receipts,
        private ReceiptReplayService $replays,
        private PropositionStateService $state,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function dispatch(
        Participant $participant,
        Phase $phase,
        array $input,
        ?InstrumentObservation $observed = null,
    ): Proposition {
        $observed ??= $this->observeInstrument($participant, $phase, $input);

        return DB::transaction(function () use ($participant, $phase, $input, $observed): Proposition {
            // Also protect direct service callers before attestations or FK inserts.
            Substrate::query()->lockForUpdate()->findOrFail($participant->substrate_id);
            if ($phase->cycle->participant->substrate_id !== $participant->substrate_id) {
                throw new DomainException('A contribution cannot cross substrate boundaries.');
            }
            app(CausalContextService::class)->assertPhase($phase);
            $gate = $phase->gate()->firstOrFail();
            if (app(ExhaustionService::class)->unavailable($gate)) {
                throw new ConflictHttpException('This branch is historical. Refresh and Rest.');
            }
            $receiptOptions = $input['receipt_options'] ?? null;
            unset($input['receipt_options']);
            $action = $input['action'];
            if (! in_array($action, config("phases.{$phase->kind->value}.actions", []), true)
                || $phase->cycle->participant_id !== $participant->id) {
                throw new DomainException('The contribution must belong to this Participant and Phase.');
            }
            if (! $this->generation->available($phase, $action)) {
                throw new DomainException('This generation opportunity is unavailable. Use the existing Write; reclassification requires a Receipt, an effective Attestation and an unused source/register classification.');
            }
            if (in_array('gate-owner', (config("phases.{$phase->kind->value}.constraints", [])[$action] ?? []), true)
                && ! $this->ownership->ownedBy($gate, $participant)) {
                throw new DomainException('Reclassification requires ownership of the Gate.');
            }
            $targets = match ($action) {
                'attestation.create' => $this->options->attestationOptionsFor($phase),
                'instrument.execute' => $this->options->instrumentOptionsFor($phase),
                'proposition.traverse' => $this->options->traversalOptionsFor($phase),
                'proposition.revise' => $this->options->revisionOptionsFor($phase, $participant),
                default => null,
            };
            if ($targets !== null && ! $targets->contains('id', $input['proposition_id'] ?? null)) {
                throw new DomainException($action === 'proposition.revise'
                    ? 'Revision requires an owned active head in the current write register with an effective self-Refute from an earlier Cycle.'
                    : 'Support requires an admissible Proposition in the current Write.');
            }

            $affectsChirality = in_array($action, ['attestation.create', 'proposition.variant', 'proposition.revise',
                'proposition.reclassify', 'proposition.reconcile', 'proposition.commit'], true);
            if ($affectsChirality) {
                app(ChiralityPopulationHistoryService::class)->observe($participant->substrate_id);
            }
            $result = match ($input['action']) {
                'proposition.traverse' => Proposition::query()
                    ->findOrFail($input['proposition_id']),
                'proposition.reclassify' => $this->reclassify($phase, $gate, $input),
                'proposition.variant' => $this->proposition(
                    $phase,
                    $gate,
                    $input,
                ),
                'proposition.revise' => $this->revise(
                    $participant,
                    $phase,
                    $input,
                ),
                'proposition.reconcile' => $this->reconcile(
                    $phase,
                    $gate,
                    $input,
                ),
                'attestation.create' => $this->attest(
                    $participant,
                    $phase,
                    $input,
                ),
                'instrument.execute' => $this->executeInstrument(
                    $phase,
                    $input,
                    $observed,
                ),
                'proposition.commit' => $this->commit($phase, $input),
                default => throw new DomainException(
                    'The selected contribution action is not supported.'
                ),
            };
            if ($receiptOptions !== null) {
                $this->receipts->desired($participant, $phase, $receiptOptions);
            }

            if ($result->wasRecentlyCreated && $result->tier === 'expressive') {
                app(\App\Services\Observables\ProspectiveClosureService::class)->initialize($result);
                // Resolve the complete successful write, including selected Receipt
                // loads, before observing its field boundary. Existing propositions
                // (Traverse, Attestation, Instrument) and Committals are not births.
                $this->measurements->recalculate($result);
                app(TragedyService::class)->record($result);
            }

            if ($affectsChirality) {
                app(ChiralityPopulationHistoryService::class)->observe($participant->substrate_id);
            }

            return $result;
        });
    }

    public function observeInstrument(Participant $participant, Phase $phase, array $input): ?InstrumentObservation
    {
        return match ($input['action'] ?? null) {
            'instrument.execute' => $this->observeExecution($participant, $phase, $input),
            default => null,
        };
    }

    private function observeExecution(Participant $participant, Phase $phase, array $input): InstrumentObservation
    {
        if ($phase->cycle->participant_id !== $participant->id
            || ! in_array('instrument.execute', config("phases.{$phase->kind->value}.actions", []), true)
            || ! $this->options->instrumentOptionsFor($phase)->contains('id', $input['proposition_id'] ?? null)) {
            throw new DomainException('Support requires an admissible Proposition in the current Write.');
        }

        if (array_key_exists('replay_receipt_id', $input['instrument'])) {
            $this->assertReplayInput($input['instrument']);

            return $this->replays->observe($participant, $phase, $input['instrument']['replay_receipt_id'], $input['proposition_id']);
        }

        return $this->instruments->observeFor($phase, Proposition::findOrFail($input['proposition_id']), $input['instrument']);
    }

    /** @param array<string, mixed> $input */
    private function revise(
        Participant $participant,
        Phase $phase,
        array $input,
    ): Proposition {
        $source = Proposition::query()
            ->whereKey($input['proposition_id'])
            ->whereKeyNot($phase->gate_proposition_id)
            ->where('register', $phase->write_register)
            ->whereHas(
                'phase.cycle',
                fn ($query) => $query->whereBelongsTo($participant),
            )
            ->whereDoesntHave('children')
            ->first();

        if ($source === null) {
            throw new DomainException(
                'Revision requires an owned active head in the current write register.'
            );
        }

        return $this->proposition($phase, $source, $input);
    }

    /** @param array<string, mixed> $input */
    private function proposition(
        Phase $phase,
        Proposition $gate,
        array $input,
    ): Proposition {
        $target = $this->write(
            $phase,
            $input['statement'],
            tier: 'expressive',
            temporalSource: $gate,
        );
        $supersession = $this->supersede(
            $phase,
            $gate,
            $target,
            str($input['action'])->after('proposition.')->toString(),
        );

        $this->ledger->record($supersession);

        if ($input['action'] === 'proposition.variant') {
            app(ExplorationEpochService::class)->advance($phase);
        }

        return $target;
    }

    /** Classification has no authored content and no inherited judgments. */
    private function reclassify(Phase $phase, Proposition $source, array $input): Proposition
    {
        if (array_diff(array_keys($input), ['action', 'proposition_id', 'receipt_id']) !== []
            || ! is_string($input['receipt_id'] ?? null) || ! is_string($input['proposition_id'] ?? null)) {
            throw new DomainException('Reclassify requires proposition_id and receipt_id; the statement is preserved.');
        }
        $source = $phase->kind === PhaseKind::Bias
            ? $this->bias->operationalFor($phase)->firstWhere('id', $input['proposition_id'])
            : ($source->id === $input['proposition_id'] ? $source : null);
        if ($source === null || ! $this->generation->available($phase, 'proposition.reclassify', position: $source)
            || ($phase->kind !== PhaseKind::Bias && ! app(PhasePositionService::class)->acceptsGate($phase, $source))) {
            throw new DomainException('Reclassify requires an eligible Gate at the exact preceding Phase position.');
        }

        return $this->classified($phase, $source, $input['receipt_id']);
    }

    private function classified(Phase $phase, Proposition $source, string $receiptId): Proposition
    {
        $target = $this->write($phase, $source->statement, tier: 'expressive', preserveStatement: true, temporalSource: $source);
        app(HorizonReceiptService::class)->consume($phase, $source, $target, $receiptId);
        DB::table('reclassification_results')->insert([
            'source_proposition_id' => $source->id, 'register' => $phase->write_register, 'target_proposition_id' => $target->id,
        ]);
        $this->ledger->record($this->supersede($phase, $source, $target, 'reclassify'));

        return $target;
    }

    /** @param array<string, mixed> $input */
    private function reconcile(
        Phase $phase,
        Proposition $gate,
        array $input,
    ): Proposition {
        if (array_key_exists('statement', $input)) {
            throw new DomainException('Reconcile selects structure; it does not accept a statement.');
        }
        $other = $this->reconciliation->forPhase($phase)->firstWhere('id', $input['other_proposition_id']);
        if ($other === null) {
            throw new DomainException('Reconcile requires an admissible Gate counterpart.');
        }
        $target = $this->write(
            $phase,
            $gate->statement,
            tier: 'expressive',
            preserveStatement: true,
            temporalSource: $gate,
        );

        foreach ([$gate, $other] as $source) {
            $this->ledger->record(
                $this->supersede(
                    $phase,
                    $source,
                    $target,
                    'reconcile',
                ),
            );
        }

        return $target;
    }

    /** @param array<string, mixed> $input */
    private function attest(
        Participant $participant,
        Phase $phase,
        array $input,
    ): Proposition {
        $proposition = Proposition::query()
            ->findOrFail($input['proposition_id']);
        $previous = Attestation::query()
            ->effective()
            ->whereBelongsTo($proposition)
            ->whereHas(
                'phase.cycle',
                fn ($query) => $query->whereBelongsTo($participant),
            )
            ->lockForUpdate()
            ->first();

        if ($previous?->phase_id === $phase->getKey()) {
            throw new DomainException(
                'The Participant has already attested this Proposition in the current Write.'
            );
        }

        $attestation = new Attestation;
        $attestation->forceFill([
            'phase_id' => $phase->getKey(),
            'proposition_id' => $proposition->getKey(),
            'supersedes_attestation_id' => $previous?->getKey(),
            'direction' => $input['direction'],
            'rationale' => $input['rationale'] ?? null,
        ]);
        $attestation->save();
        $this->ledger->record($attestation);

        return $this->refreshMeasurement($proposition);
    }

    /** @param array<string, mixed> $input */
    private function executeInstrument(
        Phase $phase,
        array $input,
        ?InstrumentObservation $observed,
    ): Proposition {
        $proposition = Proposition::query()
            ->findOrFail($input['proposition_id']);
        if (array_key_exists('replay_receipt_id', $input['instrument'])) {
            $this->assertReplayInput($input['instrument']);
            $this->replays->replay($phase->cycle->participant, $phase, $input['instrument']['replay_receipt_id'], $observed, $proposition->id);
        } else {
            $this->instruments->execute($phase, $proposition, $input['instrument'], observed: $observed);
        }

        return $proposition;
    }

    private function assertReplayInput(array $instrument): void
    {
        if (array_keys($instrument) !== ['replay_receipt_id'] || ! is_string($instrument['replay_receipt_id'])) {
            throw new DomainException('Replay selects only the stored Receipt invocation.');
        }
    }

    private function commit(
        Phase $phase,
        array $input,
    ): Proposition {
        if (array_diff(array_keys($input), ['action', 'proposition_id']) !== []) {
            throw new DomainException('Commit selects one Proposition; it accepts no authored statement.');
        }

        $gate = $phase->kind === PhaseKind::Order
            ? $this->regions->forPhase($phase)->firstWhere('id', $input['proposition_id'] ?? null) : null;
        if ($gate === null) {
            throw new DomainException('Commit requires an active Closure candidate in the current Commit Region.');
        }
        $eligibility = $this->admissibility->evaluate($gate, $phase);
        if (! $eligibility['admissible']) {
            throw new DomainException(
                'Commitment requires an effective Attestation, completed Receipt, and aligned Historical Extent/Rhythm directions. Missing: '.implode(', ', $eligibility['reasons'])
            );
        }

        $resolved = $this->measurements->recalculate($gate);
        $correspondenceDirection = $this->measurements->correspondenceDirectionFor($gate)->value;
        $governing = $this->evidence->directionFor($gate);
        $target = $this->write(
            $phase,
            $gate->statement,
            tier: 'committal',
            preserveStatement: true,
            temporalSource: $gate,
        );
        app(CanonicalMeasurementService::class)->preserve($target, $gate, $resolved, $correspondenceDirection);
        DB::table('commit_results')->insert(['source_proposition_id' => $gate->id, 'target_proposition_id' => $target->id]);
        $this->polarity->establishCommit($target, $governing);
        $supersession = $this->supersede(
            $phase,
            $gate,
            $target,
            'commit',
        );
        $this->ledger->record($supersession);
        $values = $this->measurements->recalculate($target);
        $projections = $this->projections->from(
            new ObservableValues(
                $values->correspondence,
                $values->chirality,
                $values->closure,
            ),
        );
        $this->commitments->record(
            $target,
            $projections['prominence'],
            $projections['turbulence'],
            LineageMeasurementService::VERSION,
            $supersession,
            $eligibility['closure'],
        );

        return $target;
    }

    private function write(
        Phase $phase,
        string $statement,
        string $tier,
        bool $preserveStatement = false,
        ?Proposition $temporalSource = null,
    ): Proposition {
        if ($phase->write_register === null) {
            throw new DomainException(
                'The current Phase does not expose a writable register.'
            );
        }

        $proposition = new Proposition;
        $proposition->forceFill([
            'phase_id' => $phase->getKey(),
            'register' => $phase->write_register,
            'tier' => $tier,
            'statement' => $preserveStatement ? $statement : trim($statement),
        ]);
        $proposition->save();
        $this->ledger->record($proposition, $temporalSource);

        return $proposition;
    }

    private function supersede(
        Phase $phase,
        Proposition $source,
        Proposition $target,
        string $type,
    ): Supersession {
        $supersession = new Supersession;
        $supersession->forceFill([
            'phase_id' => $phase->getKey(),
            'source_proposition_id' => $source->getKey(),
            'target_proposition_id' => $target->getKey(),
            'type' => $type,
        ]);
        $supersession->save();

        return $supersession;
    }

    private function refreshMeasurement(Proposition $proposition): Proposition
    {
        $this->measurements->recalculate($proposition);

        return $proposition;
    }
}
