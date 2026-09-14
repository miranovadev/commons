<?php

namespace App\Services;

use App\Enums\ContextDomain;
use App\Models\CommitmentSnapshot;
use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use App\Models\Supersession;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class CommitmentSnapshotService
{
    public function __construct(
        private LedgerService $ledger,
        private ContextHorizonProjector $horizons,
        private ClosureGateService $closureGate,
        private CommitmentAdmissibilityService $admissibility,
    ) {}

    public function record(
        Proposition $proposition,
        float $prominence,
        float $turbulence,
        string $calculatorVersion,
        ?Supersession $supersession = null,
    ): CommitmentSnapshot {
        $this->assertCommitment(
            $proposition,
            $prominence,
            $turbulence,
            $calculatorVersion,
            $supersession,
        );

        return DB::transaction(function () use (
            $proposition,
            $prominence,
            $turbulence,
            $calculatorVersion,
            $supersession,
        ): CommitmentSnapshot {
            $entry = $this->ledger->record($proposition);
            $substrate = $entry->substrate;

            if ($supersession !== null) {
                $supersessionEntry = $this->ledger->record($supersession);

                if ($supersessionEntry->substrate_id !== $substrate->getKey()) {
                    throw new DomainException(
                        'A Commit cannot cross substrate boundaries.'
                    );
                }
            }

            if (
                CommitmentSnapshot::query()
                    ->whereKey($proposition->getKey())
                    ->exists()
            ) {
                throw new DomainException(
                    'The Proposition already has an immutable commitment snapshot.'
                );
            }

            $measurement = PropositionMeasurement::query()
                ->lockForUpdate()
                ->find($proposition->getKey());

            if (
                $measurement === null
                || $measurement->correspondence === null
                || $measurement->chirality === null
                || $measurement->closure === null
            ) {
                throw new DomainException(
                    'A commitment requires all three Proposition measurements.'
                );
            }

            $isOrigin = $calculatorVersion === 'origin-bootstrap-v1'
                && $supersession === null && $entry->sequence === 1
                && ! $proposition->parents()->exists()
                && ! CommitmentSnapshot::where('substrate_id', $substrate->id)->exists();
            if (! $isOrigin && ! $this->admissibility->grounding($proposition)['admissible']) {
                throw new DomainException('Commitment requires effective Attestation and completed evidence.');
            }

            $horizon = $proposition->phase->cycle->contextHorizon;
            // Re-evaluate the actual persisted candidate before allowing a snapshot.
            $closure = $this->closureGate->forCommitment($proposition);
            if (! $closure['admissible']) {
                throw new DomainException('Commitment requires aligned Depth/Cadence directions.');
            }
            $previous = $horizon->predecessorCommitment();
            $tension = round($prominence - $turbulence, 7);
            $directionBefore = $horizon->domain;
            $directionAfter = ContextDomain::after(
                $tension,
                $directionBefore,
            );
            $evaluatedAt = now();

            $snapshot = new CommitmentSnapshot;
            $snapshot->forceFill([
                'proposition_id' => $proposition->getKey(),
                'substrate_id' => $substrate->getKey(),
                'ledger_entry_id' => $entry->getKey(),
                'supersession_id' => $supersession?->getKey(),
                'correspondence' => $measurement->correspondence,
                'chirality' => $measurement->chirality,
                'closure' => $measurement->closure,
                'depth' => $closure['depth'],
                'cadence' => $closure['cadence'],
                'depth_direction' => $closure['depth_direction'],
                'cadence_direction' => $closure['cadence_direction'],
                'closure_history_proposition_id' => $closure['history_proposition_id'],
                'tension_before' => $previous?->tension ?? 0.0,
                'prominence' => $prominence,
                'turbulence' => $turbulence,
                'tension' => $tension,
                'direction_before' => $directionBefore,
                'direction_after' => $directionAfter,
                'calculator_version' => trim($calculatorVersion),
                'evaluated_at' => $evaluatedAt,
            ]);
            $snapshot->save();

            $this->horizons->apply($snapshot);
            $proposition->setRelation('commitmentSnapshot', $snapshot);

            return $snapshot;
        });
    }

    private function assertCommitment(
        Proposition $proposition,
        float $prominence,
        float $turbulence,
        string $calculatorVersion,
        ?Supersession $supersession,
    ): void {
        if (! $proposition->exists || $proposition->tier !== 'committal') {
            throw new DomainException(
                'Only a persisted Committal Proposition can be committed.'
            );
        }

        foreach ([
            'prominence' => $prominence,
            'turbulence' => $turbulence,
        ] as $name => $value) {
            if (! is_finite($value) || $value < 0 || $value > 1) {
                throw new InvalidArgumentException(
                    "{$name} must be between 0 and 1."
                );
            }
        }

        if (trim($calculatorVersion) === '') {
            throw new InvalidArgumentException(
                'A calculator version is required for a commitment snapshot.'
            );
        }

        if (
            $supersession !== null
            && (
                ! $supersession->exists
                || $supersession->type !== 'commit'
                || $supersession->target_proposition_id
                    !== $proposition->getKey()
            )
        ) {
            throw new DomainException(
                'The supplied Supersession is not the Proposition commit edge.'
            );
        }
    }
}
