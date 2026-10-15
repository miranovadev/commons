<?php

namespace App\Services;

use App\Domain\Observables\ObservableValues;
use App\Enums\ObservableDirection;
use App\Exceptions\PotentialProjectionUnavailable;
use App\Models\ContextHorizon;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use App\Models\Substrate;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\ObservableService;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Facades\DB;

/** Disposable terrain, published atomically against the ledger's field version. */
final class PotentialProjectionService
{
    public const VERSION = 'potential-regions-v4-canonical-metadata';

    public function __construct(private PotentialRegionCalculator $calculator) {}

    public function assertCurrent(string $substrateId): int
    {
        $state = DB::table('substrates as substrate')->leftJoin('potential_projection_states as projection',
            'projection.substrate_id', '=', 'substrate.id')->where('substrate.id', $substrateId)
            ->first(['substrate.field_version as current_version', 'projection.field_version', 'projection.calculator_version']);
        if ($state === null || $state->field_version === null || (int) $state->field_version !== (int) $state->current_version
            || $state->calculator_version !== self::VERSION.':'.LineageMeasurementService::VERSION) {
            throw new PotentialProjectionUnavailable($substrateId);
        }

        return (int) $state->field_version;
    }

    public function region(string $id): array
    {
        $row = DB::table('potential_region_states')->where('context_horizon_id', $id)->first();
        if ($row === null) {
            $horizon = ContextHorizon::findOrFail($id);
            throw new PotentialProjectionUnavailable($horizon->substrate_id);
        }

        $local = json_decode($row->local_state, true, flags: JSON_THROW_ON_ERROR);
        // v2 projections stored only edge endpoints. Enrich that disposable
        // legacy shape on read so an already-live same-Horizon Commit becomes
        // a recovery frontier immediately, before the next normal projection.
        if (collect($local['edges'])->contains(fn (array $edge): bool => ! isset($edge[2]))) {
            $types = DB::table('supersessions')->whereIn('target_proposition_id',
                collect($local['edges'])->pluck(1)->unique())->get(['source_proposition_id', 'target_proposition_id', 'type'])
                ->keyBy(fn ($edge) => $edge->source_proposition_id.'|'.$edge->target_proposition_id);
            $local['edges'] = array_map(function (array $edge) use ($types): array {
                $type = $types->get($edge[0].'|'.$edge[1])?->type;

                return [$edge[0], $edge[1], $type];
            }, $local['edges']);
        }

        return ['local' => $local,
            'frontier' => json_decode($row->frontier, true, flags: JSON_THROW_ON_ERROR),
            'potential' => (float) $row->remaining_potential, 'version' => (int) $row->field_version];
    }

    /** Existing substrates are never backfilled as a side effect of participation. */
    public function refresh(string $substrateId): bool
    {
        if (! DB::table('potential_projection_states')->where('substrate_id', $substrateId)->exists()) {
            return false;
        }

        return $this->maintain($substrateId, false, true);
    }

    /** Explicit maintenance only. Tests may supply controlled, already-projected measurements. */
    public function rebuild(string $substrateId, bool $recalculateMeasurements = true): bool
    {
        return $this->maintain($substrateId, true, $recalculateMeasurements);
    }

