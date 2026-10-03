<?php
namespace Diagnostics\NaturalRhythm;

use App\Domain\Observables\{ChiralityValues,DomainLineage,LineageComparison};
use App\Models\{Attestation,Proposition};
use App\Services\Observables\ContextLineageComparisonService;

/** Isolated observation cache: only this proposition's own Attestation signature invalidates it. */
final class PropositionLocalMeasurements extends CandidateLocalMeasurements
{
    private array $cache=[];
    public array $changes=[];
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
