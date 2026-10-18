<?php

namespace Tests\Feature;

use App\Models\Proposition;
use App\Services\CognitiveEvidenceHorizon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

/** Recorded graph components, not claims of completed lawful lifecycle execution. */
class CognitiveEvidenceHorizonTest extends TestCase
{
    use RefreshDatabase;

    public function test_origin_and_committed_chain_exclude_expressive_and_future_basins(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $c1 = $f->node($f->gate, 'canon', 'commit', 'committal');
        $e = $f->node($c1, 'activity');
        $c2 = $f->node($e, 'canon', 'commit', 'committal');
        $this->assertEqualsCanonicalizing([$f->seed->id], $this->ids($f->seed, 'distinguish'));
        $this->assertEqualsCanonicalizing([$f->seed->id, $c1->id], $this->ids($c1, 'distinguish'));
        $this->assertEqualsCanonicalizing([$f->seed->id, $c1->id, $c2->id], $this->ids($c2, 'distinguish'));
        $this->assertEqualsCanonicalizing([$c1->id, $e->id], $this->ids($e));
    }

    public function test_selected_branch_does_not_expand_from_ancestors_or_shared_membership(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $e1 = $f->node($f->seed, 'activity');
        $e2 = $f->node($e1, 'frontier');
        $e3 = $f->node($e1, 'frontier');
        $leaf = $f->node($e2, 'canon');
        $this->assertEqualsCanonicalizing([$f->seed->id, $e1->id, $e2->id, $e3->id, $leaf->id], $this->ids($e1));
        $this->assertEqualsCanonicalizing([$f->seed->id, $e1->id, $e2->id, $leaf->id], $this->ids($e2));
        $this->assertEqualsCanonicalizing([$f->seed->id, $e1->id, $e3->id], $this->ids($e3));
    }

    private function ids(Proposition $gate, string $kind = 'persist'): array
    {
        return app(CognitiveEvidenceHorizon::class)->propositionIds([
            'gate_id' => $gate->id, 'phase' => ['kind' => $kind],
        ])->all();
    }
}
