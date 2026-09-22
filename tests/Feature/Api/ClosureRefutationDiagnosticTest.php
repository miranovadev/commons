<?php
namespace Tests\Feature\Api;
use Tests\Support\ClosureDiagnosticParticipationCase;
#[\PHPUnit\Framework\Attributes\Group('integration')]
class ClosureRefutationDiagnosticTest extends ClosureDiagnosticParticipationCase {
 public function test_authored_refutation_journey(): void {
  $j=$this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/tragedy-lifecycle.json');
  $this->assertDatabaseCount('tragedies',1);
  $this->assertDatabaseCount('commit_region_exhaustions',1);
 }
}
