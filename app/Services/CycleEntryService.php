<?php

namespace App\Services;

use App\Domain\Observables\ObservableValues;
use App\Enums\ContextDomain;
use App\Enums\LedgerEntryKind;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\SubstrateLedgerEntry;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class CycleEntryService
{
    public function __construct(
        private LedgerService $ledger,
        private InstrumentExecutionService $instruments,
        private PropositionMeasurementService $measurements,
        private ProjectionService $projections,
        private CommitmentSnapshotService $commitments,
        private ContextHorizonProjector $horizons,
        private PotentialChargeService $potential,
    ) {}

    /**
     * Establish the substrate's Origin and place the Participant at the first
     * live Settlement Cycle. Potential will govern subsequent Cycle entry.
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

            if ($this->hasPropositionHistory($substrate)) {
                $selection = $this->potential->selectFor($substrate);
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

            $bootstrapCycle = $this->createCycle($lockedParticipant);
            $order = $this->createPhase(
                $bootstrapCycle,
                PhaseKind::Order,
                'canon',
            );
            $proposition = $this->createOrigin(
                $order,
                $origin['statement'],
            );

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
                $lockedParticipant,
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

    private function createLiveCycle(
        Participant $participant,
        Substrate $substrate,
        Proposition $gate,
    ): void {
        $cycle = $this->createCycle($participant);
        $domain = $this->horizons->currentFor($substrate)->domain;
        $firstRegister = $domain === ContextDomain::Emergence
            ? 'activity'
            : 'signal';

        $this->createPhase(
            $cycle,
            PhaseKind::Distinguish,
            $firstRegister,
            $gate,
        );
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

    private function createCycle(Participant $participant): Cycle
    {
        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $participant->getKey(),
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
        Participant $participant,
    ): void {
        $execution = $this->instruments->start(
            $phase,
            $proposition,
            $input['kind'],
            $input['reference'],
            $input,
        );
        $this->instruments->complete(
            $execution,
            $proposition,
            metadata: [
                'source' => 'participant-origin-bootstrap',
                'participant_id' => $participant->getKey(),
            ],
        );
    }
}
