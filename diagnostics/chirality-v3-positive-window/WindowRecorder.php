<?php
namespace Diagnostics\NaturalRhythm;

use App\Models\{Proposition,Phase,Attestation,Receipt,Cycle};
use App\Services\{ParticipationService,ChiralityGateService,CommitmentAdmissibilityService,PropositionStateService,AttestationEvidenceService,PhasePositionService,PhaseGenerationService,PropositionAvailabilityService,BiasGateService,BiasReceiptAuthorityService,HorizonReceiptService,ReconciliationOptionService,MediationReadService,CorrespondenceGateService,ProjectionService};
use App\Services\Observables\{ContextLineageComparisonService,LineageMeasurementService,LocalMeasurementService,ChiralityCalculator};
use App\Domain\Observables\ObservableValues;
use Illuminate\Support\Facades\DB;

/** Observe all three current actors after every preserved contribution. No writes. */
final class WindowRecorder
{
    private static array $rows=[];
    private static array $previous=[];
    private static ?array $positive=null;
    private static bool $reading=false;
    public static function start():void
    {
        self::$rows=[];self::$previous=[];self::$positive=null;
        DB::listen(function($q){if(self::$reading&&preg_match('/^\s*(insert|update|delete|alter|create|drop)/i',$q->sql))throw new \RuntimeException('Read-only window inspection attempted a write');});
    }
    public static function capture(array $actors,string $label):void
    {
        self::$reading=true;
        try {
            $created=[];
            foreach(['propositions'=>Proposition::class,'attestations'=>Attestation::class,'receipts'=>Receipt::class,'cycles'=>Cycle::class] as $name=>$model){
                $ids=$model::pluck('id')->all();
                $new=array_values(array_diff($ids,self::$previous[$name]??[]));
                $created[$name]=$model::whereIn('id',$new)->get()->map(fn($m)=>$m->toArray())->all();
                self::$previous[$name]=$ids;
            }
            $states=[];
            foreach($actors as $i=>$actor){
                $s=app(ParticipationService::class)->stateFor($actor);
                if($s['status']!=='ready'){$states[]=['actor'=>$i,'state'=>$s];continue;}
                $phase=Phase::findOrFail($s['plan']['phase']['id']);
                $gate=Proposition::findOrFail($s['plan']['gate_id']);
                $row=['actor'=>$i,'phase'=>$phase->kind->value,'phase_id'=>$phase->id,'cycle'=>$phase->cycle->sequence,'cycle_id'=>$phase->cycle_id,'domain'=>$phase->cycle->contextHorizon->domain->value,'current_proposition_id'=>$s['current_proposition_id'],'gate_id'=>$gate->id,'gate_producing_phase'=>$gate->phase->kind->value,'gate_statement'=>$gate->statement,'available_actions'=>array_keys($s['plan']['schema']['$defs']['actions']),'schema_actions'=>$s['plan']['schema']['$defs']['actions'],'state_reason'=>$s['reason'],'field'=>self::field($gate),'predicates'=>self::predicates($phase,$gate)];
                if($phase->kind->value===5){
                    $row['mediation']=['reads'=>app(MediationReadService::class)->readsFor($phase)->pluck('id')->all(),'gates'=>app(MediationReadService::class)->gatesFor($phase)->pluck('id')->all(),'reconcile_options'=>app(ReconciliationOptionService::class)->forPhase($phase)->pluck('id')->all(),'gate_correspondence'=>app(CorrespondenceGateService::class)->evaluate($gate)];
                }
                if($label==='continuation:0'&&$i===1){
                    self::$positive=$row;
                    $row['counterfactual_bias_structural_only']=['assumed'=>'Current phase, producing-phase position and regional structural membership only. No hypothetical Gate or Phase is created.','nonstructural_source_conditions'=>self::conditions($phase,$gate),'note'=>'These use this exact source and this Cycle Receipt catalogue; Tension is not a Bias admission predicate. Actual phase remains Constrain.'];
                }
                if($phase->kind->value===6&&self::$positive!==null){
                    $virtual=clone $gate;$measurement=clone $gate->measurement;
                    $f=self::$positive['field'];
                    $measurement->forceFill(['correspondence'=>$f['correspondence'],'closure'=>$f['closure'],'chirality'=>$f['chirality'],'orientation'=>$f['orientation'],'attestation_cadence'=>$f['attestation_cadence'],'orientation_direction'=>$f['directions']['orientation_direction'],'attestation_cadence_direction'=>$f['directions']['attestation_cadence_direction']]);
                    $virtual->setRelation('measurement',$measurement);
                    $row['counterfactual_frozen_positive_field']=['field'=>['correspondence'=>$f['correspondence'],'chirality'=>$f['chirality'],'closure'=>$f['closure'],...app(ProjectionService::class)->field(new ObservableValues($f['correspondence'],$f['chirality'],$f['closure']))],'coherence'=>app(ChiralityGateService::class)->evaluate($virtual),'local_generation'=>app(PhaseGenerationService::class)->available($phase,'proposition.reclassify',null,$virtual),'actual_evidence_eligible'=>app(BiasGateService::class)->forPhase($phase)->contains('id',$gate->id),'grounding_unchanged'=>$row['predicates']['grounding'],'note'=>'Unsaved value carrier only. No action or future Commit is evaluated.'];
                }
                $row['four_way_overlap']=$phase->kind->value===6&&$row['predicates']['accepts_gate']&&$row['predicates']['grounding']['admissible']&&$row['field']['tension']>0&&$row['field']['directions']['admissible'];
                $states[]=$row;
            }
            self::$rows[]=['label'=>$label,'created'=>$created,'states'=>$states];
        } finally {self::$reading=false;}
    }
    private static function field(Proposition $p):array
    {
        $cmp=app(ContextLineageComparisonService::class)->for($p);
        $v=app(LineageMeasurementService::class)->calculate($p,$cmp);
        return ['correspondence'=>$v->correspondence,'chirality'=>$v->chirality,'closure'=>$v->closure()->average(),'local_chirality'=>$v->chiralityComponents->average(),...app(LocalMeasurementService::class)->diagnose($cmp),...app(ProjectionService::class)->field($v->observables()),'directions'=>app(ChiralityGateService::class)->evaluate($p),'stored_measurement'=>$p->measurement?->toArray()];
    }
    private static function conditions(Phase $phase,Proposition $p):array
    {
        $evidence=app(ChiralityCalculator::class)->directionFor($p);
        $ancestry=app(AttestationEvidenceService::class)->lineageDirectionFor($p);
        return ['mutable'=>app(PropositionAvailabilityService::class)->mutable($p),'expressive'=>$p->tier==='expressive','not_committed'=>!app(PropositionAvailabilityService::class)->committed($p),'effective_attestation'=>app(PropositionStateService::class)->attestations(collect([$p]))->isNotEmpty(),'source_evidence_direction'=>$evidence->value,'ancestry_evidence_direction'=>$ancestry?->value,'evidence_ancestry_agree'=>$evidence===$ancestry,'component_coherence'=>app(ChiralityGateService::class)->evaluate($p),'unused_source_write_register'=>!DB::table('reclassification_results')->where('source_proposition_id',$p->id)->where('register',$phase->write_register)->exists(),'ordinary_receipt_ids'=>app(HorizonReceiptService::class)->ordinaryAvailable($phase)->pluck('id')->all()];
    }
    private static function predicates(Phase $phase,Proposition $p):array
    {
        $bias=$phase->kind->value===6;
        return ['accepts_gate'=>app(PhasePositionService::class)->acceptsGate($phase,$p),'grounding'=>app(CommitmentAdmissibilityService::class)->grounding($p),'effective_attestations'=>app(PropositionStateService::class)->attestations(collect([$p]))->map(fn($a)=>$a->only(['id','phase_id','proposition_id','direction']))->all(),'evidence_receipt_ids'=>app(PropositionStateService::class)->evidenceReceipts($p)->pluck('id')->all(),'source_conditions'=>self::conditions($phase,$p),'reclassify_generation'=>in_array($phase->kind->value,[3,6])?app(PhaseGenerationService::class)->available($phase,'proposition.reclassify',null,$p):null,'bias_evidence_eligible'=>$bias?app(BiasGateService::class)->forPhase($phase)->pluck('id')->all():null,'bias_operational_sources'=>$bias?app(BiasGateService::class)->operationalFor($phase)->pluck('id')->all():null,'bias_authorized_receipts'=>$bias?app(BiasReceiptAuthorityService::class)->receiptsFor($phase,$p)->pluck('id')->all():null];
    }
    public static function save():array
    {
        $overlaps=[];foreach(self::$rows as $row)foreach($row['states'] as $s)if($s['four_way_overlap']??false)$overlaps[]=['label'=>$row['label'],'actor'=>$s['actor']];
        $out=['boundaries'=>self::$rows,'four_way_overlaps'=>$overlaps];
        file_put_contents(__DIR__.'/window-trace.json',json_encode($out,JSON_PRETTY_PRINT|JSON_PRESERVE_ZERO_FRACTION));
        return $out;
    }
}
