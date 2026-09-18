<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use Illuminate\Support\Facades\DB;

/** Versioned, Horizon-local failure witnesses. No inference about ungenerated possibility. */
final class HorizonFailureService
{
    public function inspect(Phase $phase): array
    {
        $substrateId = $phase->cycle->participant->substrate_id;
        $version = app(PotentialProjectionService::class)->assertCurrent($substrateId);
        $plan = app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase);
        $proof = ['substrate_id' => $substrateId, 'context_horizon_id' => $phase->cycle->context_horizon_id,
            'field_version' => $version, 'phase_id' => $phase->id, 'participant_id' => $phase->cycle->participant_id,
            'position_id' => $phase->gate_proposition_id, 'rest_reason' => $plan['rest_reason'] ?? 'no_available_move',
            'transitions' => [], 'failures' => [], 'horizons' => [], 'branches' => [], 'reason' => 'no_available_move'];
        if (isset($plan['schema']['$defs']['actions']['participant.rest'])
            && ($proof['rest_reason'] === 'no_remaining_potential' || $this->testedDeadEnd($phase))) {
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
                if ($node->tier === 'committal' || $node->phase->cycle->context_horizon_id !== $proof['context_horizon_id']) {
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
                $exhausted = DB::table('horizon_exhaustions')->whereIn('opening_proposition_id', $children)
                    ->pluck('opening_proposition_id')->flip();
                if ($children->contains(fn ($child) => ! $failed->has($child) && ! $exhausted->has($child) && ! isset($closed[$child]))) {
                    continue;
                }
                $closed[$id] = true;
                $proof['transitions'][] = $id;
                $proof['failures'][] = ['proposition_id' => $id, 'structural_position' => $node->phase->kind->value,
                    'exploration_epoch' => $epochs->current($proof['context_horizon_id'], $node->phase->kind)];
                foreach (DB::table('supersessions')->where('target_proposition_id', $id)->pluck('source_proposition_id') as $parent) {
                    $pending[] = $parent;
                }
            }
        }
        $proof['stable'] = $version === (int) Substrate::whereKey($substrateId)->value('field_version');

        return $proof;
    }

    public function publish(Phase $phase, array $proof, bool $exploration = false): void
    {
        // Stage 1 persists exploration proofs without enabling them through the
        // legacy global exclusion. The eligibility stage activates this path.
        if (! $exploration && $proof['rest_reason'] !== 'no_remaining_potential') {
            return;
        }
        DB::transaction(function () use ($phase, $proof): void {
            $substrate = Substrate::whereKey($phase->cycle->participant->substrate_id)->lockForUpdate()->firstOrFail();
            if (! in_array($proof['reason'], ['no_remaining_potential', 'continuation_failed'], true) || ! $proof['stable'] || $proof['field_version'] !== (int) $substrate->field_version
                || $proof['phase_id'] !== $phase->id || $proof['position_id'] !== $phase->gate_proposition_id
                || $proof['participant_id'] !== $phase->cycle->participant_id
                || $proof['substrate_id'] !== $substrate->id || $proof['context_horizon_id'] !== $phase->cycle->context_horizon_id
                || $phase->fresh()->completed_at === null || $phase->cycle->fresh()->rested_at === null
                || $phase->cycle->fresh()->rest_reason?->value !== $proof['rest_reason']) {
                return;
            }
            // Revalidate the exact per-position epochs as well as the field version.
            foreach ($proof['failures'] as $failure) {
                if (app(ExplorationEpochService::class)->current($proof['context_horizon_id'], PhaseKind::from($failure['structural_position'])) !== $failure['exploration_epoch']) {
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
                DB::table('continuation_failures')->insertOrIgnore([...$failure,
                    'context_horizon_id' => $proof['context_horizon_id'], 'phase_id' => $phase->id, 'failed_at' => now()]);
                app(RealtimePublisher::class)->proposition($failure['proposition_id']);
            }
            if ($proof['rest_reason'] === 'no_remaining_potential') {
                $tragedy = app(TragedyService::class)->record($phase, $proof);
                $exhausted = app(ExhaustionService::class)->afterFailure($phase, $tragedy, $proof);
                $phase->cycle->forceFill(['recovery_proposition_id' => $exhausted ? null : $tragedy->opening_proposition_id])->save();
                if (! $exhausted) {
                    $phase->cycle->participant->forceFill(['current_proposition_id' => $tragedy->opening_proposition_id])->save();
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

        return $phase->kind->value >= PhaseKind::Mediate->value
            && $gate?->tier === 'expressive' && $gate->phase->kind->value < $phase->kind->value
            && ! app(ExhaustionService::class)->unavailable($gate)
            && $this->exercised($gate);
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
