<?php
namespace Diagnostics\NaturalRhythm;
use App\Models\{Proposition,Phase,Attestation,Receipt,Cycle};
use App\Services\{ParticipationService,ChiralityGateService,CommitmentAdmissibilityService,PhasePositionService,PhaseGenerationService,BiasGateService,BiasReceiptAuthorityService,ProjectionService,PropositionMeasurementService};
use App\Services\Observables\{LineageMeasurementService,LocalMeasurementService};
use Illuminate\Support\Facades\DB;

final class LocalRecorder
{
    public array $boundaries=[];
    public array $idMap=[];
    public array $previous=[];
    public array $locals=[];
    private bool $reading=false;
    public function __construct(){DB::listen(function($q){if($this->reading&&preg_match('/^\s*(insert|update|delete|alter|create|drop)/i',$q->sql))throw new \RuntimeException('Read-only local inspection attempted SQL write');});}
    public function capture(array $actors,string $label,array $archived,?array $input=null):array
    {
        $this->reading=true;
        try{
            $created=[];
            foreach(['propositions'=>Proposition::class,'attestations'=>Attestation::class,'receipts'=>Receipt::class,'cycles'=>Cycle::class] as $name=>$model){
                $ids=$model::orderBy('id')->pluck('id')->all();$new=array_values(array_diff($ids,$this->previous[$name]??[]));
                $old=array_column($archived['created'][$name],'id');sort($old);
                if(count($new)!==count($old))throw new \RuntimeException('Authored record count diverged at '.$label.' '.$name);
                foreach($old as $i=>$id)$this->idMap[$id]=$new[$i];
                $created[$name]=$model::whereIn('id',$new)->get()->map(fn($m)=>$m->toArray())->all();$this->previous[$name]=$ids;
            }
            $local=app(LocalMeasurementService::class);$all=[];$invariants=[];
            foreach(Proposition::with('phase.cycle')->orderBy('id')->get() as $p){
                $d=$local->observation($p);$all[$p->id]=$d;
                if(isset($this->locals[$p->id])){
                    $b=$this->locals[$p->id];$same=$b['signature']===$d['signature'];
                    $stable=$b===$d;
                    if($same&&!$stable)throw new \RuntimeException('Local invariance failed for '.$p->id);
                    $invariants[$p->id]=['own_attestation_changed'=>!$same,'entire_local_snapshot_unchanged'=>$stable];
                }
            }
            $this->locals=$all;$fields=[];
            $field=function(Proposition $p)use(&$fields){return $fields[$p->id]??=$this->field($p);};
            $events=[];
            foreach($created['propositions'] as $p)$events[]=['event'=>'proposition_write','id'=>$p['id'],'field'=>$field(Proposition::findOrFail($p['id']))];
            foreach($created['attestations'] as $a)$events[]=['event'=>($a['supersedes_attestation_id']??null)?'attestation_change':'attestation_create','attestation'=>$a,'field'=>$field(Proposition::findOrFail($a['proposition_id']))];
            $states=[];
            foreach($actors as $i=>$actor){
                $s=app(ParticipationService::class)->stateFor($actor);
                if($s['status']!=='ready'){$states[]=['actor'=>$i,'state'=>$s];continue;}
                $phase=Phase::findOrFail($s['plan']['phase']['id']);$gate=Proposition::findOrFail($s['plan']['gate_id']);
                $f=$field($gate);$bias=$phase->kind->value===6;
                $ground=app(CommitmentAdmissibilityService::class)->grounding($gate);
                $states[]=['actor'=>$i,'cycle'=>$phase->cycle->sequence,'phase'=>$phase->kind->value,'domain'=>$phase->cycle->contextHorizon->domain->value,'gate_id'=>$gate->id,'gate_producing_phase'=>$gate->phase->kind->value,'field'=>$f,'grounding'=>$ground,'available_actions'=>array_keys($s['plan']['schema']['$defs']['actions']),'schema_actions'=>$s['plan']['schema']['$defs']['actions'],'reason'=>$s['reason'],'bias_structurally_reached'=>$bias&&app(PhasePositionService::class)->acceptsGate($phase,$gate),'bias_generation'=>$bias?app(PhaseGenerationService::class)->available($phase,'proposition.reclassify',null,$gate):null,'bias_evidence_sources'=>$bias?app(BiasGateService::class)->forPhase($phase)->pluck('id')->all():null,'bias_authorized_receipts'=>$bias?app(BiasReceiptAuthorityService::class)->receiptsFor($phase,$gate)->pluck('id')->all():null,'four_way_overlap'=>$bias&&$ground['admissible']&&$f['tension']>0&&$f['directions']['admissible']];
            }
            $out=['label'=>$label,'input'=>$input,'created'=>$created,'events'=>$events,'local_snapshots'=>$all,'invariants'=>$invariants,'states'=>$states];
            $this->boundaries[]=$out;
            return $out;
        }finally{$this->reading=false;}
    }
    private function field(Proposition $p):array
    {
        $l=app(LineageMeasurementService::class);$v=$l->calculate($p);$d=app(LocalMeasurementService::class)->observation($p);
        $parts=[];$mass=0.;$ancestral=0.;
        foreach($l->positions($p) as [$member,$distance]){
            $local=app(LocalMeasurementService::class)->observation($member);$weight=$l::attenuation($distance);$mass+=$weight;
            $parts[]=['id'=>$member->id,'distance'=>$distance,'weight'=>$weight,'local_chirality'=>$local['local_chirality'],'weighted_chirality'=>$local['local_chirality']*$weight];
            if($distance>0)$ancestral+=$local['local_chirality']*$weight;
        }
        return ['id'=>$p->id,'statement'=>$p->statement,'producing_phase'=>$p->phase->kind->value,'creating_cycle'=>$p->phase->cycle->sequence,'domain'=>$p->phase->cycle->contextHorizon->domain->value,'parents'=>$p->parents()->pluck('propositions.id')->all(),'local'=>$d,'attenuation_parts'=>$parts,'attenuation_mass'=>$mass,'normalized_ancestral_chirality'=>$ancestral/$mass,'normalized_head_chirality'=>$d['local_chirality']/$mass,'chirality'=>$v->chirality,'correspondence'=>$v->correspondence,'closure'=>$v->closure()->average(),...app(ProjectionService::class)->field($v->observables()),'directions'=>app(ChiralityGateService::class)->evaluate($p),'stored_measurement'=>$p->measurement?->toArray()];
    }
    public function save(array $extra=[]):void
    {
        file_put_contents(__DIR__.'/candidate-trace.json',json_encode(['boundaries'=>$this->boundaries,'identity_map'=>$this->idMap,'local_changes'=>app(LocalMeasurementService::class)->changes,...$extra],JSON_PRETTY_PRINT|JSON_PRESERVE_ZERO_FRACTION));
    }
}
