<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Tragedy;
use Illuminate\Support\Facades\DB;

/** Exhaustion is an optimistic observation; retirement is a small guarded write. */
final readonly class TragedyService
{
    public function __construct(private PotentialChargeService $potential, private PropositionMeasurementService $measurements) {}

    public function inspect(Phase $phase): array
    {
        $substrateId = $phase->cycle->participant->substrate_id;
        $version = (int) Substrate::whereKey($substrateId)->value('field_version');
        $branches = DB::table('origin_branches')->where('substrate_id', $substrateId)
            ->whereIn('root_proposition_id', DB::table('origin_branch_memberships')
                ->where('proposition_id', $phase->gate_proposition_id)->select('root_proposition_id'))->get();
        $proof = ['substrate_id' => $substrateId, 'field_version' => $version,
            'phase_id' => $phase->id, 'position_id' => $phase->gate_proposition_id,
            'branches' => [], 'pruned_roots' => $branches->whereNotNull('pruned_at')->pluck('root_proposition_id')->all()];
        foreach ($branches->whereNull('pruned_at') as $branch) {
            $nodes = Proposition::active()->whereIn('id', DB::table('origin_branch_memberships')
                ->where('root_proposition_id', $branch->root_proposition_id)->select('proposition_id'))
                ->with(['measurement', 'commitmentSnapshot', 'phase.cycle'])->get()->keyBy('id');
            $origin = Proposition::with(['measurement', 'commitmentSnapshot', 'phase.cycle'])->findOrFail($branch->origin_proposition_id);
            $nodes->put($origin->id, $origin);
            $edges = DB::table('supersessions')->whereIn('source_proposition_id', $nodes->keys())
                ->whereIn('target_proposition_id', $nodes->keys())->get(['source_proposition_id', 'target_proposition_id'])
                ->groupBy('source_proposition_id');
            $this->measurements->observeMissing($nodes);
            $regions = [];
            $exhausted = true;
            foreach ($nodes->where('tier', 'committal') as $seed) {
                if ($seed->commitmentSnapshot === null) {
                    $exhausted = false;
                    break;
                }
                $horizon = app(ContextHorizonProjector::class)->forEntry($seed);
                $local = $nodes->filter(fn ($node) => $node->phase->cycle->context_horizon_id === $horizon->id)->keyBy('id');
                $local->put($seed->id, $seed);
                // Complete branch observation: never exclude the resting node or
                // another participant's continuation merely because it is local.
                $observation = $this->potential->observePotential($seed->id, $seed->commitmentSnapshot->tension, $local, $edges);
                $regions[] = ['proposition_id' => $seed->id, 'context_horizon_id' => $horizon->id, ...$observation];
                if ($observation['value'] > 0 || $observation['status'] !== 'current') {
                    $exhausted = false;
                }
            }
            $proof['branches'][] = ['root_proposition_id' => $branch->root_proposition_id,
                'exhausted' => $exhausted && $regions !== [], 'regions' => $regions,
                'proposition_count' => $nodes->count() - 1, 'opened_at' => $branch->opened_at];
        }
        // A concurrent commit invalidates the entire observation, even if the
        // read saw a mixture of its rows. No lock was held during measurement.
        $proof['stable'] = $version === (int) Substrate::whereKey($substrateId)->value('field_version');

        return $proof;
    }

    /** Caller owns Substrate and has compared this proof before writing Rest. */
    public function retire(Phase $phase, array $proof): void
    {
        if (! $proof['stable'] || $proof['phase_id'] !== $phase->id || $proof['position_id'] !== $phase->gate_proposition_id
            || $proof['substrate_id'] !== $phase->cycle->participant->substrate_id
            || $phase->cycle->fresh()->rested_at === null
            || $proof['field_version'] !== (int) Substrate::whereKey($proof['substrate_id'])->value('field_version')) {
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
            Substrate::whereKey($proof['substrate_id'])->increment('field_version');
            app(RealtimePublisher::class)->horizons($proof['substrate_id']);
        }
    }
}
