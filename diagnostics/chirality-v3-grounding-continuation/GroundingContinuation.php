<?php
namespace Diagnostics\NaturalRhythm;

use App\Enums\ContextDomain;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\CommitmentAdmissibilityService;
use App\Services\ParticipationService;
use App\Services\ProjectionService;
use App\Services\Observables\ContextLineageComparisonService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\LocalMeasurementService;

/** Ordinary schema-selected contributions only. No field values enter scheduling. */
final class GroundingContinuation
{
    public static function run(array $actors, array $prefix): array
    {
        $service=app(ParticipationService::class);
        $out=['fork_before_archived_step'=>33,'bound'=>80,'actions'=>[],'stop'=>'continuation_bound','commit'=>null];
        $worked=[];
        $instrumented=[];
        foreach ($prefix as $action) {
            if ($action['input']['action'] !== 'participant.rest') $worked[$action['cycle_id']]=true;
            if ($action['input']['action'] === 'instrument.execute') $instrumented[$action['phase_id']]=true;
        }
        $out['fork_states']=self::states($actors);
        try {
            for($step=0;$step<80;$step++) {
                $offers=[];
                foreach($actors as $i=>$actor) {
                    $state=$service->stateFor($actor);
                    if($state['status']!=='ready')continue;
                    $phase=Phase::findOrFail($state['plan']['phase']['id']);
                    $gate=Proposition::findOrFail($state['plan']['gate_id']);
                    $offers[]=['actor'=>$i,'state'=>$state,'phase'=>$phase,'gate'=>$gate,
                        'defs'=>$state['plan']['schema']['$defs']['actions'],
                        'grounding'=>app(CommitmentAdmissibilityService::class)->grounding($gate)];
                }
                usort($offers,fn($a,$b)=>($b['phase']->kind->value <=> $a['phase']->kind->value) ?: ($a['actor'] <=> $b['actor']));
                $choice=null;
                foreach($offers as $offer) {
                    $input=self::input($offer,'proposition.commit',$step);
                    if($input!==null){$choice=$offer+['input'=>$input,'why'=>'stop at first available ordinary Commit'];break;}
                }
                // First answer an actual source-grounding dependency from a participant
                // whose current Write legally exposes that exact target.
                foreach($offers as $blocked) {
                    if($choice!==null)break;
                    if(!in_array($blocked['phase']->kind->value,[3,6,7]))continue;
                    if($blocked['grounding']['requirements']['attestation'])continue;
                    foreach($offers as $helper) {
                        $input=self::input($helper,'attestation.create',$step,$blocked['gate']->id);
                        if($input!==null){$choice=$helper+['input'=>$input,'why'=>'attest exact source needed by A'.$blocked['actor'].' P'.$blocked['phase']->kind->value];break 2;}
                    }
                    if(!$blocked['grounding']['requirements']['receipt'])foreach($offers as $helper) {
                        $input=self::input($helper,'instrument.execute',$step,$blocked['gate']->id);
                        if($input!==null&&!isset($instrumented[$helper['phase']->id])){$choice=$helper+['input'=>$input,'why'=>'obtain evidence for exact ungrounded source'];break 2;}
                    }
                }
                // Fixed lifecycle-first policy. Supports use only positive authored
                // stances; no Refute flip, repeated instrument, or field optimization.
                if($choice===null)foreach(['proposition.commit','proposition.reclassify','proposition.reconcile','proposition.traverse','attestation.create','instrument.execute','proposition.variant','participant.rest'] as $action) {
                    foreach($offers as $offer) {
                        if($action==='instrument.execute'&&isset($instrumented[$offer['phase']->id]))continue;
                        if($action==='participant.rest'&&!isset($worked[$offer['state']['cycle']['id']]))continue;
                        $input=self::input($offer,$action,$step);
                        if($input!==null){$choice=$offer+['input'=>$input,'why'=>'fixed field-blind lifecycle/support priority'];break 2;}
                    }
                }
                if($choice===null){$out['stop']='no_policy_action';break;}
                $i=$choice['actor']; $input=$choice['input']; $actor=$actors[$i];
                $before=BoundaryInspector::inspect($actor,$choice['state']);
                $row=['step'=>$step,'actor'=>$i,'why'=>$choice['why'],'input'=>$input,'before'=>$before,
                    'event_order_before'=>app(EventOrder::class)->clock];
                if($input['action']==='attestation.create')$row['target_before']=self::position(Proposition::findOrFail($input['proposition_id']));
                if($input['action']==='proposition.commit'){
                    $cmp=app(ContextLineageComparisonService::class)->prospectiveCommit($choice['gate'],$choice['phase']);
                    $head=$cmp->current->propositions->last();
                    $row['prospective_commit']=self::position($head,$cmp);
                }
                $service->contribute($actor,$choice['phase']->id,$input);
                if($input['action']==='instrument.execute')$instrumented[$choice['phase']->id]=true;
                if($input['action']!=='participant.rest')$worked[$choice['state']['cycle']['id']]=true;
                $after=$service->stateFor($actor);
                $row['after_actor']=['status'=>$after['status'],'phase'=>$after['plan']['phase']??null,
                    'domain'=>$after['cycle']['domain']??null,'plan'=>$after['plan']??null];
                if($input['action']==='attestation.create')$row['target_after']=self::position(Proposition::findOrFail($input['proposition_id']));
                if($step===0)$row['all_states_after_first_attestation']=self::states($actors);
                $row['returned_successfully']=true;
                $out['actions'][]=$row;
                fwrite(STDERR,"\nGROUNDING_STEP ".json_encode(['step'=>$step,'actor'=>$i,'phase'=>$choice['phase']->kind->value,'action'=>$input['action']])."\n");
                if($input['action']==='proposition.commit'){
                    $commit=Proposition::findOrFail($actor->fresh()->current_proposition_id);
                    $out['commit']=$commit->commitmentSnapshot->toArray();
                    $out['stop']='first_ordinary_commit';break;
                }
            }
        } catch (\Throwable $e) {
            $out['stop']='exception';$out['error']=get_class($e).': '.$e->getMessage();
            throw $e;
        } finally {
            $out['final_states']=self::states($actors);
            file_put_contents(__DIR__.'/continuation-checkpoint.json',json_encode($out,JSON_PRETTY_PRINT|JSON_PRESERVE_ZERO_FRACTION));
        }
        return $out;
    }
    private static function states(array $actors):array
    {
        $out=[];
        foreach($actors as $i=>$actor){$s=app(ParticipationService::class)->stateFor($actor);
            $out[]=['actor'=>$i,'status'=>$s['status'],...($s['status']==='ready'?BoundaryInspector::inspect($actor,$s):['state'=>$s])];}
        return $out;
    }
    private static function input(array $offer,string $action,int $step,?string $target=null):?array
    {
        $d=$offer['defs'][$action]??null;if($d===null)return null;
        $input=['action'=>$action];
        if($action==='attestation.create'){
            foreach($d['anyOf'] as $branch){
                if(!in_array('support',$branch['properties']['direction']['enum']))continue;
                $ids=$branch['properties']['proposition_id']['enum'];
                if($target!==null&&!in_array($target,$ids))continue;
                return $input+['proposition_id'=>$target??$ids[0],'direction'=>'support'];
            }
            return null;
        }
        foreach(['proposition_id','other_proposition_id','receipt_id'] as $key){
            if(isset($d['properties'][$key]['enum'])){
                $ids=$d['properties'][$key]['enum'];if(!$ids)return null;
                if($key==='proposition_id'&&$target!==null){if(!in_array($target,$ids))return null;$input[$key]=$target;}
                else $input[$key]=$ids[0];
            }
        }
        if($action==='proposition.reclassify'&&isset($d['anyOf']))foreach($d['anyOf'] as $branch){
            if(($branch['properties']['proposition_id']['const']??null)===($input['proposition_id']??null)){
                $r=$branch['properties']['receipt_id'];$input['receipt_id']=$r['const']??$r['enum'][0];break;
            }
        }
        if($action==='instrument.execute')$input['instrument']=['kind'=>'hyperlink','reference'=>'https://example.test/grounding/'.$offer['actor'].'/'.$step];
        if($action==='proposition.variant')$input['statement']='Author '.$offer['actor'].' grounding continuation '.$step;
        return $input;
    }
    private static function position(Proposition $p,$comparison=null):array
    {
        $comparison??=app(ContextLineageComparisonService::class)->for($p);
        $values=app(LineageMeasurementService::class)->calculate($p,$comparison);
        $details=app(LocalMeasurementService::class)->diagnose($comparison);
        $field=app(ProjectionService::class)->field($values->observables());
        return ['id'=>$p->id,'tier'=>$p->tier,'producing_phase'=>$p->phase->kind->value,
            'correspondence'=>$values->correspondence,'chirality'=>$values->chirality,'closure'=>$values->closure()->average(),
            ...$field,...$details,'grounding'=>app(CommitmentAdmissibilityService::class)->grounding($p)];
    }
}
