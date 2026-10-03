<?php
namespace Diagnostics\NaturalRhythm;

use App\Domain\Observables\{ChiralityValues,DomainLineage,LineageComparison};
use App\Models\{Attestation,Proposition,Supersession};
use App\Services\Observables\ContextLineageComparisonService;

/** Isolated observation cache: only this proposition's own Attestation signature invalidates it. */
final class LocalReconcileMeasurements extends CursorFormulaMeasurements
{
    private array $cache=[];
    public array $changes=[];
    public array $births=[];
    public array $reconcileInputs=[];
    public ?array $undefined=null;
    public function registerBirth(Supersession $edge):void
    {
        $id=$edge->target_proposition_id;
        $parent=$this->observation(Proposition::findOrFail($edge->source_proposition_id));
        $orders=$parent['current_history']['act_order'];
        $endpoint=$orders?array_last($orders):$parent['previous_boundary_beat'];
        $path=$orders?[$edge->source_proposition_id]:[...($this->births[$edge->source_proposition_id]['cursor_path']??[]),$edge->source_proposition_id];
        $birth=['parent_id'=>$edge->source_proposition_id,'child_id'=>$id,'edge_type'=>$edge->type,'birth_event_order'=>app(EventOrder::class)->clock,
            'endpoint_order'=>$endpoint,'parent_own_act_count'=>count($orders),'cursor_path'=>$path,
            'provenance'=>$orders?'immediate_predecessor':($endpoint===null?'bootstrap_no_branch_beat':'unattested_pass_through')];
        if($edge->type==='reconcile'){
            $acting=\App\Models\Phase::findOrFail($edge->phase_id)->gate_proposition_id;
            $selected=$edge->source_proposition_id===$acting;
            $birth['acting_source_id']=$acting;
            $birth['selected']=$selected;
            $birth['selection_reason']=$selected?'local_acting_source':'other_parent_ancestry_only';
            $this->reconcileInputs[$id][]=$birth;
            // Null local cursor stays null; the other parent is never a fallback.
            if(!$selected)return;
        }elseif(isset($this->births[$id])){
            throw new SemanticBoundary('Unexpected multiple parents outside Reconcile.');
        }
        $this->births[$id]=$birth;
        unset($this->cache[$id]);
    }
    protected function intervals($lineage):array
    {
        $h=parent::intervals($lineage);
        $p=$lineage->propositions->count()===1?$lineage->propositions->first():null;
        $birth=$p?($this->births[$p->id]??null):null;
        $h['boundary_beat']=$birth['endpoint_order']??null;
        $h['first_successor_interval']=null;
        if($h['boundary_beat']!==null&&$h['acts']>0){
            $from=$h['boundary_beat'];$to=$h['act_order'][0];
            if($to<=$from)throw new \RuntimeException('Noncausal cursor');
            $h['first_successor_interval']=count(array_filter($h['represented_cycle_order'],fn($n)=>$n>$from&&$n<$to));
            array_unshift($h['intervals'],$h['first_successor_interval']);
        }
        $samples=$h['intervals'];$n=count($samples);
        $mean=$n?array_sum($samples)/$n:null;
        $sd=$n?sqrt(array_sum(array_map(fn($x)=>($x-$mean)**2,$samples))/$n):null;
        $raw=$mean===null||$mean==0?null:$sd/$mean;
        $zero=$n>=2&&$mean==0&&$sd==0;
        return [...$h,'usable_interval_count'=>$n,'mean'=>$mean,'stddev'=>$sd,'raw_cv'=>$raw,'cv'=>$zero?0.0:$raw,'zero_mean_regular'=>$zero,
            'status'=>$n<2?($n?'singleton_unresolved':'no_intervals'):($zero?'zero_mean_perfectly_regular':'empirical_cv')];
    }
    public function chiralityComponents(Proposition $proposition,?LineageComparison $comparison=null):ChiralityValues
    {
        $d=$this->observation($proposition,$comparison);
        return new ChiralityValues($d['orientation'],$d['attestation_cadence']);
    }
    public function diagnose(LineageComparison $comparison):array
    {
        return $this->observation($comparison->current->propositions->last(),$comparison);
    }
    public function observation(Proposition $p,?LineageComparison $comparison=null):array
    {
        // Replacement is a new historical act; direction/state edits invalidate only their own target.
        $acts=Attestation::where('proposition_id',$p->id)->orderBy('id')->get();
        $signature=hash('sha256',json_encode($acts->map(fn($a)=>$a->only(['id','phase_id','direction','supersedes_attestation_id','rationale','updated_at']))->all()));
        if(isset($this->cache[$p->id])&&$this->cache[$p->id]['signature']===$signature)return $this->cache[$p->id]['value'];
        $comparison??=app(ContextLineageComparisonService::class)->for($p);
        // Preserve the existing complementary reference/formula. Narrow only the current population.
        $local=new LineageComparison(new DomainLineage($comparison->current->horizon,collect([$p]),collect()),$comparison->previous);
        $d=parent::diagnose($local);
        $d['proposition_id']=$p->id;
        $birth=$this->births[$p->id]??null;
        $d['previous_boundary_beat']=$birth['endpoint_order']??null;
        $d['cursor_provenance']=$birth['provenance']??'bootstrap_no_branch_beat';
        $d['cursor_path']=$birth['cursor_path']??[];
        $d['current_cursor']=$acts->isNotEmpty()?array_last($d['current_history']['act_order']):$d['previous_boundary_beat'];
        $d['local_completed_intervals']=$d['current_history']['intervals'];
        $d['first_successor_interval']=$d['current_history']['first_successor_interval'];
        $d['boundary_beat_used']=$d['first_successor_interval']!==null;
        if($d['current_history']['zero_mean_regular'])$d['cadence_status']='formation_zero_mean_regular';
        $d['own_effective_attestations']=Attestation::effective()->where('proposition_id',$p->id)->get()->map(fn($a)=>$a->only(['id','phase_id','direction','supersedes_attestation_id']))->all();
        $d['attestation_observed']=$acts->isNotEmpty();
        $d['local_chirality']=(new ChiralityValues($d['orientation'],$d['attestation_cadence']))->average();
        $d['signature']=$signature;
        $d['observed_event_order']=app(EventOrder::class)->clock;
        $d['observation_reason']=isset($this->cache[$p->id])?'own_attestation_state_changed':($acts->isEmpty()?'fresh_local_state':'first_own_attestation_observation');
        $before=$this->cache[$p->id]['value']??null;
        $this->cache[$p->id]=['signature'=>$signature,'value'=>$d];
        $this->changes[]=['proposition_id'=>$p->id,'before'=>$before,'after'=>$d];
        return $d;
    }
}
