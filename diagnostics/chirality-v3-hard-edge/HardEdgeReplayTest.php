<?php
namespace Diagnostics\NaturalRhythm;
use App\Models\{Substrate,Participant,Cycle,Attestation,Proposition};
use App\Services\ParticipationService;
use App\Services\Observables\LocalMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HardEdgeReplayTest extends TestCase
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
        $service=app(ParticipationService::class);$recorder=new PostBiasRecorder;
        $service->enter($actors[0],['statement'=>'Origin','attestation'=>['direction'=>'support']]);
        $service->enter($actors[1]);$service->enter($actors[2]);
        $recorder->capture($actors,'origin',$trace[0]);
        $steps=[];foreach($archive['actions'] as $i=>$a)$steps[]=['label'=>'prefix:'.$i,'row'=>$a];
        foreach(array_slice($archive['continuation']['actions'],0,22) as $i=>$a)$steps[]=['label'=>'continuation:'.$i,'row'=>$a];
        $audit=new HardEdgeAudit;$decisions=[];$done=0;$stop='complete_preserved_history';$blocked=null;
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

            $this->assertSame(55,$done);
            $reference=end($recorder->boundaries);
            $r=$reference['states'][0]['gate_id'];
            $this->assertTrue($reference['states'][0]['bias_generation']);
            $this->assertEqualsWithDelta(-0.0137710,$reference['states'][0]['field']['tension'],0.0000001);
            $target=$r;
            for($j=0;$j<12;$j++){
                $live=[];foreach($actors as $i=>$actor)$live[$i]=$service->stateFor($actor);
                $choice=null;$why=null;$options=[];
                if($j===0){
                    $schema=$live[0]['plan']['schema']['$defs']['actions']['proposition.reclassify'];
                    $options=$schema['properties']['receipt_id']['enum'];
                    $provenance=[];foreach($options as $id){$receipt=\App\Models\Receipt::findOrFail($id);$provenance[]=$receipt->toArray();}
                    $this->assertCount(12,$options);
                    $this->assertSame('https://example.test/evidence/2/5',$provenance[0]['reference']);
                    $choice=[0,['action'=>'proposition.reclassify','proposition_id'=>$r,'receipt_id'=>$options[0]]];
                    $why='Offered Bias on R; first Receipt in authoritative schema order, existing fixture convention; no field-based selection.';
                    $decisions[]=['receipt_choices'=>$provenance,'selection_rule'=>$why];
                }else{
                    // Order first: no field threshold is imposed. Dispatch only an offered operation.
                    foreach($live as $i=>$state){
                        $defs=$state['plan']['schema']['$defs']['actions']??[];
                        if(isset($defs['proposition.commit'])){
                            $ids=$defs['proposition.commit']['properties']['proposition_id']['enum']??[];
                            if(in_array($target,$ids,true)){$choice=[$i,['action'=>'proposition.commit','proposition_id'=>$target]];$why='Production offers Commit on the actual Bias result.';break;}
                        }
                    }
                    if($choice===null){
                        $ground=app(\App\Services\CommitmentAdmissibilityService::class)->grounding(Proposition::findOrFail($target));
                        // One genuine vote is allowed only to supply the missing current-source grounding.
                        if(!\App\Models\Attestation::where('proposition_id',$target)->exists()){
                            foreach($live as $i=>$state){
                                $defs=$state['plan']['schema']['$defs']['actions']??[];
                                if(isset($defs['attestation.create'])){
                                    $ids=$defs['attestation.create']['properties']['proposition_id']['enum']??[];
                                    if(in_array($target,$ids,true)){$choice=[$i,['action'=>'attestation.create','proposition_id'=>$target,'direction'=>'support','rationale'=>'Continue the existing supportive authored stance; ground the fresh Bias result.']];$why='Fresh Bias result lacks its own effective Attestation; this existing participant is offered Attest on that source.';break;}
                                }
                            }
                        }
                    }
                }
                if($choice===null){$stop='inspect_next_production_dependency';$blocked=['live'=>$live,'target'=>$target,'grounding'=>app(\App\Services\CommitmentAdmissibilityService::class)->grounding(Proposition::findOrFail($target))];break;}
                [$i,$input]=$choice;
                $decisions[]=['step'=>$j,'actor'=>$i,'input'=>$input,'why'=>$why,'before'=>$live];
                if($input['action']==='proposition.commit')$audit->before(Proposition::findOrFail($target),\App\Models\Phase::findOrFail($live[$i]['plan']['phase']['id']));
                $service->contribute($actors[$i],$live[$i]['plan']['phase']['id'],$input);
                $row=$recorder->capture($actors,'post-bias:'.$j,null,$input);$done++;
                if($j===0){
                    $this->assertCount(1,$row['created']['propositions']);$target=$row['created']['propositions'][0]['id'];
                    $f=$row['events'][0]['field'];
                    $this->assertSame([],$f['local']['own_effective_attestations']);
                    $this->assertSame([],$f['local']['current_history']['intervals']);
                    $this->assertSame(0.0,$f['local']['orientation']);$this->assertSame(0.0,$f['local']['local_chirality']);
                    $this->assertGreaterThan(0,$f['normalized_ancestral_chirality']);
                }
                $recorder->save(['completed_actions'=>$done,'stop'=>'running','decisions'=>$decisions]);
                if(Proposition::where('tier','committal')->count()>1){$stop='first_ordinary_commit';$audit->after(Proposition::where('tier','committal')->orderByDesc('id')->firstOrFail(),$actors[0],$service);break;}
                if(app(LocalMeasurementService::class)->undefined!==null){$stop='new_semantic_boundary';break;}
            }

        }finally{
            $overlaps=[];foreach($recorder->boundaries as $r)foreach($r['states'] as $s)if($s['four_way_overlap']??false)$overlaps[]=['label'=>$r['label'],'actor'=>$s['actor']];
            $recorder->save(['decisions'=>$decisions,'completed_actions'=>$done,'stop'=>$stop,'blocked'=>$blocked,'four_way_overlaps'=>$overlaps,'participants'=>Participant::count(),'ordinary_commits'=>Proposition::where('tier','committal')->count()-1]);
        }
        $this->assertSame(3,Participant::count());
        $x=$audit->data;
        $this->assertSame($x['source']['resolved']['correspondence'],$x['candidate_commit']['correspondence']);
        $this->assertSame($x['source']['resolved']['chirality'],$x['candidate_commit']['chirality']);
        $this->assertSame($x['closure_preview'],$x['closure_birth']);
        $this->assertNull($x['candidate_commit']['local_expressive_components']);
        $this->assertSame([],$x['source_references']['own_attestation_ids']);
        $this->assertSame('settlement',$x['candidate_commit']['direction_after']);
        $this->assertCount(2,$x['child']['candidate_positions']);
        $this->assertSame([0,1],array_column($x['child']['candidate_positions'],'distance'));
        $this->assertSame([1.0,0.25],array_column($x['child']['candidate_positions'],'weight'));
        $this->assertSame([],$x['child']['local']['own_effective_attestations']);
        $this->assertSame([],$x['child']['local']['current_history']['intervals']);
        $this->assertSame(0.0,$x['child']['local']['local_chirality']);
        $this->assertSame(round($x['candidate_commit']['chirality']/5,7),$x['child']['candidate']['chirality']);
        $this->assertSame($x['child']['before_counts'],$x['child']['after_counts']);
        $this->assertNotContains(false,$x['child']['existing_local_snapshots_unchanged']);
        $this->assertTrue($x['child']['commit_snapshot_unchanged']);
        $this->assertGreaterThan(0,$done);
        $this->assertContains($stop,['inspect_next_production_dependency','first_ordinary_commit','complete_preserved_history','first_emergence','archived_action_not_lawful_under_candidate','archived_action_rejected_under_candidate','new_semantic_boundary']);
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
