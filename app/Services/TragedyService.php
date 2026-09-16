<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Tragedy;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Facades\DB;

/** Exhaustion is an optimistic observation; retirement is a small guarded write. */
final readonly class TragedyService
{
    public function __construct(private PotentialChargeService $potential, private PropositionMeasurementService $measurements) {}

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
        $local = $branches->whereNull('pruned_at')->isEmpty() ? null
            : $projection->region($phase->cycle->context_horizon_id)['local'];
        $calculator = app(PotentialRegionCalculator::class);
        foreach ($branches->whereNull('pruned_at') as $branch) {
            $regions = DB::table('potential_branch_regions')->where('root_proposition_id', $branch->root_proposition_id);
            // Only the observer's own region can change when its exhausted path
            // is excluded. Any other viable/unknown region vetoes retirement.
            $otherViable = (clone $regions)->where('context_horizon_id', '!=', $local['id'])
                ->where(fn ($q) => $q->where('remaining_potential', '>', 0)->orWhere('complete', false))->exists();
            $candidates = $calculator->candidates($local, $phase->gate_proposition_id, $branch->root_proposition_id);
            $unknown = collect($local['nodes'])->contains(fn ($node) => $node['active']
                && $calculator->inBranch($node, $branch->root_proposition_id) && ! $node['complete']);
            $stats = (clone $regions)->selectRaw('sum(candidate_count) as candidates, sum(proposition_count) as propositions')->first();
            $exhausted = ! $otherViable && ! $unknown && (int) $stats->candidates > 0
                && ! collect($candidates)->contains(fn ($candidate) => $candidate['raw_potential_charge'] > 0 || ! $candidate['complete']);
            $proof['branches'][] = ['root_proposition_id' => $branch->root_proposition_id,
                'origin_proposition_id' => $branch->origin_proposition_id, 'exhausted' => $exhausted,
                'regions' => array_map(fn ($candidate) => ['proposition_id' => $candidate['proposition_id'],
                    'context_horizon_id' => $local['id'], 'value' => $candidate['raw_potential_charge'],
                    'status' => $candidate['complete'] ? 'current' : 'missing'], $candidates),
                'other_region_viable_or_unknown' => $otherViable,
                'proposition_count' => (int) $stats->propositions, 'opened_at' => $branch->opened_at];
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
                if (! $branch['exhausted']) {
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
