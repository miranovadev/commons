<?php

namespace Diagnostics;

use App\Models\{Attestation, Cycle, Participant, Phase, Proposition, Substrate, SubstrateLedgerEntry};
use App\Services\{ParticipationService, ChiralityGateService};
use App\Services\Observables\{ChiralityCalculator, ContextLineageComparisonService, LineageMeasurementService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Diagnostic only. No calculator overrides; stops before candidate Bias evaluation. */
class BootstrapDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    private int $clock = 0;
    private array $entryOrder = [];
    private array $actOrder = [];

    public static function matrix(): array
    {
        $rows = [];
        // Fixed before execution; repeat the final sign after the listed prefix.
        foreach (['A' => [1,1,1,1,1], 'B' => [1,1,-1,-1,-1],
                  'C' => [1,-1,1,-1,1], 'D' => [1,-1,-1,1,1]] as $name => $signs) {
            foreach (['support', 'refute'] as $label) {
                $rows[$name.'-'.$label] = [$name, $signs, $label];
            }
        }
        return $rows;
    }

    #[DataProvider('matrix')]
    public function test_observe_prefix(string $name, array $signs, string $label): void
    {
        Cycle::created(function ($cycle) { $this->entryOrder[$cycle->id] = ++$this->clock; });
        SubstrateLedgerEntry::created(function ($entry) {
            ++$this->clock;
            if ($entry->ledgerable_type === Attestation::class) {
                $this->actOrder[$entry->ledgerable_id] = $this->clock;
            }
        });
        $substrate = (new Substrate)->forceFill(['slug'=>'diagnostic','title'=>'Diagnostic','visibility'=>'public','configuration'=>[]]);
        $substrate->save();
        $actor = (new Participant)->forceFill(['substrate_id'=>$substrate->id,'callsign'=>'author','max_read_count'=>25]);
        $actor->save();
        $service = app(ParticipationService::class);
        $state = $service->enter($actor, ['statement'=>'Origin','attestation'=>['direction'=>$label]]);
        $origin = Proposition::findOrFail($state['current_proposition_id']);
        $rows = [['stage'=>'Origin','observation'=>$this->observe($origin)]];
        $actionsTaken = []; $attestationIndex = 0; $phaseSeen = [];
        $stop = 'action_bound';
        for ($step=0; $step<100; $step++) {
            $state = $service->stateFor($actor);
            if ($state['status'] !== 'ready') { $stop = $state['status']; break; }
            $plan=$state['plan']; $phase=(int)$plan['phase']['number'];
            if (!isset($phaseSeen[$phase])) {
                $gate=Proposition::findOrFail($plan['gate_id']);
                $rows[]=['stage'=>'Phase '.$phase.' Gate','observation'=>$this->observe($gate)];
                $phaseSeen[$phase]=true;
            }
            if ($phase===6) { $stop='candidate_cadence_undefined_at_first_bias'; break; }
            $actions=$plan['schema']['$defs']['actions'];
            $action=collect(['proposition.reclassify','proposition.variant','proposition.reconcile',
                'proposition.traverse','attestation.create','instrument.execute','participant.rest'])
                ->first(fn($candidate)=>isset($actions[$candidate]));
            if ($action===null) { $stop='no_planned_non_rest_action'; break; }
            $definition=$actions[$action]; $input=['action'=>$action];
            foreach (['proposition_id','other_proposition_id','receipt_id'] as $key) {
                if(isset($definition['properties'][$key]['enum'])) $input[$key]=$definition['properties'][$key]['enum'][0];
            }
            if($action==='proposition.variant') $input['statement']='Development '.$step;
            if($action==='instrument.execute') $input['instrument']=['kind'=>'hyperlink','reference'=>'https://example.test/evidence/'.$step];
            if($action==='attestation.create') {
                // Identical population schedule for all eight cases: one idle entry
                // immediately before ordinary Attestation acts 2 and 4.
                if(in_array($attestationIndex,[1,3],true)) {
                    $idle=(new Participant)->forceFill(['substrate_id'=>$substrate->id,'callsign'=>'idle-'.$attestationIndex]);
                    $idle->save(); $service->enter($idle);
                }
                $sign=$signs[min($attestationIndex,count($signs)-1)];
                $direction=$sign===1 ? $label : ($label==='support'?'refute':'support');
                $choice=collect($definition['anyOf'])->first(fn($choice)=>in_array($direction,$choice['properties']['direction']['enum'],true));
                if($choice===null) { $stop='scheduled_attestation_unavailable'; break; }
                $input['proposition_id']=$choice['properties']['proposition_id']['enum'][0];
                $input['direction']=$direction; $attestationIndex++;
            }
            $actionsTaken[]=['phase'=>$phase,'action'=>$action,'effective_sign'=>$action==='attestation.create'?$sign:null];
            $service->contribute($actor,$plan['phase']['id'],$input);
            if ($action === 'attestation.create') {
                $rows[]=['stage'=>'After ordinary Attestation '.$attestationIndex,
                    'observation'=>$this->observe(Proposition::findOrFail($input['proposition_id']))];
            }
        }
        $result=['case'=>$name,'origin_label'=>$label,'planned_signs'=>$signs,'population_schedule'=>'one idle entry before ordinary acts 2 and 4',
            'stop'=>$stop,'ordinary_commits'=>0,'actions'=>$actionsTaken,'observations'=>$rows];
        file_put_contents(__DIR__.'/result-'.$name.'-'.$label.'.json',json_encode($result,JSON_PRETTY_PRINT|JSON_PRESERVE_ZERO_FRACTION)."\n");
        $this->assertSame(1, $origin->attestations()->count());
        $this->assertSame('settlement',$actor->fresh()->cycles()->latest('sequence')->first()->contextHorizon->domain->value);
    }

    private function observe(Proposition $node): array
    {
        $comparison=app(ContextLineageComparisonService::class)->for($node);
        $calc=app(ChiralityCalculator::class);
        $lean=fn($l)=>$l===null?null:$calc->leanFor($calc->activeIds($l->propositions,$l->supersessions));
        $current=$lean($comparison->current); $previous=$lean($comparison->previous);
        $old=$calc->components($comparison);
        $measured=app(LineageMeasurementService::class)->calculate($node);
        $orientation=$previous===null?0.0:round(($current-$previous)/2,7);
        $c=$measured->closure();
        $rotationSum=$mass=0.0;
        foreach (app(LineageMeasurementService::class)->positions($node) as [$member,$distance]) {
            $cmp=app(ContextLineageComparisonService::class)->for($member);
            $o=$cmp->previous===null?0.0:($lean($cmp->current)-$lean($cmp->previous))/2;
            $weight=LineageMeasurementService::attenuation($distance);
            $rotationSum+=($o/2)*$weight; $mass+=$weight;
        }
        $rotationContribution=round($rotationSum/$mass,7);
        return ['domain'=>$comparison->current->horizon->domain->value,
            'current_lean'=>$current,'previous_lean'=>$previous,
            'current_intervals'=>$this->intervals($comparison->current),
            'previous_intervals'=>$comparison->previous===null?null:$this->intervals($comparison->previous),
            'candidate'=>['orientation'=>$orientation,'orientation_direction'=>$orientation>0?'positive':'negative',
                'attestation_cadence'=>null,'attestation_cadence_direction'=>null,'local_chirality'=>null,
                'attenuated_chirality'=>null,'chirality_direction'=>null,'bias_admissible'=>null,'tension'=>null,
                'required_correspondence'=>null,'resulting_domain'=>null,
                'attenuated_orientation_contribution'=>$rotationContribution,
                'chirality_range_if_all_cadences_bounded'=>[$rotationContribution-.5,$rotationContribution+.5]],
            'current'=>['orientation'=>$old->orientation,'attestation_cadence'=>$old->cadence,
                'current_acts'=>$old->currentActs,'current_cycles'=>$old->currentCycles,'previous_acts'=>$old->previousActs,'previous_cycles'=>$old->previousCycles,
                'local_chirality'=>$old->average(),'attenuated_chirality'=>$measured->chirality,
                'gate'=>app(ChiralityGateService::class)->evaluate($node)],
            'canonical'=>['correspondence'=>$measured->correspondence,
                'shape_topology'=>$measured->correspondenceComponents->shapeTopology,
                'cardinal_position'=>$measured->correspondenceComponents->cardinalPosition,
                'closure'=>$c->average(),'committed_cardinal_position'=>$c->resolve()['committed_cardinal_position'],
                'committal_cadence'=>$c->cadence,'closure_admissible'=>$c->resolve()['admissible']]];
    }

    private function intervals($lineage): array
    {
        $entries=$lineage->propositions->map(fn($p)=>$p->phase->cycle->entry_committal_id)
            ->merge($lineage->propositions->where('tier','committal')->pluck('id'))->filter()->unique();
        $cycles=Cycle::whereIn('entry_committal_id',$entries)->where('context_horizon_id',$lineage->horizon->id)->get();
        $origins=$lineage->propositions->filter(fn($p)=>$p->tier==='committal'&&$p->phase->cycle->entry_committal_id===null)
            ->map(fn($p)=>$p->phase->cycle);
        $cycles=$cycles->merge($origins)->unique('id');
        $acts=Attestation::whereIn('proposition_id',$lineage->propositions->pluck('id'))->with('ledgerEntry')->get()->sortBy(fn($a)=>$a->ledgerEntry->sequence)->values();
        $intervals=[];
        for($i=1;$i<$acts->count();$i++) {
            $a=$this->actOrder[$acts[$i-1]->id]; $b=$this->actOrder[$acts[$i]->id];
            $intervals[]=$cycles->filter(fn($c)=>$this->entryOrder[$c->id]>$a&&$this->entryOrder[$c->id]<$b)->count();
        }
        $mean=count($intervals)?array_sum($intervals)/count($intervals):null;
        $cv=$mean===null||$mean==0?null:sqrt(array_sum(array_map(fn($x)=>($x-$mean)**2,$intervals))/count($intervals))/$mean;
        return ['acts'=>$acts->count(),'intervals'=>$intervals,'mean'=>$mean,'cv'=>$cv,
            'status'=>$mean===null?'no_intervals':($mean==0?'undefined_zero_mean':(count($intervals)<2?'singleton_interval':'empirical_cv'))];
    }
}
