<?php

namespace App\Services\Observables;

use App\Domain\Observables\PhaseProfile;
use App\Exceptions\ClosureHistoryUnavailable;
use App\Models\Cycle;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Facades\DB;

/** Durable authorised state; reads never initialise, repair or remeasure it. */
class ProspectiveClosureService
{
    public const VERSION = 'prospective-closure-v1';

    public function __construct(private readonly ProspectiveClosureCalculator $calculator) {}

    public function stored(Proposition $head, ?ParticipationContext $context = null): array
    {
        $row = $context === null || ! $context->propositions->has($head->id)
            ? DB::table('prospective_closure_states')->where('proposition_id', $head->id)->first()
            : $context->remember('closure:prospective-states', fn () => DB::table('prospective_closure_states')
                ->whereIn('proposition_id', $context->propositions->keys())->get()->keyBy('proposition_id'))->get($head->id);

        return $this->decode($row);
    }

    /** Call once, after the birth's ledger, selected temporal facts and real edges exist. */
    public function initialize(Proposition $head): void
    {
        $this->assertTransaction($head);
        if ($head->ledgerEntry === null) {
            throw new \DomainException('Closure birth requires complete ledger context.');
        }
        if ($head->tier === 'committal') {
            if ($head->phase->cycle->entry_committal_id !== null
                || $head->phase->cycle->contextHorizon->parent_context_horizon_id !== null) {
                throw new \DomainException('Ordinary canonical Closure must preserve its expressive source.');
            }
            $phases = [];
            foreach (PhaseProfile::origin()->values as $index => $interval) {
                $phases['origin:'.$head->id.':'.$index] = ['phase' => $index + 1, 'interval' => $interval,
                    'domain' => 'emergence', 'source' => 'constitutional', 'proposition_id' => $head->id];
            }
            $observation = [...$this->calculator->origin(), 'creating_domain' => 'emergence',
                'domain_depths' => ['emergence' => 1, 'settlement' => 0],
                'domain_intervals' => ['emergence' => [1], 'settlement' => []],
                'cadence_interval' => 1, 'phase_observations' => $phases, 'orientation_observations' => [],
                'history_proposition_id' => null, 'trigger' => ['kind' => 'constitutional_origin', 'id' => $head->id]];
        } else {
            $history = new ProspectiveClosureHistory(collect([$head]));
            $entry = $this->entry($head);
            $observation = $this->measure($head, $history, $entry, $entry,
                ['kind' => 'expressive_birth', 'id' => $head->id]);
        }
        $this->insert($head, $observation, $head->tier === 'committal');
    }

    /** Invoked only by a successful new Cycle save under the Substrate lock. */
    public function entered(Cycle $cycle): void
    {
        if (! $cycle->wasRecentlyCreated || ! $cycle->exists || DB::transactionLevel() === 0) {
            throw new \DomainException('Prospective Closure updates require a genuine transactional Cycle entry.');
        }
        $heads = Proposition::where('tier', 'expressive')->whereHas('phase.cycle',
            fn ($query) => $query->where('entry_committal_id', $cycle->entry_committal_id))
            ->with(['phase.cycle.contextHorizon', 'phase.cycle.participant', 'ledgerEntry'])->get();
        if ($heads->isEmpty()) {
            return;
        }
        $history = new ProspectiveClosureHistory($heads);
        $rows = DB::table('prospective_closure_states')->whereIn('proposition_id', $heads->pluck('id'))
            ->lockForUpdate()->get()->keyBy('proposition_id');
        $entry = $this->entry($heads->first());
        $updates = [];
        foreach ($heads as $head) {
            $previous = $this->decode($rows->get($head->id));
            if ($previous['trigger'] === ['kind' => 'cycle_entry', 'id' => $cycle->id]) {
                continue;
            }
            $observation = $this->measure($head, $history, $entry, $previous,
                ['kind' => 'cycle_entry', 'id' => $cycle->id]);
            $updates[] = ['proposition_id' => $head->id, 'version' => self::VERSION, 'frozen' => false,
                'observation' => $this->encode($observation)];
        }
        if ($updates !== []) {
            DB::table('prospective_closure_states')->upsert($updates, ['proposition_id'], ['observation']);
        }
    }

    public function preserve(Proposition $target, Proposition $source, array $expected): void
    {
        $this->assertTransaction($target);
        if ($target->tier !== 'committal' || $source->tier !== 'expressive'
            || $source->phase->cycle->entry_committal_id !== $target->phase->cycle->entry_committal_id) {
            throw new \DomainException('Canonical Closure requires the selected local expressive source.');
        }
        $state = $this->stored($source);
        if ($state !== $expected) {
            throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException('Closure changed after validation. Retry from fresh state.');
        }
        // Exact copy, including trigger and historical inputs; no new observation.
        $this->insert($target, $state, true);
    }

    private function measure(Proposition $head, ProspectiveClosureHistory $history, array $entry, array $previous, array $trigger): array
    {
        $domain = $head->phase->cycle->contextHorizon->domain->value;
        $depths = $entry['domain_depths'];
        $depths[$domain]++;
        $intervals = $entry['domain_intervals'];
        $interval = $history->interval($head);
        // Preserve existing prospective committed-interval ownership/counting.
        $intervals[$domain][] = $interval;
        $phases = $entry['phase_observations'] + $history->phases($head);
        $orientations = $entry['orientation_observations'] + $history->orientations($head);
        $phaseGroups = $orientationGroups = ['emergence' => [], 'settlement' => []];
        foreach ($phases as $row) {
            $phaseGroups[$row['domain']][] = $row;
        }
        foreach ($orientations as $row) {
            $orientationGroups[$row['domain']][] = $row['value'];
        }

        return [...$this->calculator->calculate($domain, $depths, $intervals, $phaseGroups, $orientationGroups, $previous),
            'creating_domain' => $domain, 'domain_depths' => $depths, 'domain_intervals' => $intervals,
            'cadence_interval' => $interval, 'phase_observations' => $phases, 'orientation_observations' => $orientations,
            'history_proposition_id' => $head->phase->cycle->entry_committal_id, 'trigger' => $trigger];
    }

    private function entry(Proposition $head): array
    {
        $row = DB::table('prospective_closure_states')->where('proposition_id', $head->phase->cycle->entry_committal_id)->first();
        if ($row === null || ! $row->frozen) {
            throw new ClosureHistoryUnavailable('Prospective Closure requires frozen selected committed history.');
        }

        return $this->decode($row);
    }

    private function decode(?object $row): array
    {
        if ($row === null || $row->version !== self::VERSION) {
            throw new ClosureHistoryUnavailable('Authorised prospective Closure is unavailable; reads cannot reconstruct it.');
        }

        return json_decode($row->observation, true, flags: JSON_THROW_ON_ERROR);
    }

    private function insert(Proposition $head, array $observation, bool $frozen): void
    {
        DB::table('prospective_closure_states')->insert(['proposition_id' => $head->id,
            'version' => self::VERSION, 'frozen' => $frozen, 'observation' => $this->encode($observation)]);
    }

    private function encode(array $observation): string
    {
        return json_encode($observation, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function assertTransaction(Proposition $head): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \DomainException('Closure mutation requires the contribution transaction.');
        }
        Substrate::whereKey($head->phase->cycle->participant->substrate_id)->lockForUpdate()->firstOrFail();
    }
}
