<?php

namespace Tests\Feature;

use App\Models\Proposition;
use App\Services\CognitiveEvidenceHorizon;
use App\Services\TerminalContinuationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_terminal_boundary_keeps_itself_but_stops_descendants_and_preserves_siblings(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $e1 = $f->node($f->seed, 'activity');
        $e2 = $f->node($e1, 'frontier');
        $e3 = $f->node($e2, 'canon');
        $sibling = $f->node($e1, 'frontier');
        // Isolated historical graph fixture. This is NOT a dispatched encounter.
        // The service still computes authoritative all-child closure from storage.
        $this->terminal($e2);
        $this->assertEqualsCanonicalizing([$f->seed->id, $e1->id, $e2->id, $sibling->id], $this->ids($e1));
        $this->assertEqualsCanonicalizing([$f->seed->id, $e1->id, $e2->id], $this->ids($e2));
        $this->assertEqualsCanonicalizing([$f->seed->id, $e1->id, $sibling->id], $this->ids($sibling));
        $this->assertContains($e2->id, $this->ids($e3), 'Terminality does not erase recorded ancestry.');
        $this->terminal($sibling);
        $this->assertTrue(app(TerminalContinuationService::class)->closed($e1));
        $this->assertEqualsCanonicalizing([$f->seed->id, $e1->id], $this->ids($e1));
        $this->assertContains($e2->id, $this->ids($e2));
    }

    public function test_reconcile_unions_local_operand_ancestry_without_expanding_foreign_history_or_operand_descendants(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $a = $f->node($f->seed, 'activity');
        $b0 = $f->node($f->seed, 'activity');
        $b = $f->node($b0, 'activity');
        $unrelated = $f->node($b0, 'frontier');
        $otherContinuation = $f->node($b, 'frontier');
        $join = $f->node($a, 'frontier', 'reconcile', additionalParent: $b);
        $this->assertContains($b->id, $this->ids($a));
        $this->assertContains($b0->id, $this->ids($a));
        $this->assertNotContains($unrelated->id, $this->ids($a));
        $this->assertNotContains($otherContinuation->id, $this->ids($a));
        $this->assertContains($a->id, $this->ids($b), 'Both approach directions expose direct operands.');
        $this->assertContains($b0->id, $this->ids($join), 'Completed same-basin ancestry follows both recorded parents.');

        $foreign = $f->node($f->gate, 'canon', 'commit', 'committal');
        $fp = $f->node($foreign, 'activity');
        $operand = $f->node($fp, 'activity');
        $result = $f->node($a, 'frontier', 'reconcile', additionalParent: $operand);
        foreach ([$a, $result] as $position) {
            $this->assertContains($operand->id, $this->ids($position));
            $this->assertNotContains($fp->id, $this->ids($position));
            $this->assertNotContains($foreign->id, $this->ids($position));
        }
        $this->terminal($operand);
        $this->assertContains($operand->id, $this->ids($result));
        $this->terminal($a);
        $this->assertNotContains($operand->id, $this->ids($a));
    }

    public function test_live_committed_exit_is_distinct_from_terminal_closure(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $a = $f->node($f->seed, 'activity');
        $b = $f->node($a, 'canon');
        $c = $f->node($a, 'canon', 'commit', 'committal');
        $this->terminal($b);
        $this->assertFalse(app(TerminalContinuationService::class)->closed($a));
        $this->assertContains($b->id, $this->ids($a));
        $this->assertNotContains($c->id, $this->ids($a));
    }

    private function terminal(Proposition $node): void
    {
        DB::table('terminal_encounters')->insert([
            'id' => strtolower((string) str()->ulid()), 'proposition_id' => $node->id,
            'phase_id' => $node->phase_id, 'participant_id' => $node->phase->cycle->participant_id,
            'governing_committal_id' => $node->phase->cycle->entry_committal_id,
            'action' => 'attestation.create', 'field' => json_encode(['fixture' => 'isolated-terminal-graph']),
            'observed_field_version' => 0, 'occurred_at' => now(),
        ]);
    }

    private function ids(Proposition $gate, string $kind = 'persist'): array
    {
        return app(CognitiveEvidenceHorizon::class)->propositionIds([
            'gate_id' => $gate->id, 'phase' => ['kind' => $kind],
        ])->all();
    }
}
