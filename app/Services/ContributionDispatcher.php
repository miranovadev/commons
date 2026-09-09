<?php

namespace App\Services;

use App\Domain\Observables\ObservableValues;
use App\Models\Attestation;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use DomainException;

final readonly class ContributionDispatcher
{
    public function __construct(
        private LedgerService $ledger,
        private OperationalInstrumentService $instruments,
        private CommitmentAdmissibilityService $admissibility,
        private PropositionMeasurementService $measurements,
        private ProjectionService $projections,
        private CommitmentSnapshotService $commitments,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function dispatch(
        Participant $participant,
        Phase $phase,
        array $input,
    ): Proposition {
        $gate = $phase->gate()->firstOrFail();

        return match ($input['action']) {
            'proposition.select' => Proposition::query()
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
            'attestation.create' => $this->attest(
                $participant,
                $phase,
                $input,
            ),
            'instrument.execute' => $this->executeInstrument(
                $phase,
                $input,
            ),
            'proposition.commit' => $this->commit($phase, $gate),
            default => throw new DomainException(
                'The selected contribution action is not supported.'
            ),
        };
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

        if (isset($input['instrument'])) {
            $this->instruments->execute($phase, $target, $input['instrument']);
        }

        $this->ledger->record($supersession);

        return $this->refreshMeasurement($target);
    }

    /** @param array<string, mixed> $input */
    private function reconcile(
        Phase $phase,
        Proposition $gate,
        array $input,
    ): Proposition {
        $other = Proposition::query()
            ->findOrFail($input['other_proposition_id']);
        $target = $this->write(
            $phase,
            $input['statement'],
            tier: 'expressive',
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
        Proposition $gate,
    ): Proposition {
        if (! $this->admissibility->evaluate($gate)['admissible']) {
            throw new DomainException(
                'Commitment requires an effective Attestation on the current Gate and a completed Receipt on that Gate or one of its effective Attestations.'
            );
        }

        $target = $this->write(
            $phase,
            $gate->statement,
            tier: 'committal',
        );
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
            'lineage-observables-v1',
            $supersession,
        );

        return $target;
    }

    private function write(
        Phase $phase,
        string $statement,
        string $tier,
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
            'statement' => trim($statement),
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
