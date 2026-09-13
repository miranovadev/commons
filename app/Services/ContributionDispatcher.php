<?php

namespace App\Services;

use App\Domain\Observables\ObservableValues;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\Observables\LineageMeasurementService;
use DomainException;
use Illuminate\Support\Facades\DB;

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
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function dispatch(
        Participant $participant,
        Phase $phase,
        array $input,
    ): Proposition {
        return DB::transaction(function () use ($participant, $phase, $input): Proposition {
            // Also protect direct service callers before attestations or FK inserts.
            Substrate::query()->lockForUpdate()->findOrFail($participant->substrate_id);
            if ($phase->cycle->participant->substrate_id !== $participant->substrate_id) {
                throw new DomainException('A contribution cannot cross substrate boundaries.');
            }
            $gate = $phase->gate()->firstOrFail();
            $action = $input['action'];
            if (! in_array($action, config("phases.{$phase->kind->value}.actions", []), true)
                || $phase->cycle->participant_id !== $participant->id) {
                throw new DomainException('The contribution must belong to this Participant and Phase.');
            }
            if (! $this->generation->available($phase, $action)) {
                throw new DomainException('This generation opportunity is unavailable. Support an existing Write; Constrain reclassification also requires a completed Receipt.');
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
                    ? 'Revision requires an owned active head in the current write register.'
                    : 'Support requires an admissible Proposition in the current Write.');
            }

            return match ($input['action']) {
                'proposition.traverse' => Proposition::query()
                    ->findOrFail($input['proposition_id']),
                'proposition.variant',
                'proposition.reclassify' => $this->proposition(
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
                'proposition.bias' => $this->bias($phase, $input),
                'attestation.create' => $this->attest(
                    $participant,
                    $phase,
                    $input,
                ),
                'instrument.execute' => $this->executeInstrument(
                    $phase,
                    $input,
                ),
                'proposition.commit' => $this->commit($phase, $input),
                default => throw new DomainException(
                    'The selected contribution action is not supported.'
                ),
            };
        });
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
        );
        $supersession = $this->supersede(
            $phase,
            $gate,
            $target,
            str($input['action'])->after('proposition.')->toString(),
        );

        $this->ledger->record($supersession);

        return $this->refreshMeasurement($target);
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

        return $this->refreshMeasurement($target);
    }

    /** Unary directional selection preserves content, never accumulated history. */
    private function bias(Phase $phase, array $input): Proposition
    {
        if (array_diff(array_keys($input), ['action', 'proposition_id']) !== []) {
            throw new DomainException('Bias selects one Proposition; it accepts no statement or second source.');
        }

        return DB::transaction(function () use ($phase, $input): Proposition {
            Substrate::query()->whereKey($phase->cycle->participant->substrate_id)
                ->lockForUpdate()->firstOrFail();
            $source = $this->bias->forPhase($phase)->firstWhere('id', $input['proposition_id'] ?? null);
            if ($source === null) {
                throw new DomainException('Bias requires an active Gate contributing to the dominant Stance/Frontier lean.');
            }
            $target = $this->write($phase, $source->statement, tier: 'expressive', preserveStatement: true);
            $this->ledger->record($this->supersede($phase, $source, $target, 'reclassify'));

            return $this->refreshMeasurement($target);
        });
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
    ): Proposition {
        $proposition = Proposition::query()
            ->findOrFail($input['proposition_id']);
        $this->instruments->execute($phase, $proposition, $input['instrument']);

        return $proposition;
    }

    private function commit(
        Phase $phase,
        array $input,
    ): Proposition {
        if (array_diff(array_keys($input), ['action', 'proposition_id']) !== []) {
            throw new DomainException('Commit selects one Proposition; it accepts no authored statement.');
        }

        return DB::transaction(function () use ($phase, $input): Proposition {
            // Serialize evaluation and the resulting horizon transition with ledger writes.
            Substrate::query()->whereKey($phase->cycle->participant->substrate_id)
                ->lockForUpdate()->firstOrFail();
            $gate = $phase->kind === PhaseKind::Order
                ? $this->regions->forPhase($phase)->firstWhere('id', $input['proposition_id'] ?? null) : null;
            if ($gate === null) {
                throw new DomainException('Commit requires an active Closure candidate in the current Commit Region.');
            }
            $eligibility = $this->admissibility->evaluate($gate, $phase);
            if (! $eligibility['admissible']) {
                throw new DomainException(
                    'Commitment requires an effective Attestation, completed Receipt, and aligned Depth/Cadence directions. Missing: '.implode(', ', $eligibility['reasons'])
                );
            }

            $governing = $this->evidence->directionFor($gate);
            $target = $this->write(
                $phase,
                $gate->statement,
                tier: 'committal',
                preserveStatement: true,
            );
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
            );

            return $target;
        });
    }

    private function write(
        Phase $phase,
        string $statement,
        string $tier,
        bool $preserveStatement = false,
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
        $this->ledger->record($proposition);

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
