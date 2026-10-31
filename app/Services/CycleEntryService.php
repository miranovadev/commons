<?php

namespace App\Services;

use App\Domain\Observables\ObservableValues;
use App\Enums\LedgerEntryKind;
use App\Enums\PhaseKind;
use App\Exceptions\PotentialProjectionUnavailable;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\SubstrateLedgerEntry;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class CycleEntryService
{
    public function __construct(
        private LedgerService $ledger,
        private CommitRegionPolarityService $polarity,
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
        $this->start($participant, $origin, required: true);
        app(PotentialProjectionService::class)->refresh($participant->substrate_id);
    }

    /** Infrastructure entry/rollover: never infer an Origin or invent a recovery Gate. */
    public function prepare(Participant $participant): void
    {
        $activeBefore = $this->hasActiveCycle($participant);
        try {
            $this->start($participant, null, required: false);
            // Only a newly created Cycle has dirty Phase touches. Never derive
            // recovery or backfill merely to find an existing position.
            if (! $activeBefore && $this->hasActiveCycle($participant)) {
                app(PotentialProjectionService::class)->refresh($participant->substrate_id);
            }
        } catch (PotentialProjectionUnavailable) {
            // stateFor exposes the recoverable maintenance state; the scheduler
            // owns retries if a worker died after the ledger write.
        }
    }

    private function start(Participant $participant, ?array $origin, bool $required): void
    {
        // Wakes call prepare on every iteration; an existing Cycle is a read-only no-op.
        if (! $required && $this->hasActiveCycle($participant)) {
            return;
        }
        if ($origin !== null && ! in_array($origin['attestation']['direction'] ?? null, ['support', 'refute'], true)) {
            throw new DomainException('Origin requires an explicit Support or Refute Attestation.');
        }

        $participant = $participant->fresh();
        $version = (int) Substrate::whereKey($participant->substrate_id)->value('field_version');
        $positionId = $participant->current_proposition_id;
        $gate = null;
        if ($positionId !== null) {
            $gate = $this->positionedEntryFor($participant)['proposition'] ?? null;
        } elseif ($this->hasPropositionHistory($participant->substrate)
            && CommitmentSnapshot::where('substrate_id', $participant->substrate_id)->exists()) {
            $gate = $this->potential->selectFor($participant->substrate)['proposition'];
        }

        DB::transaction(function () use ($participant, $origin, $required, $version, $positionId, $gate): void {
            $substrate = Substrate::query()
                ->lockForUpdate()
                ->findOrFail($participant->substrate_id);
            $lockedParticipant = Participant::query()
                ->lockForUpdate()
                ->findOrFail($participant->getKey());

            if ($this->hasActiveCycle($lockedParticipant)) {
                if (! $required) {
                    return;
                }
                throw new ConflictHttpException(
                    'The Participant already has an active Cycle.'
                );
            }

            if ((int) $substrate->field_version !== $version || $lockedParticipant->current_proposition_id !== $positionId) {
                if (! $required) {
                    return;
                }
                throw new ConflictHttpException('The active field changed during Cycle entry. Retry from fresh state.');
            }
            if ($gate !== null) {
                if (app(ExhaustionService::class)->unavailable($gate)) {
                    throw new ConflictHttpException('The selected branch became historical. Retry from fresh state.');
                }
                $this->createLiveCycle($lockedParticipant, $substrate, $gate);

                return;
            }
            if ($positionId !== null || $this->hasPropositionHistory($substrate)) {
                if (! $required) {
                    return;
                }
                throw new ConflictHttpException('No valid recovery basin entry can be established from this position.');
            }

            if ($origin === null) {
                if (! $required) {
                    return;
                }
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
            $this->createAttestation($order, $proposition, $origin['attestation']);

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
     * Ordinary expressive completion resumes its own Committal region. Rest
     * invokes structural recovery, which may retain an unexplored open basin.
     */
    public function continuationFor(Participant $participant): ?Proposition
    {
        $latest = $participant->cycles()->latest('sequence')->first();
        if ($participant->current_proposition_id === null || ($latest?->rested_at !== null || $latest?->deferred_at !== null)) {
            return null;
        }
        $position = $participant->position()->firstOrFail();
        if ($position->phase->cycle->participant->substrate_id !== $participant->substrate_id) {
            throw new DomainException('A Participant cannot continue in another substrate.');
        }
        if (app(ExhaustionService::class)->unavailable($position)) {
            return null;
        }
        $seed = $this->regions->seedFor($position);

        return $seed?->commitmentSnapshot !== null ? $seed : null;
    }

    /** Ordinary continuation, then structural recovery. Only a stranded Participant can resume locally. */
    public function positionedEntryFor(Participant $participant): ?array
    {
        if ($continuation = $this->continuationFor($participant)) {
            return ['proposition' => $continuation, 'reason' => 'continuity'];
        }
        $position = $participant->position()->firstOrFail();
        $latest = $participant->cycles()->latest('sequence')->first();
        $selection = $this->potential->recoverFrom($position, $latest?->contextHorizon, $participant);
        if ($selection !== null) {
            return $selection;
        }
        $rest = $latest?->restedEntryPhase($position);
        if ($rest === null || app(ExhaustionService::class)->unavailable($position)) {
            return null;
        }
        // No ancestor is reachable. Preview local Distinguish on read-only copies
        // so a later opportunity can wake the Participant without an empty Cycle.
        $preview = clone $rest;
        $cycle = clone $latest;
        $preview->completed_at = $cycle->completed_at = $cycle->rested_at = null;
        $preview->setRelation('cycle', $cycle);
        $schema = app(InputSchemaService::class)->forParticipant($participant, [], [], context: new ParticipationContext($preview));

        return array_diff(array_keys($schema['$defs']['actions']), ['participant.rest']) !== []
            ? ['proposition' => $position, 'reason' => 'continuity'] : null;
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
        $cycle = $this->createCycle($participant, $horizon, $gate);
        $firstRegister = $cycle->contextHorizon->domain->registerFor(PhaseKind::Distinguish);

        $entry = $this->createPhase(
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

    private function createCycle(Participant $participant, ContextHorizon $horizon, ?Proposition $entryCommittal = null): Cycle
    {
        // The only production Cycle without an entry Committal is the initial Origin bootstrap.
        // Recovery/continuity always enters with its explicitly selected Committal.
        if ($entryCommittal === null && $this->hasPropositionHistory($participant->substrate)) {
            throw new DomainException('An ordinary Cycle requires an entry Committal.');
        }
        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $participant->getKey(),
            'context_horizon_id' => $horizon->id,
            'entry_committal_id' => $entryCommittal?->id,
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
}
