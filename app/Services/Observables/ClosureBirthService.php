<?php

namespace App\Services\Observables;

use App\Enums\ObservableDirection;
use App\Exceptions\ClosureHistoryUnavailable;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Facades\DB;

/** Durable historical observable, independent of disposable measurement projections. */
class ClosureBirthService
{
    public const VERSION = 'closure-birth-v2-comparative-history';

    public function __construct(private readonly CommittalLineageService $lineage) {}

    /** Called only by Proposition's created event, inside the contribution transaction. */
    public function record(Proposition $position): void
    {
        $observation = $this->prospective($position);
        DB::table('closure_births')->insert(['proposition_id' => $position->id,
            'version' => self::VERSION, 'observation' => json_encode($observation, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)]);
    }

    public function stored(Proposition $position, ?ParticipationContext $context = null): array
    {
        $load = fn () => DB::table('closure_births')->where('proposition_id', $position->id)->first();
        $row = $context === null || ! $context->propositions->has($position->id) ? $load()
            : $context->remember('closure:births', fn () => DB::table('closure_births')
                ->whereIn('proposition_id', $context->propositions->keys())->get()->keyBy('proposition_id'))->get($position->id);
        if ($row === null || $row->version !== self::VERSION) {
            throw new ClosureHistoryUnavailable('Closure birth state is missing or obsolete; current population cannot reconstruct it.');
        }

        return json_decode($row->observation, true, flags: JSON_THROW_ON_ERROR);
    }

    /** Read-only next-child observation. Expressive births never reserve committed slots. */
    public function prospective(Proposition $position): array
    {
        $ancestors = $this->lineage->ancestors($position);
        if ($ancestors->isEmpty() && $position->phase->cycle->contextHorizon->parent_context_horizon_id !== null) {
            throw new \DomainException('Only Origin may have no entry Committal.');
        }
        $domain = $position->phase->cycle->contextHorizon->domain->value;
        $depths = ['emergence' => 0, 'settlement' => 0];
        $intervals = ['emergence' => [], 'settlement' => []];
        $laterals = [];
        $previous = null;
        $births = DB::table('closure_births')->whereIn('proposition_id', $ancestors->pluck('proposition_id'))->get()->keyBy('proposition_id');
        foreach ($ancestors as $record) {
            $birth = $births->get($record->proposition_id);
            if ($birth === null || $birth->version !== self::VERSION) {
                throw new ClosureHistoryUnavailable('Committed ancestry has no reconstructable Closure birth observation.');
            }
            $history = json_decode($birth->observation, true, flags: JSON_THROW_ON_ERROR);
            $previous ??= $history;
            $depths[$record->creating_domain]++;
            // Intervals belong to their creating Domain, never direction_after.
            $interval = $record->cadence_interval ?? $history['cadence_interval'];
            if ($interval === null || $interval < 1) {
                throw new ClosureHistoryUnavailable('A committed Cadence interval is unavailable.');
            }
            array_unshift($intervals[$record->creating_domain], $interval);
            if ($record->creating_domain === $domain) {
                $laterals[] = $history['committed_lateral_position'];
            }
        }
        $depths[$domain]++;
        $interval = $ancestors->isEmpty() ? 1 : $this->lineage->prospectiveInterval($position);
        $intervals[$domain][] = $interval;
        $other = $domain === 'emergence' ? 'settlement' : 'emergence';
        $origin = $ancestors->isEmpty();
        $lateral = $origin ? 1 : $this->lateralPosition($position, $ancestors->last()->proposition_id);
        $cvs = array_map(fn ($samples) => count($samples) < 2 ? null : ClosureCalculator::coefficientOfVariation($samples), $intervals);
        $mature = $cvs[$domain] !== null && $cvs[$other] !== null;
        $centroid = ! $mature || count($laterals) < 2 ? null : array_sum($laterals) / count($laterals);
        // Origin = -1. Lateral history activates only with comparative Cadence maturity.
        $lateralValue = $origin ? -1.0 : ($centroid === null ? 0.0 : ClosureCalculator::positionalSimilarity($lateral, $centroid));
        $depth = $origin ? -1.0 : ($ancestors->count() === 1 || $depths[$other] === 0 ? 0.0 : ClosureCalculator::similarity($depths[$domain], $depths[$other]));
        $cadence = $origin ? -1.0 : (! $mature ? 0.0 : ClosureCalculator::variabilitySimilarity($cvs[$domain], $cvs[$other]));
        $cardinal = round(($depth + $lateralValue) / 2, 7);
        $values = ['depth' => $depth, 'lateral_committal_position' => $lateralValue,
            'committed_cardinal_position' => $cardinal, 'cadence' => $cadence,
            'closure' => round(($cardinal + $cadence) / 2, 7)];
        foreach ($values as $name => $value) {
            $values[$name.'_direction'] = ObservableDirection::after($value,
                ObservableDirection::from($previous[$name.'_direction'] ?? 'negative'))->value;
        }

        return [...$values,
            'admissible' => $values['committed_cardinal_position_direction'] === $values['cadence_direction'],
            'creating_domain' => $domain, 'cadence_interval' => $interval, 'committed_lateral_position' => $lateral,
            'historical_lateral_centroid' => $centroid, 'domain_depths' => $depths,
            'domain_intervals' => $intervals, 'domain_cvs' => $cvs,
            'history_proposition_id' => $ancestors->first()?->proposition_id,
            'history_source' => $origin ? 'negative-default' : 'committed-ancestor'];
    }

    /** Breadth-first committed-only rows. Existing siblings occupy gaps; ancestry supplies samples. */
    private function lateralPosition(Proposition $position, string $origin): int
    {
        $nodes = DB::table('commitment_snapshots as s')
            ->join('propositions as p', 'p.id', '=', 's.proposition_id')
            ->join('phases as ph', 'ph.id', '=', 'p.phase_id')
            ->join('cycles as c', 'c.id', '=', 'ph.cycle_id')
            ->join('substrate_ledger_entries as l', 'l.id', '=', 's.ledger_entry_id')
            ->where('s.substrate_id', $position->phase->cycle->participant->substrate_id)
            ->orderBy('l.sequence')->get(['p.id', 'c.entry_committal_id']);
        $children = [];
        foreach ($nodes as $node) {
            $children[$node->entry_committal_id ?? ''][] = $node->id;
        }
        $candidate = '@birth';
        $children[$position->phase->cycle->entry_committal_id][] = $candidate;
        $row = [$origin];
        $seen = [];
        while ($row !== []) {
            $next = [];
            foreach ($row as $parent) {
                if (isset($seen[$parent])) {
                    throw new \DomainException('Committed morphology must be acyclic.');
                }
                $seen[$parent] = true;
                foreach ($children[$parent] ?? [] as $child) {
                    $next[] = $child;
                    if ($child === $candidate) {
                        return count($next);
                    }
                }
            }
            $row = $next;
        }
        throw new ClosureHistoryUnavailable('The committed-position observation has no path from Origin.');
    }
}
