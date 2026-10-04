<?php

namespace App\Services\Observables;

use App\Domain\Observables\ChiralityValues;
use App\Enums\ObservableDirection;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Durable expressive state: only ledgered own-Attestation events advance it. */
final readonly class LocalChiralityStateService
{
    public function __construct(
        private ContextLineageComparisonService $lineages,
        private ChiralityCalculator $evidence,
        private LocalChiralityCalculator $calculator,
    ) {}

    public function initialize(Proposition $proposition): void
    {
        if ($proposition->tier !== 'expressive') {
            return;
        }
        DB::table('local_chirality_states')->insertOrIgnore([
            'proposition_id' => $proposition->id, ...$this->fresh($proposition),
        ]);
    }

    /** Read-only; never measure from live population on a cache miss. */
    public function components(Proposition $proposition, ?ParticipationContext $context = null): ChiralityValues
    {
        $state = $context === null
            ? DB::table('local_chirality_states')->where('proposition_id', $proposition->id)->first()
            : $context->remember('chirality:local-states', fn () => DB::table('local_chirality_states')
                ->whereIn('proposition_id', $context->propositions->keys())->get()->keyBy('proposition_id'))->get($proposition->id);
        if ($state === null) {
            if ($context === null ? Attestation::where('proposition_id', $proposition->id)->exists() : $context->attestationsFor([$proposition->id])->isNotEmpty()) {
                throw new DomainException('Attested proposition '.$proposition->id.' has no event-born local Chirality state; explicit historical migration is required.');
            }
            $state = (object) $this->fresh($proposition, $context);
        }

        return new ChiralityValues((float) $state->orientation, (float) $state->attestation_cadence,
            currentActs: (int) $state->act_count,
            retainedOrientation: ObservableDirection::from($state->orientation_direction),
            retainedCadence: ObservableDirection::from($state->attestation_cadence_direction));
    }

    /** Called within LedgerService's substrate-locked transaction, after recording the act. */
    public function attest(Attestation $attestation): void
    {
        $proposition = $attestation->proposition;
        if ($proposition->tier !== 'expressive') {
            return; // Commit measurement remains a separate, later stage.
        }
        $state = DB::table('local_chirality_states')->where('proposition_id', $proposition->id)->lockForUpdate()->first();
        if ($state === null) {
            // An existing but previously unattested proposition can start with its
            // first genuine event. Never discard or reconstruct historical acts.
            if (Attestation::where('proposition_id', $proposition->id)->where('id', '!=', $attestation->id)->exists()) {
                throw new DomainException('Attested proposition '.$proposition->id.' has no event-born local Chirality state; explicit historical migration is required.');
            }
            $this->initialize($proposition);
            $state = DB::table('local_chirality_states')->where('proposition_id', $proposition->id)->first();
        }
        if ($state->last_attestation_id === $attestation->id) {
            return;
        }
        $comparison = $this->lineages->for($proposition);
        $previous = $comparison->previous;
        // The calibrated Origin is a polarity reference, not an empirical lean sample.
        $previousLean = $previous === null || (int) $previous->horizon->sequence === 1 ? 0.0
            : $this->evidence->leanFor($this->evidence->activeIds($previous->propositions, $previous->supersessions), $previous->context);
        $orientation = $previous === null ? 0.0
            : $this->calculator->orientation($this->evidence->leanFor(collect([$proposition->id])), $previousLean);

        // Snapshot the represented Cycle-entry count at the genuine act, under the
        // same root lock as Cycle entry. No timestamps, lineage replay or foreign beats.
        $position = $proposition->phase->cycle->entry_committal_id === null ? 0 : Cycle::where('context_horizon_id', $comparison->current->horizon->id)
            ->where('entry_committal_id', $proposition->phase->cycle->entry_committal_id)->count();
        $intervals = json_decode($state->intervals, true, flags: JSON_THROW_ON_ERROR);
        if ($state->last_position !== null) {
            $interval = $position - (int) $state->last_position;
            if ($interval < 0) {
                throw new DomainException('Represented Cycle-entry position cannot move backwards.');
            }
            $intervals[] = $interval;
        }
        $cadence = $this->calculator->cadence($intervals, $proposition->id)['value'];
        $values = new ChiralityValues($orientation, $cadence);
        DB::table('local_chirality_states')->where('proposition_id', $proposition->id)->update([
            'orientation' => $orientation, 'attestation_cadence' => $cadence, 'chirality' => $values->average(),
            ...$values->resolveDirections(ObservableDirection::from($state->orientation_direction), ObservableDirection::from($state->attestation_cadence_direction)),
            'intervals' => json_encode($intervals, JSON_THROW_ON_ERROR), 'last_position' => $position,
            'last_attestation_id' => $attestation->id, 'act_count' => $state->act_count + 1,
        ]);
    }

    private function fresh(Proposition $proposition, ?ParticipationContext $context = null): array
    {
        $entry = $proposition->phase->cycle->entry_committal_id;
        $snapshot = $entry === null ? null : ($context === null ? CommitmentSnapshot::find($entry) : $context->snapshots->firstWhere('proposition_id', $entry));

        return ['orientation' => 0.0, 'attestation_cadence' => 0.0, 'chirality' => 0.0,
            // Preserve the diagnostic entry-frame hysteresis; no parent local values.
            'orientation_direction' => $snapshot?->orientation_direction ?? 'negative',
            'attestation_cadence_direction' => $snapshot?->attestation_cadence_direction ?? 'positive',
            'intervals' => '[]', 'last_position' => null, 'last_attestation_id' => null, 'act_count' => 0];
    }
}
