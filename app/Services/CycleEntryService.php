<?php

namespace App\Services;

use App\Domain\Observables\ObservableValues;
use App\Enums\LedgerEntryKind;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\SubstrateLedgerEntry;
use DomainException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class CycleEntryService
{
    public function __construct(
        private LedgerService $ledger,
        private CommitRegionPolarityService $polarity,
        private OperationalInstrumentService $instruments,
        private PropositionMeasurementService $measurements,
        private ProjectionService $projections,
        private CommitmentSnapshotService $commitments,
        private ContextHorizonProjector $horizons,
        private PotentialChargeService $potential,
        private CommitRegionService $regions,
    ) {}

    /**
     * Establish the substrate's Origin and place the Participant at the first
     * live Settlement Cycle. Retain local Committal continuity; use Potential
     * for initial entry or relocation after local continuation is exhausted.
     *
     * @param  array<string, mixed>|null  $origin
     */
    public function enter(Participant $participant, ?array $origin = null): void
    {
        DB::transaction(function () use ($participant, $origin): void {
            $substrate = Substrate::query()
                ->lockForUpdate()
                ->findOrFail($participant->substrate_id);
            $lockedParticipant = Participant::query()
                ->lockForUpdate()
                ->findOrFail($participant->getKey());

            if ($this->hasActiveCycle($lockedParticipant)) {
                throw new ConflictHttpException(
                    'The Participant already has an active Cycle.'
                );
            }

            if ($lockedParticipant->current_proposition_id !== null) {
                $gate = $this->continuationFor($lockedParticipant)
                    ?? ($this->potential->recoverFrom($lockedParticipant->position()->firstOrFail())['proposition'] ?? null);
                if ($gate === null) {
                    throw new ConflictHttpException('No ancestral Committal region has positive Potential Charge.');
                }
                $this->createLiveCycle(
                    $lockedParticipant,
                    $substrate,
                    $gate,
                );

                return;
            }

            if ($this->hasPropositionHistory($substrate)) {
                $selection = $this->potential->selectFor($substrate);
                if ($selection === null) {
                    throw new ConflictHttpException('No structurally reachable measured Committal Proposition is available for Cycle entry.');
                }
                $this->createLiveCycle(
                    $lockedParticipant,
                    $substrate,
                    $selection['proposition'],
                );

                return;
            }

            if ($origin === null) {
                throw new ConflictHttpException(
                    'The substrate requires an Origin before Potential can select a Gate.'
                );
            }

            $bootstrapCycle = $this->createCycle($lockedParticipant, $this->horizons->currentFor($substrate));
            $order = $this->createPhase(
                $bootstrapCycle,
                PhaseKind::Order,
                'canon',
            );
            $proposition = $this->createOrigin(
                $order,
                $origin['statement'],
            );

            $this->polarity->establishOrigin($proposition, $origin['attestation']['direction']);
            $this->ledger->record($proposition);
            $this->createAttestation(
                $order,
                $proposition,
                $origin['attestation'],
            );
            $this->createReceipt(
                $order,
                $proposition,
                $origin['instrument'],
            );

            $values = $this->measurements->recalculate($proposition);
            $projection = $this->projections->from(new ObservableValues(
                $values->correspondence,
                $values->chirality,
                $values->closure,
            ));
            $this->commitments->record(
                $proposition,
                $projection['prominence'],
                $projection['turbulence'],
                'origin-bootstrap-v1',
            );

            $order->completed_at = now();
            $order->save();
            $bootstrapCycle->completed_at = now();
            $bootstrapCycle->save();

            $this->createLiveCycle(
                $lockedParticipant,
                $substrate,
                $proposition,
            );
        });
    }

    /**
     * A Commit is already a continuation, regardless of its current Potential.
     * Non-tragic expressive completion resumes its own Committal region. Rest
     * explicitly exhausts that local path, so recovery must select elsewhere.
     */
    public function continuationFor(Participant $participant): ?Proposition
    {
        if ($participant->current_proposition_id === null
            || $participant->cycles()->latest('sequence')->first()?->rested_at !== null) {
            return null;
        }
        $position = $participant->position()->firstOrFail();
        if ($position->phase->cycle->participant->substrate_id !== $participant->substrate_id) {
            throw new DomainException('A Participant cannot continue in another substrate.');
        }
        $seed = $this->regions->seedFor($position);

        return $seed?->commitmentSnapshot !== null ? $seed : null;
    }

    private function createLiveCycle(
        Participant $participant,
        Substrate $substrate,
        Proposition $gate,
    ): void {
        if ($gate->tier !== 'committal') {
            throw new DomainException('A Cycle must begin from a Committal Proposition.');
        }

        $horizon = $this->horizons->forEntry($gate);
        if ($horizon->substrate_id !== $substrate->id) {
            throw new DomainException('Cycle entry cannot cross substrates.');
        }
        $cycle = $this->createCycle($participant, $horizon);
        $firstRegister = $cycle->contextHorizon->domain->registerFor(PhaseKind::Distinguish);

        $this->createPhase(
            $cycle,
            PhaseKind::Distinguish,
            $firstRegister,
            $gate,
        );
        $participant->current_proposition_id = $gate->getKey();
        $participant->save();
    }

    private function hasActiveCycle(Participant $participant): bool
    {
        return Cycle::query()
            ->whereBelongsTo($participant)
            ->whereNull('completed_at')
            ->exists();
    }

    private function hasPropositionHistory(Substrate $substrate): bool
    {
        return SubstrateLedgerEntry::query()
            ->whereBelongsTo($substrate)
            ->where('kind', LedgerEntryKind::Proposition->value)
            ->exists();
    }

    private function createCycle(Participant $participant, ContextHorizon $horizon): Cycle
    {
        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $participant->getKey(),
            'context_horizon_id' => $horizon->id,
            'sequence' => (int) Cycle::query()
                ->whereBelongsTo($participant)
                ->max('sequence') + 1,
        ]);
        $cycle->save();

        return $cycle;
    }

    private function createPhase(
        Cycle $cycle,
        PhaseKind $kind,
        string $writeRegister,
        ?Proposition $gate = null,
    ): Phase {
        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->getKey(),
            'gate_proposition_id' => $gate?->getKey(),
            'kind' => $kind,
            'write_register' => $writeRegister,
            'started_at' => now(),
        ]);
        $phase->save();

        return $phase;
    }

    private function createOrigin(Phase $phase, string $statement): Proposition
    {
        $proposition = new Proposition;
        $proposition->forceFill([
            'phase_id' => $phase->getKey(),
            'register' => 'canon',
            'tier' => 'committal',
            'statement' => trim($statement),
        ]);
        $proposition->save();

        return $proposition;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function createAttestation(
        Phase $phase,
        Proposition $proposition,
        array $input,
    ): void {
        $attestation = new Attestation;
        $attestation->forceFill([
            'phase_id' => $phase->getKey(),
            'proposition_id' => $proposition->getKey(),
            'direction' => $input['direction'],
            'rationale' => $input['rationale'] ?? null,
        ]);
        $attestation->save();
        $this->ledger->record($attestation);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function createReceipt(
        Phase $phase,
        Proposition $proposition,
        array $input,
    ): void {
        $this->instruments->execute($phase, $proposition, $input, bootstrap: true);
    }
}
