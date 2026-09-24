<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** Versioned, Horizon-local failure witnesses. No inference about ungenerated possibility. */
final class HorizonFailureService
{
    public function inspect(Phase $phase): array
    {
        $substrateId = $phase->cycle->participant->substrate_id;
        $version = app(PotentialProjectionService::class)->assertCurrent($substrateId);
        $plan = app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase);
        $exhaustedContinuations = app(ExhaustedContinuationService::class)->ids($phase);
        $proof = ['substrate_id' => $substrateId, 'context_horizon_id' => $phase->cycle->context_horizon_id,
            'field_version' => $version, 'phase_id' => $phase->id, 'participant_id' => $phase->cycle->participant_id,
            'position_id' => $phase->gate_proposition_id, 'governing_committal_id' => app(ExhaustionService::class)->regionFor($phase)?->id, 'rest_reason' => $plan['rest_reason'] ?? 'no_available_move',
            'transitions' => [], 'failures' => [], 'horizons' => [], 'branches' => [], 'reason' => 'no_available_move'];
        if (isset($plan['schema']['$defs']['actions']['participant.rest'])
            && $this->testedDeadEnd($phase)) {
            $proof['reason'] = $proof['rest_reason'] === 'no_remaining_potential' ? 'no_remaining_potential' : 'continuation_failed';
            $pending = [$phase->gate_proposition_id];
            $closed = [];
            while ($pending !== []) {
                $id = array_shift($pending);
                if (isset($closed[$id])) {
                    continue;
                }
                $node = Proposition::with('phase.cycle')->findOrFail($id);
                // Failed expressive opportunities can reopen generation locally.
                // A Committal is a hard boundary, never recursively closed here.
                if ($node->tier === 'committal' || $exhaustedContinuations->has($node->id)
                    || $node->phase->cycle->context_horizon_id !== $proof['context_horizon_id']) {
                    continue;
                }
                // A failed descendant cannot condemn an untested generative source.
                if (in_array($node->phase->kind, [PhaseKind::Distinguish, PhaseKind::Disperse], true)
                    && ! $this->exercised($node, instrumentRequired: true)) {
                    continue;
                }
                $children = DB::table('supersessions')->where('source_proposition_id', $id)->pluck('target_proposition_id');
                $epochs = app(ExplorationEpochService::class);
                $failed = $epochs->currentFailures($proof['context_horizon_id'], $children);
                $exhausted = DB::table('commit_region_exhaustions')->whereIn('governing_committal_id', $children)
                    ->pluck('governing_committal_id')->flip();
                if ($children->contains(fn ($child) => ! $failed->has($child) && ! $exhausted->has($child) && ! isset($closed[$child]))) {
                    continue;
                }
                $closed[$id] = true;
                $proof['transitions'][] = $id;
                $position = app(ExplorationPositionService::class)->forProposition($node);
                if ($position === null) {
                    throw new \DomainException('Cannot publish failure without exact exploration provenance.');
                }
                $proof['failures'][] = ['proposition_id' => $id, 'structural_position' => $position['structural_position'],
                    'structural_source_id' => $position['structural_source_id'], 'exploration_epoch' => $epochs->current($position)];
                foreach (DB::table('supersessions')->where('target_proposition_id', $id)->pluck('source_proposition_id') as $parent) {
                    $pending[] = $parent;
                }
            }
        }
        $proof['stable'] = $version === (int) Substrate::whereKey($substrateId)->value('field_version');

