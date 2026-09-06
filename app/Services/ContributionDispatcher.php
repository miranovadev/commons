<?php

namespace App\Services;

use App\Domain\Observables\ObservableValues;
use App\Models\Attestation;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Supersession;
use DomainException;

final readonly class ContributionDispatcher
{
    public function __construct(
        private LedgerService $ledger,
        private InstrumentExecutionService $instruments,
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
            'proposition.revise',
            'proposition.reclassify' => $this->proposition(
                $phase,
                $gate,
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

    /**
     * @param  array<string, mixed>  $input
     */
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
            $this->startInstrument($phase, $target, $input['instrument']);
        }

        $this->ledger->record($supersession);

        return $target;
    }

    /**
     * @param  array<string, mixed>  $input
     */
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

        return $target;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function attest(
        Participant $participant,
        Phase $phase,
        array $input,
    ): Proposition {
        $proposition = Proposition::query()
            ->findOrFail($input['proposition_id']);
        $alreadyAttested = Attestation::query()
            ->whereBelongsTo($proposition)
            ->whereHas(
                'phase.cycle',
                fn ($query) => $query->whereBelongsTo($participant),
            )
            ->exists();

        if ($alreadyAttested) {
            throw new DomainException(
                'The Participant has already attested this Proposition.'
            );
        }

        $attestation = new Attestation;
        $attestation->forceFill([
            'phase_id' => $phase->getKey(),
            'proposition_id' => $proposition->getKey(),
            'direction' => $input['direction'],
            'rationale' => $input['rationale'] ?? null,
        ]);
        $attestation->save();
        $this->ledger->record($attestation);

        return $proposition;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function executeInstrument(
        Phase $phase,
        array $input,
    ): Proposition {
        $proposition = Proposition::query()
            ->findOrFail($input['proposition_id']);
        $this->startInstrument($phase, $proposition, $input['instrument']);

        return $proposition;
    }

    private function commit(
        Phase $phase,
        Proposition $gate,
    ): Proposition {
        $hasAttestation = Attestation::query()
            ->whereBelongsTo($gate)
            ->exists();
        $attestationIds = Attestation::query()
            ->whereBelongsTo($gate)
            ->pluck('id');
        $hasReceipt = Receipt::query()
            ->where(function ($query) use ($gate, $attestationIds): void {
                $query->where(function ($query) use ($gate): void {
                    $query
                        ->where('receiptable_type', $gate->getMorphClass())
                        ->where('receiptable_id', $gate->getKey());
                })->orWhere(function ($query) use ($attestationIds): void {
                    $query
                        ->where(
                            'receiptable_type',
                            (new Attestation)->getMorphClass(),
                        )
                        ->whereIn('receiptable_id', $attestationIds);
                });
            })
            ->exists();

        if (! $hasAttestation || ! $hasReceipt) {
            throw new DomainException(
                'Commitment requires an Attestation and Receipt in the current lineage.'
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
        $values = $this->measurements
            ->recalculate($target);
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

    /**
     * @param  array{kind: string, reference: string}  $instrument
     */
    private function startInstrument(
        Phase $phase,
        Proposition $proposition,
        array $instrument,
    ): void {
        $this->instruments->start(
            $phase,
            $proposition,
            $instrument['kind'],
            $instrument['reference'],
            $instrument,
        );
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
}
