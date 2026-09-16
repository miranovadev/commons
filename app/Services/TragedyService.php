<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Substrate;
use App\Models\Tragedy;
use Illuminate\Support\Facades\DB;

/** Exhaustion is an optimistic observation; retirement is a small guarded write. */
final readonly class TragedyService
{
    public function inspect(Phase $phase): array
    {
        $substrateId = $phase->cycle->participant->substrate_id;
        $projection = app(PotentialProjectionService::class);
        $version = (int) Substrate::whereKey($substrateId)->value('field_version');
        $branches = DB::table('origin_branches')->where('substrate_id', $substrateId)
            ->whereIn('root_proposition_id', DB::table('origin_branch_memberships')
                ->where('proposition_id', $phase->gate_proposition_id)->select('root_proposition_id'))->get();
        $proof = ['substrate_id' => $substrateId, 'field_version' => $version,
            'phase_id' => $phase->id, 'position_id' => $phase->gate_proposition_id,
            'branches' => [], 'pruned_roots' => $branches->whereNotNull('pruned_at')->pluck('root_proposition_id')->all()];
        if ($branches->whereNull('pruned_at')->isNotEmpty()) {
            $projection->assertCurrent($substrateId);
        }
        $calculator = app(PotentialRegionCalculator::class);
        $locals = [];
        $localFor = function (string $id) use ($projection, &$locals): array {
            return $locals[$id] ??= $projection->region($id)['local'];
        };
        foreach ($branches->whereNull('pruned_at') as $branch) {
            $maturity = app(BranchCommitmentService::class)->inspect($branch->root_proposition_id,
                $branch->origin_proposition_id, $phase->gate_proposition_id, $substrateId);
            $evidence = ['root_proposition_id' => $branch->root_proposition_id,
                'origin_proposition_id' => $branch->origin_proposition_id,
                'current_terminal' => $phase->gate_proposition_id, ...$maturity,
                'earliest_commit_id' => count($maturity['earliest_commits']) === 1
                    ? $maturity['earliest_commits'][0]['proposition_id'] : null,
                'earliest_commit_potential' => null, 'exhausted' => false, 'would_tragedy' => false,
                'reason' => 'immature_branch', 'regions' => [], 'opened_at' => $branch->opened_at];
            if (! $maturity['valid_root'] || ! $maturity['has_commit'] || $maturity['earliest_commits'] === []) {
                $evidence['reason'] = $maturity['valid_root'] ? 'immature_branch' : 'invalid_branch_root';
                $proof['branches'][] = $evidence;

                continue;
            }
            $regions = DB::table('potential_branch_regions')->where('root_proposition_id', $branch->root_proposition_id)
                ->get()->keyBy('context_horizon_id');
            // Override only the terminal, Origin-root and earliest-Commit basins.
            // Origin is generative, so its pre-Commit Potential is not a veto.
            // All remaining basins retain their maintained conservative summaries.
            $inspectIds = array_values(array_unique([$phase->cycle->context_horizon_id,
                $maturity['root_context_horizon_id'], ...array_column($maturity['earliest_commits'], 'context_horizon_id')]));
            $other = $regions->except($inspectIds);
            $viable = $other->contains(fn ($region) => $region->remaining_potential > 0);
            $unknown = $other->contains(fn ($region) => ! $region->complete);
            foreach (array_unique([...$inspectIds, ...array_column($maturity['committed_regions'], 'context_horizon_id')]) as $id) {
                $unknown = $unknown || ! $regions->has($id);
            }
            $observed = [];
            foreach ($inspectIds as $id) {
                $local = $localFor($id);
                // An exhausted expressive path is excluded just as in recovery.
                // A terminal Commit must still be measured, never disappear from
                // the proof merely because it is the participant's position.
                $excluded = $id === $phase->cycle->context_horizon_id && ! $maturity['terminal_is_committal']
                    ? $phase->gate_proposition_id : null;
                $candidates = array_filter($calculator->candidates($local, $excluded, $branch->root_proposition_id),
                    fn ($candidate) => ! $local['nodes'][$candidate['proposition_id']]['origin']);
                $unknown = $unknown || collect($local['nodes'])->contains(fn ($node) => $node['active']
                    && $calculator->inBranch($node, $branch->root_proposition_id) && ! $node['complete']);
                foreach ($candidates as $candidate) {
                    $observed[$id][$candidate['proposition_id']] = $candidate;
                    $viable = $viable || $candidate['raw_potential_charge'] > 0;
                    $unknown = $unknown || ! $candidate['complete'];
                    $evidence['regions'][] = ['proposition_id' => $candidate['proposition_id'],
                        'context_horizon_id' => $id, 'value' => $candidate['raw_potential_charge'],
                        'status' => $candidate['complete'] ? 'current' : 'missing'];
                }
            }
            $checkpoints = [];
            foreach ($maturity['earliest_commits'] as $commit) {
                $entry = $observed[$commit['context_horizon_id']][$commit['proposition_id']] ?? null;
                $unknown = $unknown || $entry === null || ! $entry['complete'];
                $checkpoints[] = [...$commit, 'remaining_potential' => $entry['raw_potential_charge'] ?? null];
            }
            $exhausted = ! $unknown && ! $viable;
            $proof['branches'][] = [...$evidence, 'earliest_commits' => $checkpoints,
                'earliest_commit_potential' => count($checkpoints) === 1 ? $checkpoints[0]['remaining_potential'] : null,
                'exhausted' => $exhausted, 'would_tragedy' => $exhausted,
                'reason' => $unknown ? 'incomplete_projection' : ($viable ? 'viable_ancestor' : 'earliest_commit_exhausted'),
                'other_region_viable_or_unknown' => $other->contains(fn ($region) => $region->remaining_potential > 0 || ! $region->complete),
                'proposition_count' => (int) $regions->sum('proposition_count')];
        }
        $proof['stable'] = $version === (int) Substrate::whereKey($substrateId)->value('field_version');

        return $proof;
    }

    /** Final guarded write; normal callers already own the Substrate root. */
    public function retire(Phase $phase, array $proof): void
    {
        DB::transaction(function () use ($phase, $proof): void {
            $substrate = Substrate::whereKey($proof['substrate_id'])->lockForUpdate()->firstOrFail();
            if (! $proof['stable'] || $proof['phase_id'] !== $phase->id || $proof['position_id'] !== $phase->gate_proposition_id
                || $proof['substrate_id'] !== $phase->cycle->participant->substrate_id
                || $phase->cycle->fresh()->rested_at === null
                || $proof['field_version'] !== (int) $substrate->field_version) {
                return;
            }
            foreach ($proof['branches'] as $branch) {
                if (! $branch['exhausted'] || ! ($branch['has_commit'] ?? false)
                    || ! ($branch['valid_root'] ?? false) || ($branch['reason'] ?? null) !== 'earliest_commit_exhausted') {
                    continue;
                }
                $changed = DB::table('origin_branches')->where('substrate_id', $proof['substrate_id'])
                    ->where('root_proposition_id', $branch['root_proposition_id'])->whereNull('pruned_at')->update(['pruned_at' => now()]);
                if (! $changed) {
                    continue;
                }
                (new Tragedy)->forceFill([
                    'root_proposition_id' => $branch['root_proposition_id'], 'substrate_id' => $proof['substrate_id'],
                    'participant_id' => $phase->cycle->participant_id, 'phase_id' => $phase->id,
                    'terminal_proposition_id' => $phase->gate_proposition_id,
                    'observed_field_version' => $proof['field_version'], 'occurred_at' => now(), 'evidence' => $branch,
                ])->save();
                $affected = DB::table('potential_branch_regions')->where('root_proposition_id', $branch['root_proposition_id'])
                    ->pluck('context_horizon_id')->all();
                app(FieldMutationService::class)->mark($proof['substrate_id'], $affected, measurementsDirty: false);
                app(RealtimePublisher::class)->horizons($proof['substrate_id']);
            }
        });
    }
}
