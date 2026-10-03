<?php
namespace Diagnostics\NaturalRhythm;
use App\Models\{Substrate,Participant,Cycle,Attestation,Proposition};
use App\Services\ParticipationService;
use App\Services\Observables\LocalMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReconcileReplayTest extends TestCase
{
    use RefreshDatabase;
    public function test_exact_history_with_local_reconcile_cursor():void
    {
        $this->app->singleton(EventOrder::class);
        $this->app->singleton(PropositionLocalMeasurements::class);
        $this->app->singleton(LocalMeasurementService::class,LocalReconcileMeasurements::class);
        \App\Models\Supersession::created(fn($e)=>app(LocalMeasurementService::class)->registerBirth($e));
        $order=app(EventOrder::class);
        Cycle::created(function($c)use($order){$order->cycles[$c->id]=++$order->clock;});
        Attestation::created(function($a)use($order){$order->acts[$a->id]=++$order->clock;});
        $archive=json_decode(file_get_contents(__DIR__.'/../chirality-v3-positive-window/result-3-sparse_5.json'),true);
        $trace=json_decode(file_get_contents(__DIR__.'/../chirality-v3-positive-window/window-trace.json'),true)['boundaries'];
        $sub=(new Substrate)->forceFill(['slug'=>'natural-rhythm','title'=>'Natural rhythm','visibility'=>'public','configuration'=>[]]);$sub->save();
        $actors=[];
        for($i=0;$i<3;$i++){$p=(new Participant)->forceFill(['substrate_id'=>$sub->id,'callsign'=>'author-'.$i,'max_read_count'=>25]);$p->save();$actors[]=$p;}
        $service=app(ParticipationService::class);$recorder=new ReconcileRecorder;
        $service->enter($actors[0],['statement'=>'Origin','attestation'=>['direction'=>'support']]);
        $service->enter($actors[1]);$service->enter($actors[2]);
        $recorder->capture($actors,'origin',$trace[0]);
        $steps=[];foreach($archive['actions'] as $i=>$a)$steps[]=['label'=>'prefix:'.$i,'row'=>$a];
        foreach($archive['continuation']['actions'] as $i=>$a)$steps[]=['label'=>'continuation:'.$i,'row'=>$a];
        $done=0;$stop='complete_preserved_history';$blocked=null;
        try{
            foreach($steps as $index=>$step){
                $old=$step['row'];$actor=$actors[$old['actor']];$state=$service->stateFor($actor);
                $oldState=$trace[$index]['states'][$old['actor']];
                $this->assertSame($oldState['phase'],$state['plan']['phase']['number']);
                $this->assertSame($oldState['cycle'],$state['cycle']['sequence']);
                $this->assertSame($recorder->idMap[$oldState['gate_id']],$state['plan']['gate_id']);
                $input=$old['input'];foreach(['proposition_id','other_proposition_id','receipt_id'] as $key)if(isset($input[$key]))$input[$key]=$recorder->idMap[$input[$key]]??throw new \RuntimeException('Unmapped authored target '.$key);
                if(!isset($state['plan']['schema']['$defs']['actions'][$input['action']])){
                    $stop='archived_action_not_lawful_under_candidate';$blocked=['label'=>$step['label'],'actor'=>$old['actor'],'input'=>$input,'actual_state'=>$state];break;
                }
                try{$service->contribute($actor,$state['plan']['phase']['id'],$input);}
                catch(SemanticBoundary $e){$stop='new_semantic_boundary';$blocked=['label'=>$step['label'],'input'=>$input,'error'=>$e->getMessage()];break;}
                catch(\DomainException|\Illuminate\Validation\ValidationException|\Symfony\Component\HttpKernel\Exception\HttpException $e){$stop='archived_action_rejected_under_candidate';$blocked=['label'=>$step['label'],'input'=>$input,'error'=>get_class($e).': '.$e->getMessage(),'actual_state'=>$service->stateFor($actor)];break;}
                $row=$recorder->capture($actors,$step['label'],$trace[$index+1],$input);$done++;
                foreach($row['states'] as $i=>$s){
                    foreach(['correspondence','closure'] as $key)$this->assertSame($trace[$index+1]['states'][$i]['field'][$key],$s['field'][$key],$step['label'].' unchanged '.$key);
                    $this->assertEqualsWithDelta($s['field']['chirality'],$s['field']['normalized_ancestral_chirality']+$s['field']['normalized_head_chirality'],0.00000006);
                }
                foreach($row['events'] as $e)if($e['event']==='proposition_write'&&$e['field']['producing_phase']!==7){
                    $this->assertSame([],$e['field']['local']['own_effective_attestations']);
                    $this->assertSame(0.0,$e['field']['local']['orientation']);
                    $this->assertSame(0.0,$e['field']['local']['attestation_cadence']);
                    $this->assertSame(0.0,$e['field']['local']['local_chirality']);
                }
                if(Proposition::where('tier','committal')->count()>1){
                    $latest=Proposition::where('tier','committal')->orderByDesc('id')->first()->commitmentSnapshot;
                    if($latest->direction_before===\App\Enums\ContextDomain::Settlement&&$latest->direction_after===\App\Enums\ContextDomain::Emergence){$stop='first_emergence';break;}
                }
                // Boundaries are checkpointed so a runtime limit cannot erase evidence.
                $recorder->save(['completed_actions'=>$done,'stop'=>'running']);
                fwrite(STDERR,"\nLOCAL_STEP ".json_encode(['label'=>$step['label'],'action'=>$input['action']])."\n");
            }
        }finally{
            $overlaps=[];foreach($recorder->boundaries as $r)foreach($r['states'] as $s)if($s['four_way_overlap']??false)$overlaps[]=['label'=>$r['label'],'actor'=>$s['actor']];
            $recorder->save(['completed_actions'=>$done,'stop'=>$stop,'blocked'=>$blocked,'four_way_overlaps'=>$overlaps,'participants'=>Participant::count(),'ordinary_commits'=>Proposition::where('tier','committal')->count()-1]);
        }
        $this->assertSame(3,Participant::count());
        $this->assertGreaterThan(0,$done);
        $this->assertContains($stop,['complete_preserved_history','first_emergence','archived_action_not_lawful_under_candidate','archived_action_rejected_under_candidate','new_semantic_boundary']);
        $this->assertGreaterThanOrEqual(52,$done);
        $rows=collect($recorder->boundaries)->keyBy('label');
        $q=$rows['continuation:5']['events'][0]['field'];
        $this->assertSame([0,0],$q['local']['current_history']['intervals']);
        $this->assertTrue($q['local']['current_history']['zero_mean_regular']);
        $this->assertSame(0.0,$q['local']['current_history']['cv']);
        $this->assertNull($q['local']['current_history']['raw_cv']);
        $this->assertSame(1.0,$q['local']['attestation_cadence']);
        $this->assertSame('positive',$q['directions']['attestation_cadence_direction']);
        $mixed=$rows['continuation:4']['events'][0]['field']['local'];
        $this->assertSame([10,0],$mixed['current_history']['intervals']);
        $this->assertFalse($mixed['current_history']['zero_mean_regular']);
        $this->assertSame(1.0,$mixed['current_history']['cv']);
        $this->assertSame(0.0,$mixed['attestation_cadence']);
        $r=$rows['continuation:18']['events'][0]['field'];
        $this->assertCount(2,$r['parents']);
        $this->assertSame([],$r['local']['own_effective_attestations']);
        $this->assertSame([],$r['local']['current_history']['intervals']);
        $this->assertSame(0.0,$r['local']['orientation']);
        $this->assertSame(0.0,$r['local']['local_chirality']);
        $this->assertSame(27,$r['local']['previous_boundary_beat']);
        $incoming=app(LocalMeasurementService::class)->reconcileInputs[$r['id']];
        $this->assertSame([27,23],array_column($incoming,'endpoint_order'));
        $this->assertSame([true,false],array_column($incoming,'selected'));
        $this->assertSame($r['ancestry_partition']['acting_source'],$incoming[0]['parent_id']);
        $this->assertGreaterThan(0,$r['ancestry_partition']['local_only']);
        $this->assertGreaterThan(0,$r['ancestry_partition']['other_only']);
        $this->assertEqualsWithDelta($r['normalized_ancestral_chirality'],$r['ancestry_partition']['local_only']+$r['ancestry_partition']['other_only']+$r['ancestry_partition']['shared_once'],0.00000006);
        foreach($recorder->boundaries as $row)if(isset($row['invariants'][$q['id']])){
            if(!$row['invariants'][$q['id']]['own_attestation_changed'])$this->assertTrue($row['invariants'][$q['id']]['entire_local_snapshot_unchanged']);
        }
    }
}
