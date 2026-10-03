<?php
namespace Diagnostics\NaturalRhythm;

use App\Domain\Observables\{ChiralityValues,LineageComparison};
use App\Models\{Proposition,Supersession};

/** Process-local, opt-in candidate. No production writes, inherited votes or direction overrides. */
final class BoundaryBeatMeasurements extends CandidateLocalMeasurements
{
    private array $cache=[];
    public array $changes=[];
    public array $births=[];
    public ?array $undefined=null;

    public function registerBirth(Supersession $edge):void
    {
        $id=$edge->target_proposition_id;
        if(isset($this->births[$id]))throw new SemanticBoundary('Multiple causal predecessors require an explicit anchor selection rule.');
        $parent=$this->observation(Proposition::findOrFail($edge->source_proposition_id));
        $orders=$parent['current_history']['act_order'];
        if(!$orders){
            $this->undefined=['reason'=>'immediate_predecessor_without_attestation','parent_id'=>$edge->source_proposition_id,'child_id'=>$id,'edge_type'=>$edge->type,'birth_event_order'=>app(EventOrder::class)->clock];
            throw new SemanticBoundary('Immediate causal predecessor has no own Attestation beat.');
        }
        $this->births[$id]=[
            'parent_id'=>$edge->source_proposition_id,'child_id'=>$id,'edge_type'=>$edge->type,
            'birth_event_order'=>app(EventOrder::class)->clock,
            'parent_intervals_audit_only'=>$parent['current_history']['intervals'],
            'parent_own_act_count'=>$parent['current_history']['acts'],
            'endpoint_order'=>array_last($orders),
        ];
        // A creation-time read may precede the causal edge. Reset only that newborn cache.
        unset($this->cache[$id]);
    }

    public function chiralityComponents(Proposition $p,?LineageComparison $comparison=null):ChiralityValues
    {
        $d=$this->observation($p,$comparison);
        return new ChiralityValues($d['orientation'],$d['attestation_cadence']);
    }
    public function diagnose(LineageComparison $comparison):array
    {
        return $this->observation($comparison->current->propositions->last(),$comparison);
    }
    public function observation(Proposition $p,?LineageComparison $comparison=null):array
    {
        $d=app(PropositionLocalMeasurements::class)->observation($p,$comparison);
        if(isset($this->cache[$p->id])&&$this->cache[$p->id]['signature']===$d['signature'])return $this->cache[$p->id];
        $birth=$this->births[$p->id]??null;
        $d['previous_boundary_beat']=$birth['endpoint_order']??null;
        $d['boundary_endpoint_order']=$birth['endpoint_order']??null;
        $d['local_completed_intervals']=$d['current_history']['intervals'];
        $d['first_successor_interval']=null;
        $d['boundary_beat_used']=false;
        if($d['previous_boundary_beat']!==null&&$d['attestation_observed']){
            if($d['cadence_status']==='constitutional_origin'||!str_starts_with($d['cadence_status'],'formation_'))throw new SemanticBoundary('Boundary beat comparison beyond first Settlement is outside this experiment.');
            $h=$d['current_history'];
            $first=$h['act_order'][0];$from=$d['boundary_endpoint_order'];
            if($first<=$from)throw new \RuntimeException('Noncausal boundary endpoint');
            $gap=count(array_filter($h['represented_cycle_order'],fn($n)=>$n>$from&&$n<$first));
            $d['first_successor_interval']=$gap;
            $d['local_completed_intervals']=[$gap,...$h['intervals']];
            $samples=$d['local_completed_intervals'];
            $mean=array_sum($samples)/count($samples);
            $sd=sqrt(array_sum(array_map(fn($v)=>($v-$mean)**2,$samples))/count($samples));
            $cv=$mean==0 ? null : $sd/$mean;
            $d['boundary_beat_used']=true;
            $d['current_history']=[...$h,'intervals'=>$samples,'usable_interval_count'=>count($samples),'mean'=>$mean,'stddev'=>$sd,'cv'=>$cv,'status'=>$cv===null?'undefined_zero_mean':'empirical_cv'];
            if(count($samples)>=2&&$cv===null){
                $d['attestation_cadence']=$d['cadence_contribution']=$d['local_chirality']=null;
                $d['attestation_cadence_mature']=null;$d['cadence_status']='undefined_zero_mean';$d['component_gate']='unresolved';
                $this->undefined=['proposition_id'=>$p->id,'birth'=>$birth,'attempted_observation'=>$d,'reason'=>'undefined_zero_mean'];
                throw new SemanticBoundary('Boundary beat requires undefined zero-mean CV: '.json_encode($samples));
            }
            $mature=count($samples)>=2;
            $cadence=$mature?round(max(-1,min(1,(1-$cv)/(1+$cv))),7):0.0;
            $d['attestation_cadence']=$d['cadence_contribution']=$cadence;
            $d['attestation_cadence_mature']=$mature;$d['cadence_status']=$mature?'formation_cv':'formation_immature';$d['component_gate']=$mature?'direction_agreement':'immature_direction_hysteresis';
            $d['local_chirality']=(new ChiralityValues($d['orientation'],$cadence))->average();
        }
        $before=$this->cache[$p->id]??null;
        $this->cache[$p->id]=$d;
        $this->changes[]=['proposition_id'=>$p->id,'before'=>$before,'after'=>$d];
        return $d;
    }
}
