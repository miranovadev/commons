<?php
namespace Diagnostics\NaturalRhythm;
use App\Models\{Proposition,Phase,Attestation,Receipt};
use App\Domain\Observables\{LocalMeasurements,LineageComparison,ChiralityValues};
use App\Services\Observables\{LocalMeasurementService,LineageMeasurementService,ContextLineageComparisonService};
use App\Services\{ProjectionService,ClosureGateService,ParticipationService};
use App\Enums\ContextDomain;

/** Read-only local-value provider for a canonical ancestor; never installed in the live container. */
final class CanonicalBaselineLocal extends LocalMeasurementService
{
    public array $reads=[];
    public function __construct(private LocalMeasurementService $expressive,private string $canonicalId,private LocalMeasurements $baseline){}
    public function calculate(Proposition $p,?LineageComparison $comparison=null,bool $includeClosure=true):LocalMeasurements
    {
        if($p->id!==$this->canonicalId)return $this->expressive->calculate($p,$comparison,$includeClosure);
        $this->reads[]=['id'=>$p->id,'include_closure'=>$includeClosure];
        // Ancestor Closure is excluded exactly as in the real lineage service.
        return new LocalMeasurements($this->baseline->correspondence,$this->baseline->chirality,0,0,0,null,null);
    }
    public function chiralityComponents(Proposition $p,?LineageComparison $comparison=null):ChiralityValues
    {
        if($p->id===$this->canonicalId)throw new \LogicException('Canonical aggregate has no newly invented local Chirality components.');
        return $this->expressive->chiralityComponents($p,$comparison);
    }
}
final class HardEdgeAudit
{
    public array $data=[];
    private LocalMeasurements $source;
    private string $sourceId;
    public function audit(Proposition $p):array
    {
        $service=app(LineageMeasurementService::class);$parts=[];$mass=0;
        foreach($service->positions($p) as [$member,$distance]){
            $v=app(LocalMeasurementService::class)->calculate($member,null,$distance===0);
            $w=$service::attenuation($distance);$mass+=$w;
            $parts[]=['id'=>$member->id,'distance'=>$distance,'weight'=>$w,'raw_correspondence'=>$v->correspondence,'raw_chirality'=>$v->chirality,'shape_topology'=>$v->correspondenceComponents?->shapeTopology,'cardinal_position'=>$v->correspondenceComponents?->cardinalPosition];
        }
        foreach($parts as &$part){$part['normalized_correspondence']=$part['weight']*$part['raw_correspondence']/$mass;$part['normalized_chirality']=$part['weight']*$part['raw_chirality']/$mass;}
        return ['id'=>$p->id,'parts'=>$parts,'mass'=>$mass,'resolved'=>$this->values($service->calculate($p))];
    }
    private function values(LocalMeasurements $v):array
    {
        return ['correspondence'=>$v->correspondence,'chirality'=>$v->chirality,'closure'=>$v->closure()->average(),...app(ProjectionService::class)->field($v->observables())];
    }
    public function before(Proposition $b,Phase $phase):void
    {
        $this->source=app(LineageMeasurementService::class)->calculate($b);$this->sourceId=$b->id;
        $this->data=['source'=>$this->audit($b),'source_local'=>app(LocalMeasurementService::class)->observation($b),'closure_preview'=>app(ClosureGateService::class)->preview($b,$phase)];
    }
    public function after(Proposition $c,$actor,ParticipationService $service):void
    {
        $snapshot=$c->commitmentSnapshot;$birth=app(ClosureGateService::class)->forCommitment($c);
        $this->data['recorded_commit']=$this->audit($c);
        $this->data['recorded_snapshot']=$snapshot->toArray();
        $this->data['closure_birth']=$birth;
        // Preserve only resolved scalars. Closure is the actual immutable Commit birth, not B's Closure.
        $candidate=new LocalMeasurements($this->source->correspondence,$this->source->chirality,$birth['depth'],$birth['cadence'],$birth['closure']);
        $field=$this->values($candidate);
        $this->data['candidate_commit']=['proposition_id'=>$c->id,'source_id'=>$this->sourceId,'kind'=>'canonical_resolved_baseline','local_expressive_components'=>null,'local_cadence_samples'=>null,'closure_birth'=>$birth,...$field,'direction_before'=>$snapshot->direction_before->value,'direction_after'=>ContextDomain::after($field['tension'],$snapshot->direction_before)->value];
        $this->data['source_references']=['commit_sources'=>$c->commitSources()->pluck('propositions.id')->all(),'own_attestation_ids'=>$c->attestations()->pluck('id')->all(),'effective_source_attestation_ids'=>app(\App\Services\PropositionStateService::class)->attestations(collect([$c]))->pluck('id')->all()];
        $this->save();
        // One real, offered expressive child; identical Domain outcome permits this narrow shared-world probe.
        if($this->data['candidate_commit']['direction_after']!==$snapshot->direction_after->value)throw new \RuntimeException('Candidate Domain differs: cannot reuse production child lifecycle.');
        $state=$service->stateFor($actor);$offer=$state['plan']['schema']['$defs']['actions']['proposition.variant']??null;
        if($offer===null){$this->data['child_stop']='variant_not_offered';$this->save();return;}
        if($state['plan']['gate_id']!==$c->id)throw new \RuntimeException('Variant acting Gate is not C1.');
        $beforeCounts=['attestations'=>Attestation::count(),'receipts'=>Receipt::count()];
        $beforeLocals=[];foreach(Proposition::all() as $p)$beforeLocals[$p->id]=app(LocalMeasurementService::class)->observation($p);
        $input=['action'=>'proposition.variant','statement'=>'First post-Commit expressive child'];
        $service->contribute($actor,$state['plan']['phase']['id'],$input);
        $child=Proposition::where('tier','expressive')->orderByDesc('id')->firstOrFail();
        $provider=new CanonicalBaselineLocal(app(LocalMeasurementService::class),$c->id,$candidate);
        $lineage=new LineageMeasurementService($provider,app(ContextLineageComparisonService::class));
        $positions=$lineage->positions($child)->map(fn($pair)=>['id'=>$pair[0]->id,'distance'=>$pair[1],'weight'=>$lineage::attenuation($pair[1])])->all();
        $candidateChild=$lineage->calculate($child);
        $unchanged=[];foreach($beforeLocals as $id=>$local)$unchanged[$id]=$local===app(LocalMeasurementService::class)->observation(Proposition::findOrFail($id));
        $this->data['child']=['id'=>$child->id,'before_state'=>$state,'input'=>$input,'after_state'=>$service->stateFor($actor),'parents'=>$child->parents()->pluck('propositions.id')->all(),'local'=>app(LocalMeasurementService::class)->observation($child),'raw_local'=>$this->values(app(LocalMeasurementService::class)->calculate($child)),'production_comparator'=>$this->audit($child),'candidate'=>$this->values($candidateChild),'candidate_positions'=>$positions,'canonical_reads'=>$provider->reads,'before_counts'=>$beforeCounts,'after_counts'=>['attestations'=>Attestation::count(),'receipts'=>Receipt::count()],'existing_local_snapshots_unchanged'=>$unchanged,'commit_snapshot_unchanged'=>$snapshot->toArray()===$c->fresh()->commitmentSnapshot->toArray()];
        $this->save();
    }
    private function save():void{file_put_contents(__DIR__.'/hard-edge.json',json_encode($this->data,JSON_PRETTY_PRINT|JSON_PRESERVE_ZERO_FRACTION));}
}