        return $proof;
    }

    public function publish(Phase $phase, array $proof): void
    {
        DB::transaction(function () use ($phase, $proof): void {
            $substrate = Substrate::whereKey($phase->cycle->participant->substrate_id)->lockForUpdate()->firstOrFail();
            if (! in_array($proof['reason'], ['no_remaining_potential', 'continuation_failed'], true) || ! $proof['stable'] || $proof['field_version'] !== (int) $substrate->field_version
                || $proof['phase_id'] !== $phase->id || $proof['position_id'] !== $phase->gate_proposition_id
                || ($proof['governing_committal_id'] ?? null) !== app(ExhaustionService::class)->regionFor($phase)?->id
                || $proof['participant_id'] !== $phase->cycle->participant_id
                || $proof['substrate_id'] !== $substrate->id || $proof['context_horizon_id'] !== $phase->cycle->context_horizon_id
                || $phase->fresh()->completed_at === null || $phase->cycle->fresh()->rested_at === null
                || $phase->cycle->fresh()->rest_reason?->value !== $proof['rest_reason']) {
                return;
            }
            // Revalidate the exact per-position epochs as well as the field version.
            foreach ($proof['failures'] as $failure) {
                $position = app(ExplorationPositionService::class)->forProposition(Proposition::findOrFail($failure['proposition_id']));
                if ($position !== ['context_horizon_id' => $proof['context_horizon_id'],
                    'structural_position' => $failure['structural_position'], 'structural_source_id' => $failure['structural_source_id'] ?? null]
                    || app(ExplorationEpochService::class)->current($position) !== $failure['exploration_epoch']) {
                    return;
                }
            }
            if ($proof['reason'] === 'continuation_failed' && $proof['failures'] === []) {
                return;
            }
            if (! DB::table('horizon_failure_witnesses')->insertOrIgnore([
                'phase_id' => $phase->id, 'context_horizon_id' => $proof['context_horizon_id'],
                'observed_field_version' => $proof['field_version'], 'recorded_at' => now(),
            ])) {
                return;
            }
            foreach ($proof['failures'] as $failure) {
                // The immutable subject already determines its source; historical scope is not rewritten.
                if (DB::table('continuation_failures')->insertOrIgnore([...Arr::except($failure, 'structural_source_id'),
                    'context_horizon_id' => $proof['context_horizon_id'], 'phase_id' => $phase->id, 'failed_at' => now()])) {
                    app(RealtimePublisher::class)->proposition($failure['proposition_id'], 'proposition.spent');
                }
            }
            app(FieldMutationService::class)->mark($substrate->id, [$proof['context_horizon_id']], $proof['transitions'], measurementsDirty: false);
            if ($proof['rest_reason'] === 'no_remaining_potential') {
                app(RealtimePublisher::class)->horizons($substrate->id);
                app(RealtimePublisher::class)->graphMetrics($substrate->id);
            }
        });
    }

    /** A later actionless Phase is a failed test only after evidence and judgment. */
    private function testedDeadEnd(Phase $phase): bool
    {
        $gate = $phase->gate;

        if ($gate?->tier !== 'expressive' || $gate->phase->kind->value >= $phase->kind->value
            || app(ExhaustionService::class)->unavailable($gate)) {
            return false;
        }
        if ($phase->kind->value >= PhaseKind::Mediate->value && $this->exercised($gate)) {
            return true;
        }
        // On recovery an earlier position can encounter a continuation already
        // proved dead downstream. Reuse that proof, rather than retrying forever
        // because this return happens before Mediate. An empty Write is no proof.
        $children = DB::table('supersessions')->where('source_proposition_id', $gate->id)->pluck('target_proposition_id');

        return $children->isNotEmpty()
            && app(PropositionStateService::class)->attestations(collect([$gate]))->isNotEmpty()
            && $children->diff(app(ExplorationEpochService::class)->currentFailures($phase->cycle->context_horizon_id, $children)->keys())->isEmpty();
    }

    private function exercised(Proposition $node, bool $instrumentRequired = false): bool
    {
        $state = app(PropositionStateService::class);
        if ($state->attestations(collect([$node]))->isEmpty()) {
            return false;
        }

        return $instrumentRequired
            ? app(HorizonReceiptService::class)->instrumentedIds($node->phase, collect([$node->id]))->isNotEmpty()
            : $state->receipts($node)->isNotEmpty();
    }
}
