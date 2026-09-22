<?php
namespace Tests\Feature\Api;
use Tests\Support\ScriptedParticipationTestCase;
use App\Services\PotentialProjectionService;
class ClosureEvidenceDiagnosticTest extends ScriptedParticipationTestCase {
 public function test_refuting_observed_structure(): void {
  $j=$this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/tragedy-lifecycle.json',53);
  $actor=$j['participants']['tragic-explorer'];
  $ids=['p14'=>$j['responses'][49]['current_proposition_id'],'p15'=>$j['responses'][50]['current_proposition_id']];
  $receipt=collect($j['responses'][52]['plan']['receipts'])->firstWhere('reference','https://evidence.example.test/observation/55')['id'];
  $inputs=[
   ['action'=>'attestation.create','proposition_id'=>'p15','direction'=>'refute'],
   ['action'=>'proposition.reclassify','proposition_id'=>'p15','receipt_id'=>$receipt],
   ['action'=>'proposition.variant','statement'=>'Testable opportunity 58'],
   ['action'=>'participant.rest'],
   ['action'=>'instrument.execute','proposition_id'=>'p14','instrument'=>['kind'=>'hyperlink','reference'=>'https://evidence.example.test/observation/60']],
   ['action'=>'proposition.traverse','proposition_id'=>'p15'],
   ['action'=>'attestation.create','proposition_id'=>'p16','direction'=>'support'],
   ['action'=>'instrument.execute','proposition_id'=>'p17','instrument'=>['kind'=>'hyperlink','reference'=>'https://evidence.example.test/observation/63']],
   ['action'=>'participant.rest'],
   ['action'=>'attestation.create','proposition_id'=>'p14','direction'=>'refute'],
   ['action'=>'proposition.traverse','proposition_id'=>'p15'],
   ['action'=>'proposition.traverse','proposition_id'=>'p16'],
   ['action'=>'attestation.create','proposition_id'=>'p17','direction'=>'support'],
   ['action'=>'participant.rest'],
  ];
  foreach($inputs as $i=>$input){
   if(isset($input['proposition_id'])) $input['proposition_id']=$ids[$input['proposition_id']];
   $before=$this->readParticipation($actor);
   fwrite(STDERR,"\nDIAG-BEFORE ".json_encode(['step'=>53+$i,'state'=>$before])."\n");
   $state=$this->contribute($actor,$input,$before);
   if($i===1) $ids['p16']=$state['current_proposition_id'];
   if($i===2) $ids['p17']=$state['current_proposition_id'];
  }
  $this->fail('DIAG-END '.json_encode($state));
 }
}
