<?php
namespace Diagnostics\NaturalRhythm;

use App\Models\Proposition;
use App\Models\Attestation;
use App\Models\Phase;
use App\Enums\ObservableDirection;
use App\Services\ChiralityGateService;
use App\Services\PhaseGenerationService;
use App\Services\BiasGateService;
use App\Services\CommitmentAdmissibilityService;
use App\Services\ProjectionService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\Observables\ContextLineageComparisonService;
use App\Services\Observables\LineageMeasurementService;
use Illuminate\Support\Facades\DB;

final class BeatRecorder
{
    private static array $directions=[];
    private static array $seen=[];
    private static array $rows=[];
    private static array $directionChanges=[];
    private static bool $reading=false;
    public static function start():void
    {
        self::$directions=[];self::$seen=[];self::$rows=[];self::$directionChanges=[];
        DB::listen(function($q){if(self::$reading && preg_match('/^\s*(insert|update|delete|alter|create|drop)/i',$q->sql))throw new \RuntimeException('Counterfactual inspection attempted a write');});
    }
    public static function capture():void
    {
        self::$reading=true;
        try {
            foreach(Proposition::orderBy('id')->get() as $p){
                $cmp=app(ContextLineageComparisonService::class)->for($p);
                $a=app(LocalMeasurementService::class)->diagnose($cmp);
                $b=app(OccupiedLocalMeasurements::class)->diagnose($cmp);
                $old=self::$directions[$p->id]??null;
                $entry=$p->phase->cycle->entryCommittal?->commitmentSnapshot;
                $values=new \App\Domain\Observables\ChiralityValues($b['orientation'],$b['attestation_cadence']);
                $dirs=$values->resolveDirections(ObservableDirection::from($old['orientation_direction']??$entry?->orientation_direction??'negative'),ObservableDirection::from($old['attestation_cadence_direction']??$entry?->attestation_cadence_direction??'positive'));
                self::$directions[$p->id]=$dirs;
                $key=json_encode([$a['current_history']['intervals'],$a['orientation'],$a['attestation_cadence'],$b['current_history']['intervals'],$b['attestation_cadence'],$dirs]);
                if((self::$seen[$p->id]??null)!==$key){
                    self::$directionChanges[]=['stage'=>DirectionRecorder::$stage,'proposition_id'=>$p->id,'previous'=>$old,'current'=>$dirs,'A'=>$a,'B'=>$b];
                    self::$seen[$p->id]=$key;
                    self::$rows[]=self::position($p,DirectionRecorder::$stage.' after-change');
                }
            }
        } finally {self::$reading=false;}
    }
    public static function boundary(Phase $phase,Proposition $p,string $label):void
    {
        self::$reading=true;
        try{self::$rows[]=self::position($p,$label,$phase);}finally{self::$reading=false;}
    }
    private static function position(Proposition $p,string $label,?Phase $phase=null):array
    {
        $cmp=app(ContextLineageComparisonService::class)->for($p);
        $local=app(LocalMeasurementService::class);
        $alt=app(OccupiedLocalMeasurements::class);
        $a=app(LineageMeasurementService::class)->calculate($p,$cmp);
        $b=(new LineageMeasurementService($alt,app(ContextLineageComparisonService::class)))->calculate($p,$cmp);
        if($a->correspondence!==$b->correspondence || $a->closure()->average()!==$b->closure()->average() || $a->chiralityComponents->orientation!==$b->chiralityComponents->orientation)throw new \RuntimeException('Unrelated observable changed');
        $ag=app(ChiralityGateService::class)->evaluate($p);
        // An unsaved clone is a read-only value carrier for the real equality and
        // local generation predicates. Never bind B, save it, or schedule from it.
        $virtual=clone $p;
        $measurement=clone $p->measurement;
        $measurement->forceFill([...self::$directions[$p->id],'chirality'=>$b->chirality,'orientation'=>$b->chiralityComponents->orientation,'attestation_cadence'=>$b->chiralityComponents->cadence]);
        $virtual->setRelation('measurement',$measurement);
        $bg=app(ChiralityGateService::class)->evaluate($virtual);
        $bias=$phase!==null&&$phase->kind->value===6;
        $eligible=$bias?app(BiasGateService::class)->forPhase($phase)->contains('id',$p->id):null;
        $pack=function($v,$details,$gate,$node)use($phase,$bias,$eligible){return [...$details,'local_chirality'=>$v->chiralityComponents->average(),'chirality'=>$v->chirality,'correspondence'=>$v->correspondence,'closure'=>$v->closure()->average(),...app(ProjectionService::class)->field($v->observables()),'coherence'=>$gate,'bias_direction_eligible'=>$eligible,'local_bias_generation'=>$bias?app(PhaseGenerationService::class)->available($phase,'proposition.reclassify',null,$node):null,'local_bias_source_admissible'=>$bias?($eligible&&app(PhaseGenerationService::class)->available($phase,'proposition.reclassify',null,$node)):null];};
        $ad=$local->diagnose($cmp);$bd=$alt->diagnose($cmp);
        $positions=OccupiedLocalMeasurements::occupied($ad['current_history'])['act_positions'];
        $acts=[];
        foreach($ad['current_history']['act_ids'] as $i=>$id){
            $act=Attestation::findOrFail($id);$cycle=$act->phase->cycle;
            $acts[]=['id'=>$id,'target'=>$act->proposition_id,'participant_id'=>$cycle->participant_id,'participant'=>$cycle->participant->callsign,'phase_id'=>$act->phase_id,'phase'=>$act->phase->kind->value,'cycle_id'=>$cycle->id,'cycle_sequence'=>$cycle->sequence,'own_cycle_entry_order'=>app(EventOrder::class)->cycles[$cycle->id],'act_order'=>app(EventOrder::class)->acts[$id],'represented_position'=>$positions[$i]];
        }
        return ['label'=>$label,'proposition_id'=>$p->id,'statement'=>$p->statement,'producing_phase'=>$p->phase->kind->value,'phase'=>$phase?->kind->value,'actor'=>$phase?->cycle->participant->callsign,'cycle'=>$phase?->cycle->sequence,'grounding'=>app(CommitmentAdmissibilityService::class)->grounding($p),'acts'=>$acts,'A'=>$pack($a,$ad,$ag,$p),'B'=>$pack($b,$bd,$bg,$virtual)];
    }
    public static function save():void
    {
        file_put_contents(__DIR__.'/beat-trace.json',json_encode(['rows'=>self::$rows,'direction_changes'=>self::$directionChanges],JSON_PRETTY_PRINT|JSON_PRESERVE_ZERO_FRACTION));
    }
}
