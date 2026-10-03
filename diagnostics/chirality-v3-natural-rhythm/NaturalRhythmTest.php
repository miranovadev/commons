<?php

namespace Diagnostics\NaturalRhythm;

use App\Enums\ContextDomain;
use App\Enums\ObservableDirection;
use App\Enums\SupersessionStrength;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\SubstrateLedgerEntry;
use App\Services\ChiralityGateService;
use App\Services\CommitRegionPolarityService;
use App\Services\Observables\CardinalMorphologyService;
use App\Services\Observables\ChiralityCalculator;
use App\Services\Observables\ContextLineageComparisonService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\ParticipationService;
use App\Services\ProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NaturalRhythmTest extends TestCase
{
    use RefreshDatabase;

    public static function cases(): array
    {
        $rhythms = ['noisy' => [1,5,2,6], 'regularising' => [5,4,3,3],
            'regular_2' => [2], 'disrupted' => [2,2,2,6], 'sparse_5' => [5],
            'regular_1' => [1], 'regular_3' => [3]];
        $cases = [];
        foreach (getenv('RHYTHM_POPULATION') ? [(int) getenv('RHYTHM_POPULATION')] : [1,3] as $population) {
            foreach ($rhythms as $name => $rhythm) {
                if (getenv('RHYTHM_CASE') && getenv('RHYTHM_CASE') !== $name) { continue; }
                $cases[$population.'-'.$name] = [$population, $name, $rhythm];
            }
        }
        return $cases;
    }

    #[DataProvider('cases')]
    public function test_natural_rhythm(int $population, string $name, array $rhythm): void
    {
        $this->app->singleton(EventOrder::class);
        $this->app->bind(LocalMeasurementService::class, CandidateLocalMeasurements::class);
        $order = app(EventOrder::class);
        Cycle::created(function ($cycle) use ($order) { $order->cycles[$cycle->id] = ++$order->clock; });
        Attestation::created(function ($act) use ($order) { $order->acts[$act->id] = ++$order->clock; });
        $sub = (new Substrate)->forceFill(['slug' => 'natural-rhythm', 'title' => 'Natural rhythm', 'visibility' => 'public', 'configuration' => []]);
        $sub->save();
        $actors = [];
        for ($i = 0; $i < $population; $i++) {
            $actor = (new Participant)->forceFill(['substrate_id' => $sub->id, 'callsign' => 'author-'.$i, 'max_read_count' => 25]);
            $actor->save();
            $actors[] = $actor;
        }
        $service = app(ParticipationService::class);
        $state = $service->enter($actors[0], ['statement' => 'Origin', 'attestation' => ['direction' => 'support']]);
        $origin = Proposition::findOrFail($state['current_proposition_id']);
        $this->assertSame(-0.5, $origin->commitmentSnapshot->chirality);
        $this->assertSame(ContextDomain::Settlement, $origin->commitmentSnapshot->direction_after);
        for ($i = 1; $i < $population; $i++) { $service->enter($actors[$i]); }
        $initialCycles = count($order->cycles);
        $result = ['population' => $population, 'case' => $name, 'requested_rhythm' => $rhythm,
            'origin' => $origin->commitmentSnapshot->only(['correspondence','chirality','closure','tension','direction_before','direction_after']),
            'origin_label' => 'support', 'rows' => [], 'actions' => [], 'bias' => [], 'spacing_shortfalls' => [],
            'cycle_boundaries' => [], 'observations' => [], 'crossed' => false, 'stop' => 'action_bound', 'action_bound' => 400];
        $ordinaryActs = 0;
        $lastActOrder = null;
        $nextActor = 0;
        $commits = 0;
        $step = 0;
        try {
            for (; $step < 400; $step++) {
                // Fair cyclic scan. Delay an Attestation only while another genuine
                // action exists. No clock advance, forced Rest, or synthetic entry.
                $offered = [];
                for ($offset = 0; $offset < $population; $offset++) {
                    $index = ($nextActor + $offset) % $population;
                    $state = $service->stateFor($actors[$index]);
                    if ($state['status'] !== 'ready') { continue; }
                    $defs = $state['plan']['schema']['$defs']['actions'];
                    $offered[] = ['index' => $index, 'state' => $state, 'defs' => $defs];
                }
                if (! $offered) { $result['stop'] = 'no_ready_participant'; break; }
                $gap = $lastActOrder === null ? 0 : count(array_filter($order->cycles, fn ($at) => $at > $lastActOrder));
                $targetIndex = max(0, $ordinaryActs - 1);
                $target = $name === 'regularising' ? $rhythm[min($targetIndex, count($rhythm)-1)]
                    : ($name === 'disrupted' ? ($targetIndex < 3 ? 2 : 6) : $rhythm[$targetIndex % count($rhythm)]);
                $attestNow = $lastActOrder === null || $gap >= $target;
                $choice = null;
                foreach ($offered as $offer) {
                    $priority = $attestNow
                        ? ['attestation.create','proposition.commit','proposition.reclassify','proposition.variant','proposition.reconcile','proposition.traverse','instrument.execute','participant.rest']
                        : ['proposition.commit','proposition.reclassify','proposition.variant','proposition.reconcile','proposition.traverse','instrument.execute','participant.rest'];
                    foreach ($priority as $action) {
                        if ($action === 'instrument.execute' && collect($result['actions'])->contains(fn ($r) => $r['phase_id'] === $offer['state']['plan']['phase']['id'] && $r['input']['action'] === 'instrument.execute')) { continue; }
                        if ($action === 'participant.rest' && ! collect($result['actions'])->contains(fn ($r) => $r['cycle_id'] === $offer['state']['cycle']['id'] && $r['input']['action'] !== 'participant.rest')) { continue; }
                        if (isset($offer['defs'][$action])) { $choice = $offer + ['action' => $action]; break 2; }
                    }
                }
                if ($choice === null) {
                    foreach ($offered as $offer) {
                        if (isset($offer['defs']['attestation.create'])) { $choice = $offer + ['action' => 'attestation.create']; break; }
                    }
                    if ($choice === null) { $result['stop'] = 'no_available_action'; break; }
                    $result['spacing_shortfalls'][] = ['step' => $step, 'target' => $target, 'actual_global_gap' => $gap,
                        'reason' => 'All ready participants require Attestation before further lawful progression'];
                }
                $index = $choice['index'];
                $actor = $actors[$index];
                $state = $choice['state'];
                $phase = Phase::findOrFail($state['plan']['phase']['id']);
                $gate = Proposition::findOrFail($state['plan']['gate_id']);
                if ($phase->kind->value === 6) {
                    $result['bias'][] = ['step' => $step, 'actor' => $index,
                        'observation' => app(LocalMeasurementService::class)->diagnose(app(ContextLineageComparisonService::class)->for($gate)),
                        'gate' => app(ChiralityGateService::class)->evaluate($gate), 'actions' => array_keys($choice['defs'])];
                }
                if ($phase->kind->value === 7) {
                    $result['rows'][] = $this->preview($gate, $phase, $commits+1) + ['step' => $step, 'actor' => $index,
                        'cycle' => $state['cycle']['sequence'], 'commit_action_available' => isset($choice['defs']['proposition.commit'])];
                }
                $action = $choice['action'];
                $definition = $choice['defs'][$action];
                $input = ['action' => $action];
                foreach (['proposition_id','other_proposition_id','receipt_id'] as $key) {
                    if (isset($definition['properties'][$key]['enum'])) { $input[$key] = $definition['properties'][$key]['enum'][0]; }
                }
                if ($action === 'proposition.variant') { $input['statement'] = 'Author '.$index.' development '.$step; }
                if ($action === 'instrument.execute') { $input['instrument'] = ['kind' => 'hyperlink', 'reference' => 'https://example.test/evidence/'.$index.'/'.$step]; }
                if ($action === 'attestation.create') {
                    $options = [];
                    foreach ($definition['anyOf'] as $option) {
                        foreach ($option['properties']['proposition_id']['enum'] as $id) {
                            $targetProp = Proposition::findOrFail($id);
                            $polarity = app(CommitRegionPolarityService::class);
                            $seed = $polarity->seedFor($targetProp);
                            $support = $seed === null ? 1 : ($polarity->forSeed($seed)['support'] === ObservableDirection::Positive ? 1 : -1);
                            foreach ($option['properties']['direction']['enum'] as $direction) {
                                $options[] = ['id' => $id, 'direction' => $direction, 'effective_sign' => $direction === 'support' ? $support : -$support];
                            }
                        }
                    }
                    $selected = collect($options)->firstWhere('effective_sign', 1) ?? $options[0];
                    $input['proposition_id'] = $selected['id'];
                    $input['direction'] = $selected['direction'];
                }
                $before = count($order->cycles);
                if ($action === 'attestation.create' || $phase->kind->value === 7) {
                    $cmp = app(ContextLineageComparisonService::class)->for($gate);
                    $values = app(LineageMeasurementService::class)->calculate($gate, $cmp);
                    $details = app(LocalMeasurementService::class)->diagnose($cmp);
                    $field = app(ProjectionService::class)->field($values->observables());
                    $result['observations'][] = ['step'=>$step, 'actor'=>$index, 'cycle'=>$state['cycle']['sequence'], 'phase'=>$phase->kind->value,
                        'domain'=>$phase->cycle->contextHorizon->domain->value, 'scope'=>'existing_gate_before_action',
                        'correspondence'=>$values->correspondence,'chirality'=>$values->chirality,'closure'=>$values->closure()->average(),
                        ...$field,...$details];
                }
                $result['actions'][] = ['step' => $step, 'actor' => $index, 'cycle' => $state['cycle']['sequence'],
                    'phase' => $phase->kind->value, 'phase_id' => $phase->id, 'cycle_id' => $phase->cycle_id, 'domain' => $phase->cycle->contextHorizon->domain->value,
                    'input' => $input, 'requested_gap' => $target, 'global_gap_before' => $gap, 'event_order_before' => $order->clock];
                $service->contribute($actor, $phase->id, $input);
                $nextActor = ($index + 1) % $population;
                $result['actions'][array_key_last($result['actions'])]['returned_successfully'] = true;
                if ($action === 'attestation.create') { $ordinaryActs++; $lastActOrder = max($order->acts); }
                if (count($order->cycles) > $before) {
                    $result['cycle_boundaries'][] = ['step' => $step, 'actor' => $index, 'caused_by' => $action,
                        'entries_added' => count($order->cycles) - $before, 'event_order' => $order->clock];
                }
                if ($action === 'proposition.commit') {
                    $commits++;
                    $commit = $actor->fresh()->position;
                    $snapshot = $commit->commitmentSnapshot;
                    $last = array_key_last($result['rows']);
                    $result['rows'][$last]['actual'] = $snapshot->only(['correspondence','chirality','orientation','orientation_direction',
                        'attestation_cadence','attestation_cadence_direction','closure','tension','direction_before','direction_after']);
                    foreach (['correspondence','chirality','closure'] as $field) { $this->assertSame($result['rows'][$last][$field], $snapshot->$field); }
                    if ($snapshot->direction_before === ContextDomain::Settlement && $snapshot->direction_after === ContextDomain::Emergence) {
                        $result['crossed'] = true; $result['stop'] = 'first_emergence'; break;
                    }
                    if ($commits >= 3) { $result['stop'] = 'three_commit_bound'; break; }
                }
            }
        } catch (SemanticBoundary $e) {
            $result['stop'] = 'unresolved_zero_mean';
            $result['semantic_boundary'] = $e->getMessage();
        } finally {
            $result['persisted_attestations'] = Attestation::whereHas('phase.cycle.participant', fn ($q) => $q->where('substrate_id', $sub->id))->get()->map(fn ($a) => ['id'=>$a->id,'proposition_id'=>$a->proposition_id,'phase_id'=>$a->phase_id,'direction'=>$a->direction,'event_order'=>$order->acts[$a->id],'timestamp'=>$a->created_at->toISOString()])->all();
            $result['persisted_cycles'] = Cycle::whereHas('participant', fn ($q) => $q->where('substrate_id', $sub->id))->get()->map(fn ($c) => ['id'=>$c->id,'participant_id'=>$c->participant_id,'sequence'=>$c->sequence,'entry_committal_id'=>$c->entry_committal_id,'event_order'=>$order->cycles[$c->id],'completed'=>$c->completed_at !== null,'rested'=>$c->rested_at !== null])->all();
            $result['ordinary_commits'] = $commits;
            $result['final_participants'] = Participant::where('substrate_id', $sub->id)->count();
            $result['initial_cycles'] = $initialCycles;
            $result['final_cycles'] = count($order->cycles);
            $result['actual_ordinary_attestations'] = Attestation::whereHas('phase.cycle.participant', fn ($q) => $q->where('substrate_id', $sub->id))->count()-1;
            $result['participation'] = collect($actors)->map(fn ($a, $i) => ['actor' => $i, 'actions' => count(array_filter($result['actions'], fn ($r) => $r['actor'] === $i))])->all();
            file_put_contents(__DIR__.'/result-'.$population.'-'.$name.'.json', json_encode($result, JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION)."\n");
            fwrite(STDERR, "\nNATURAL_RHYTHM ".json_encode(['population'=>$population,'case'=>$name,'stop'=>$result['stop'],'actions'=>count($result['actions']),'commits'=>$commits,'crossed'=>$result['crossed']])."\n");
        }
        $this->assertSame($population, $result['final_participants']);
        $this->assertGreaterThan(0, count($result['actions']));
        $last = 0;
        foreach (SubstrateLedgerEntry::where('ledgerable_type', Attestation::class)->where('substrate_id', $sub->id)->orderBy('sequence')->get() as $entry) {
            $this->assertGreaterThan($last, $order->acts[$entry->ledgerable_id]);
            $last = $order->acts[$entry->ledgerable_id];
        }
    }
    private function preview(Proposition $gate, Phase $phase, int $ordinal): array
    {
        $comparison = app(ContextLineageComparisonService::class)->prospectiveCommit($gate, $phase);
        $candidate = $comparison->current->propositions->last();
        $values = app(LineageMeasurementService::class)->calculate($candidate, $comparison);
        $details = app(LocalMeasurementService::class)->diagnose($comparison);
        $closure = $values->closure()->resolve();
        $entry = $phase->cycle->entryCommittal->commitmentSnapshot;
        $directions = $values->chiralityComponents->resolveDirections(ObservableDirection::from($entry->orientation_direction ?? 'negative'), ObservableDirection::from($entry->attestation_cadence_direction ?? 'positive'));
        $chiralityDirection = ObservableDirection::after($values->chirality, ObservableDirection::from($entry->chirality_direction ?? 'positive'))->value;
        $field = app(ProjectionService::class)->field($values->observables());
        $v2Total = $mass = 0.0;
        foreach (app(LineageMeasurementService::class)->positions($candidate) as [$node,$distance]) {
            $cmp = $distance === 0 ? $comparison : app(ContextLineageComparisonService::class)->for($node);
            $v2 = app(ChiralityCalculator::class)->components($cmp);
            $weight = LineageMeasurementService::attenuation($distance);
            $v2Total += $v2->average() * $weight;
            $mass += $weight;
        }
        $v2 = app(ChiralityCalculator::class)->components($comparison);
        $morphology = app(CardinalMorphologyService::class)->current($candidate, $comparison->current->horizon);
        $singleParent = $morphology->supersessions->groupBy('target_proposition_id')->every(fn ($edges) => $edges->pluck('source_proposition_id')->unique()->count() === 1);
        $required = -($values->chirality + $values->closure()->average());

        return ['ordinal' => $ordinal, 'current_domain' => $phase->cycle->contextHorizon->domain->value,
            'morphology' => ['head' => $candidate->id, 'positions' => collect($morphology->positions)->map(fn ($p) => ['depth' => $p->depth, 'lateral' => $p->lateral])->all(),
                'nodes' => $morphology->propositions->map(fn ($p) => ['id' => $p->id, 'register' => $p->register, 'tier' => $p->tier])->values()->all(),
                'edges' => $morphology->supersessions->map(fn ($e) => ['source' => $e->source_proposition_id, 'target' => $e->target_proposition_id, 'strength' => SupersessionStrength::for($e->type)->value])->values()->all()],
            'morphology_single_parent' => $singleParent, 'morphology_nodes' => $morphology->propositions->count(),
            'morphology_edges' => $morphology->supersessions->count(), 'morphology_primary_depth' => $morphology->primaryDepth,
            'entry_committal_id' => $phase->cycle->entry_committal_id, 'correspondence' => $values->correspondence,
            'shape_topology' => $values->correspondenceComponents->shapeTopology, 'cardinal_position' => $values->correspondenceComponents->cardinalPosition,
            ...$details, ...$directions, 'local_chirality' => $values->chiralityComponents->average(),
            'chirality' => $values->chirality, 'chirality_direction' => $chiralityDirection,
            'closure' => $closure['closure'], 'closure_observation' => $closure, 'tension' => $field['tension'], 'charge' => $field['charge'],
            'required_correspondence' => $required, 'correspondence_margin' => round($values->correspondence - $required, 7),
            'resulting_domain' => ContextDomain::after($field['tension'], $phase->cycle->contextHorizon->domain)->value,
            'v2' => ['orientation' => $v2->orientation, 'attestation_cadence' => $v2->cadence, 'local_chirality' => $v2->average(), 'attenuated_chirality' => round($v2Total / $mass,7)]];
    }
}