    private function maintain(string $substrateId, bool $all, bool $recalculate): bool
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $version = (int) Substrate::whereKey($substrateId)->value('field_version');
            $ids = $all ? ContextHorizon::where('substrate_id', $substrateId)->pluck('id')
                : DB::table('potential_dirty_horizons')->where('substrate_id', $substrateId)->pluck('context_horizon_id');
            if ($ids->isEmpty() && ! $all) {
                // A version gap without an impact record is an integrity failure,
                // never permission to declare an old projection current.
                try {
                    $this->assertCurrent($substrateId);

                    return true;
                } catch (PotentialProjectionUnavailable) {
                    return false;
                }
            }
            $prepared = [];
            foreach (ContextHorizon::whereKey($ids)->orderByDesc('sequence')->get() as $horizon) {
                $prepared[$horizon->id] = $this->derive($horizon, $recalculate && ($all || (bool) DB::table('potential_dirty_horizons')->where('context_horizon_id', $horizon->id)->value('measurements_dirty')));
            }
            // Compute ancestor summaries OUTSIDE the write lock. An unchanged
            // complete frontier ends propagation, even if local evidence changed.
            $queue = array_keys($prepared);
            $summaries = [];
            while ($queue !== []) {
                $id = array_shift($queue);
                $old = DB::table('potential_region_states')->where('context_horizon_id', $id)->first();
                $local = $prepared[$id]['local'] ?? ($old === null ? null : json_decode($old->local_state, true));
                if ($local === null) {
                    // A missing ancestor requires explicit backfill.
                    return false;
                }
                $children = [];
                foreach (ContextHorizon::where('parent_context_horizon_id', $id)->get(['id', 'opened_by_proposition_id']) as $child) {
                    $frontier = $summaries[$child->id]['frontier'] ?? null;
                    if ($frontier === null) {
                        $stored = DB::table('potential_region_states')->where('context_horizon_id', $child->id)->value('frontier');
                        if ($stored === null) {
                            throw new PotentialProjectionUnavailable($substrateId);
                        }
                        $frontier = json_decode($stored, true);
                    }
                    $children[] = ['opener' => $child->opened_by_proposition_id, 'frontier' => $frontier];
                }
                $frontier = $this->calculator->frontier($local, $children);
                $previous = $summaries[$id]['frontier'] ?? ($old === null ? null : json_decode($old->frontier, true));
                if ($frontier === $previous && ! isset($prepared[$id])) {
                    continue;
                }
                $summaries[$id] = ['local' => $local, 'frontier' => $frontier];
                if ($frontier !== $previous && $local['parent'] !== null) {
                    $queue[] = $local['parent'];
                }
            }
            foreach ($summaries as &$summary) {
                $best = collect($summary['frontier'])->sortByDesc('raw_potential_charge')->first();
                $summary['best'] = $best;
                $summary['local_json'] = json_encode($summary['local'], JSON_THROW_ON_ERROR);
                $summary['frontier_json'] = json_encode($summary['frontier'], JSON_THROW_ON_ERROR);
            }
            unset($summary);
            $published = DB::transaction(function () use ($substrateId, $version, $prepared, $summaries, $ids): bool {
                $root = Substrate::whereKey($substrateId)->lockForUpdate()->firstOrFail();
                if ((int) $root->field_version !== $version) {
                    return false;
                }
                DB::table('potential_entries')->whereIn('context_horizon_id', array_keys($prepared))->delete();
                foreach ($prepared as $id => $region) {
                    if ($region['measurements'] !== []) {
                        foreach (array_chunk($region['measurements'], 500) as $measurements) {
                            DB::table('proposition_measurements')->upsert($measurements, ['proposition_id'],
                                ['correspondence', 'chirality', 'closure', 'calculated_at', 'calculator_version',
                                    'chirality_direction', 'orientation', 'attestation_cadence', 'orientation_direction', 'attestation_cadence_direction',
                                    'shape_topology', 'phase_profile', 'shape_topology_direction', 'phase_profile_direction']);
                        }
                        foreach ($region['directions'] as $direction => $nodes) {
                            DB::table('propositions')->whereIn('id', $nodes)->update(['correspondence_direction' => $direction]);
                        }
                    }
                    DB::table('potential_region_dependencies')->where('context_horizon_id', $id)->delete();
                    foreach (array_chunk($region['dependencies'], 500) as $dependencies) {
                        DB::table('potential_region_dependencies')->insert($dependencies);
                    }
                    foreach (array_chunk($region['entries'], 500) as $entries) {
                        DB::table('potential_entries')->insert($entries);
                    }
                    DB::table('potential_branch_regions')->where('context_horizon_id', $id)->delete();
                    foreach (array_chunk($region['branches'], 500) as $branches) {
                        DB::table('potential_branch_regions')->insert($branches);
                    }
                }
                foreach ($summaries as $id => $summary) {
                    $best = $summary['best'];
                    DB::table('potential_region_states')->updateOrInsert(['context_horizon_id' => $id], [
                        'substrate_id' => $substrateId, 'parent_context_horizon_id' => $summary['local']['parent'],
                        'best_recovery_proposition_id' => $best['proposition_id'] ?? null,
                        'remaining_potential' => $best['raw_potential_charge'] ?? 0,
                        'field_version' => $version, 'local_state' => $summary['local_json'],
                        'frontier' => $summary['frontier_json'],
                    ]);
                }
                DB::table('potential_dirty_horizons')->whereIn('context_horizon_id', $ids)->delete();
                DB::table('potential_projection_states')->updateOrInsert(['substrate_id' => $substrateId], [
                    'field_version' => $version, 'calculator_version' => self::VERSION.':'.LineageMeasurementService::VERSION,
                    'projected_at' => now(),
                ]);

                return true;
            });
            if ($published) {
                app(RealtimePublisher::class)->graphMetrics($substrateId);

                return true;
            }
        }

        return false;
    }

    private function derive(ContextHorizon $horizon, bool $recalculate): array
    {
        $owned = Proposition::whereHas('phase.cycle', fn ($q) => $q->where('context_horizon_id', $horizon->id))
            ->with(['measurement', 'commitmentSnapshot', 'phase.cycle.participant'])->get()->keyBy('id');
        $nodes = clone $owned;
        if ($horizon->opened_by_proposition_id !== null) {
            $boundary = Proposition::with(['measurement', 'commitmentSnapshot', 'phase.cycle.participant'])->findOrFail($horizon->opened_by_proposition_id);
            $nodes->put($boundary->id, $boundary);
        }
        $active = Proposition::active()->whereKey($nodes->keys())->pluck('id')->flip();
        $phase = Phase::whereHas('cycle', fn ($q) => $q->where('context_horizon_id', $horizon->id))->latest('id')->first();
        // Projection retains historical geometry after a fresh foreign-source boundary
        // retires its Region. Operational working sets remain active-only.
        $context = $phase === null ? null : new ParticipationContext($phase, $owned->keys(), includeUnavailableAdditional: true);
        $measurements = $directions = [];
        if ($recalculate && $context !== null) {
            foreach ($owned as $node) {
                if (! $active->has($node->id) || ! $context->memberships->has($node->id)) {
                    continue;
                }
                $snapshot = $node->commitmentSnapshot;
                $components = $snapshot === null ? app(ObservableService::class)->components($context->proposition($node), $context) : null;
                $values = $snapshot ?? $components->observables();
                $diagnostics = $snapshot !== null ? $snapshot->only(['chirality_direction', 'orientation', 'attestation_cadence', 'orientation_direction', 'attestation_cadence_direction',
                    'shape_topology', 'phase_profile', 'shape_topology_direction', 'phase_profile_direction'])
                    : [...app(PropositionMeasurementService::class)->chiralityAttributes($context->proposition($node), $values->chirality, $components->chiralityComponents, $context),
                        ...app(PropositionMeasurementService::class)->correspondenceAttributes($context->proposition($node), $components->correspondenceComponents, $context)];
                $row = [...$diagnostics, 'proposition_id' => $node->id, 'correspondence' => $values->correspondence,
                    'chirality' => $values->chirality, 'closure' => $values->closure,
                    'calculated_at' => now(), 'calculator_version' => LineageMeasurementService::VERSION];
                $measurements[] = $row;
                $measurement = new PropositionMeasurement;
                $measurement->forceFill($row);
                $node->setRelation('measurement', $measurement);
                if ($values->correspondence !== null && $values->correspondence != 0) {
                    $directions[ObservableDirection::after($values->correspondence)->value][] = $node->id;
                }
            }
        }
        $memberships = DB::table('origin_branch_memberships')->whereIn('proposition_id', $nodes->keys())->get()->groupBy('proposition_id');
        $openingIds = ContextHorizon::where('parent_context_horizon_id', $horizon->id)->pluck('opened_by_proposition_id')->flip();
        $local = ['id' => $horizon->id, 'parent' => $horizon->parent_context_horizon_id,
            'opener' => $horizon->opened_by_proposition_id, 'domain' => $horizon->domain->value,
            'nodes' => [], 'entries' => [], 'edges' => [], 'foreign_inputs' => []];
        foreach ($nodes as $id => $node) {
            $values = $node->commitmentSnapshot ?? $node->measurement;
            $complete = $values !== null && $values->correspondence !== null && $values->chirality !== null && $values->closure !== null
                && ($node->commitmentSnapshot !== null || $values->calculator_version === LineageMeasurementService::VERSION);
            $tension = $complete ? ($node->commitmentSnapshot?->tension ?? app(ProjectionService::class)->field(
                new ObservableValues($values->correspondence, $values->chirality, $values->closure))['tension']) : null;
            $local['nodes'][$id] = ['tier' => $node->tier, 'active' => $active->has($id), 'complete' => $complete,
                'tension' => $tension, 'roots' => $memberships->get($id, collect())->pluck('root_proposition_id')->all(),
                'origin' => $node->commitmentSnapshot?->calculator_version === 'origin-bootstrap-v1',
                'seed_id' => $node->tier === 'committal' ? $id : ($context?->seed($node)?->id)];
            if ($active->has($id) && $node->tier === 'committal' && $node->commitmentSnapshot !== null && ! $openingIds->has($id)) {
                $local['entries'][$id] = ['tension' => $node->commitmentSnapshot->tension];
            }
        }
        // Historical geometry is retained for distances from exhausted positions;
        // active-only nodes above independently constrain numeric Potential.
        $edges = DB::table('supersessions')->whereIn('target_proposition_id', $owned->keys())
            ->get(['phase_id', 'source_proposition_id', 'target_proposition_id', 'type']);
        foreach ($edges as $edge) {
            if (! $nodes->has($edge->source_proposition_id)) {
                $target = $nodes->get($edge->target_proposition_id);
                $gate = $target?->phase->gate_proposition_id;
                if ($target?->tier === 'expressive' && ($local['nodes'][$target->id]['seed_id'] ?? null) !== null
                    && $nodes->has($gate) && $context?->propositions->has($edge->source_proposition_id)
                    && ($edges->contains(fn ($input) => $input->target_proposition_id === $target->id
                        && $input->source_proposition_id === $gate)
                        || app(RecordedBiasSourceService::class)->recordedSourceFor($target,
                            $edges->where('target_proposition_id', $target->id), $context)?->id === $edge->source_proposition_id)) {
                    // Recorded Bias classifications have one foreign source, with
                    // no invented local-parent edge. Keep it outside local terrain.
                    $local['foreign_inputs'][] = [$edge->source_proposition_id, $edge->target_proposition_id, $edge->type];

                    continue;
                }
                throw new \DomainException('Potential projection requires causal Horizon edges. Run commons:causal-audit before rebuilding.');
            }
            $local['edges'][] = [$edge->source_proposition_id, $edge->target_proposition_id, $edge->type];
        }
        $branches = [];
        $roots = $memberships->flatten(1)->pluck('root_proposition_id')->unique();
        foreach (DB::table('origin_branches')->whereIn('root_proposition_id', $roots)->get() as $branch) {
            $candidates = $this->calculator->candidates($local, branch: $branch->root_proposition_id);
            $branchNodes = collect($local['nodes'])->filter(fn ($node) => $node['active'] && $this->calculator->inBranch($node, $branch->root_proposition_id));
            $branches[] = ['root_proposition_id' => $branch->root_proposition_id, 'context_horizon_id' => $horizon->id,
                'remaining_potential' => collect($candidates)->max('raw_potential_charge') ?? 0,
                'complete' => ! $branchNodes->contains(fn ($node) => ! $node['complete']),
                'candidate_count' => count($candidates),
                // An opener is already counted in its creating region.
                'proposition_count' => $branchNodes->only($owned->keys()->all())->where('origin', false)->count()];
        }

        $dependencies = $context?->propositions->keys()->merge($nodes->keys())->unique()->all() ?? $nodes->keys()->all();

        return ['local' => $local, 'entries' => array_map(fn ($entry) => ['substrate_id' => $horizon->substrate_id, ...$entry], $this->calculator->candidates($local)),
            'branches' => $branches, 'measurements' => $measurements, 'directions' => $directions,
            'dependencies' => array_map(fn ($id) => ['context_horizon_id' => $horizon->id, 'proposition_id' => $id], $dependencies)];
    }
}
