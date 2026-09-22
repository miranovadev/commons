<?php
namespace Tests\Feature\Api;
use Tests\Support\ScriptedParticipationTestCase;
use App\Services\PotentialProjectionService;
use App\Services\PotentialRegionCalculator;
class ClosureReplayDiagnosticTest extends ScriptedParticipationTestCase {
 public function test_inspect_new_terminal_potential(): void {
  $j=$this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/tragedy-lifecycle.json',65);
  $actor=$j['participants']['tragic-explorer'];
  $before=$this->readParticipation($actor);
  $state=$this->contribute($actor,$this->nextScriptInput(),$before);
  $local=app(PotentialProjectionService::class)->region($state['context_horizon_id'])['local'];
  $aliases=[];
  foreach($j['responses'] as $i=>$r) $aliases[$r['current_proposition_id']] ??= $i;
  $this->fail('DIAGNOSTIC '.json_encode(['state'=>$state,'aliases'=>$aliases,'local'=>$local,'candidates'=>app(PotentialRegionCalculator::class)->candidates($local,shadowCompletedRegions:false)]));
 }
}
