<?php
namespace Diagnostics\NaturalRhythm;

use App\Domain\Observables\{ChiralityValues,LineageComparison};
use App\Models\{Proposition,Supersession};

/** Process-local, opt-in candidate. No production writes, inherited votes or direction overrides. */
final class AnchorMeasurements extends CandidateLocalMeasurements
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
        // A dormant ancestral anchor is not a newly completed local interval.
        $completed=$parent['local_completed_intervals'];
        $endpoint=$completed ? array_last($parent['current_history']['act_order']) : null;
        $this->births[$id]=[
            'parent_id'=>$edge->source_proposition_id,'child_id'=>$id,'edge_type'=>$edge->type,
            'birth_event_order'=>app(EventOrder::class)->clock,
            'parent_local_completed_intervals'=>$completed,
            'parent_own_act_count'=>$parent['current_history']['acts'],
            'anchor'=>$completed ? array_last($completed) : null,
            'endpoint_order'=>$endpoint,
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
        $d['cadence_anchor']=$birth['anchor']??null;
        $d['anchor_endpoint_order']=$birth['endpoint_order']??null;
        $d['local_completed_intervals']=$d['current_history']['intervals'];
        $d['first_successor_interval']=null;
        $d['anchor_activated']=false;
        if($d['cadence_anchor']!==null&&$d['attestation_observed']){
            if($d['cadence_status']==='constitutional_origin'||!str_starts_with($d['cadence_status'],'formation_'))throw new SemanticBoundary('Anchor comparison beyond first Settlement is outside this experiment.');
            $h=$d['current_history'];
            $first=$h['act_order'][0];$from=$d['anchor_endpoint_order'];
            if($first<=$from)throw new \RuntimeException('Noncausal anchor endpoint');
            $gap=count(array_filter($h['represented_cycle_order'],fn($n)=>$n>$from&&$n<$first));
            $d['first_successor_interval']=$gap;
            $d['local_completed_intervals']=[$gap,...$h['intervals']];
            $samples=[$d['cadence_anchor'],...$d['local_completed_intervals']];
            $mean=array_sum($samples)/count($samples);
            $sd=sqrt(array_sum(array_map(fn($v)=>($v-$mean)**2,$samples))/count($samples));
            $cv=$mean==0 ? null : $sd/$mean;
            $d['anchor_activated']=true;
            $d['current_history']=[...$h,'intervals'=>$samples,'usable_interval_count'=>count($samples),'mean'=>$mean,'stddev'=>$sd,'cv'=>$cv,'status'=>$cv===null?'undefined_zero_mean':'empirical_cv'];
            if($cv===null){
                $d['attestation_cadence']=$d['cadence_contribution']=$d['local_chirality']=null;
                $d['attestation_cadence_mature']=null;$d['cadence_status']='undefined_zero_mean';
                $this->undefined=['proposition_id'=>$p->id,'birth'=>$birth,'attempted_observation'=>$d,'reason'=>'undefined_zero_mean'];
                throw new SemanticBoundary('Cadence Anchor requires undefined zero-mean CV: '.json_encode($samples));
            }
            $cadence=round(max(-1,min(1,(1-$cv)/(1+$cv))),7);
            $d['attestation_cadence']=$d['cadence_contribution']=$cadence;
            $d['attestation_cadence_mature']=true;$d['cadence_status']='formation_cv';$d['component_gate']='direction_agreement';
            $d['local_chirality']=(new ChiralityValues($d['orientation'],$cadence))->average();
        }
        $before=$this->cache[$p->id]??null;
        $this->cache[$p->id]=$d;
        $this->changes[]=['proposition_id'=>$p->id,'before'=>$before,'after'=>$d];
        return $d;
    }
}
