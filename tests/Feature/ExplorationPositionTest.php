<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Supersession;
use App\Services\ExplorationEpochService;
use App\Services\ExplorationPositionService;
use App\Services\HorizonFailureService;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\SpentContinuationFixture;
use Tests\TestCase;

class ExplorationPositionTest extends TestCase
{
    use RefreshDatabase, SpentContinuationFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    public function test_position_is_shared_across_phase_occurrences_but_not_gates_or_kinds(): void
    {
        $first = $this->phase($this->origin, PhaseKind::Distinguish);
        $second = $this->phase($this->origin, PhaseKind::Distinguish);
        $positions = app(ExplorationPositionService::class);
        $this->assertSame($positions->forPhase($first), $positions->forPhase($second));
        $gate = $this->node($first, $this->origin);
        $other = $this->phase($gate, PhaseKind::Persist);
        $this->assertNotSame($positions->forPhase($first), $positions->forPhase($other));
        $this->assertNotSame($positions->forPhase($first), $positions->forPhase($this->phase($gate, PhaseKind::Distinguish)));
    }

    public function test_revision_chains_retain_the_original_source_in_cold_and_hydrated_reads(): void
    {
        $source = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $original = $this->node($this->phase($source, PhaseKind::Persist), $source);
        $revision = $this->node($this->phase($source, PhaseKind::Persist), $original);
        $latest = $this->node($this->phase($source, PhaseKind::Persist), $revision);
        Supersession::whereIn('target_proposition_id', [$revision->id, $latest->id])->update(['type' => 'revise']);
        $positions = app(ExplorationPositionService::class);
        $expected = $positions->forProposition($original);
        $context = new ParticipationContext($latest->phase);
        foreach ([$original, $revision, $latest] as $node) {
            $this->assertSame($source->id, $positions->forProposition($node)['structural_source_id']);
            $this->assertSame($expected, $positions->forProposition($node, $context));
        }
        DB::table('continuation_failures')->insert(['proposition_id' => $latest->id,
            'context_horizon_id' => $expected['context_horizon_id'], 'structural_position' => $expected['structural_position'],
            'exploration_epoch' => 0, 'phase_id' => $latest->phase_id, 'failed_at' => now()]);
        $epochs = app(ExplorationEpochService::class);
        $this->assertTrue($epochs->spentNow($latest, $latest->phase));
        DB::table('horizon_exploration_states')->insert([...$expected, 'epoch' => 1]);
        foreach ([$original, $revision, $latest] as $node) {
            $this->assertSame(1, $epochs->forProposition($node));
            $this->assertSame(1, $epochs->forProposition($node, new ParticipationContext($latest->phase)));
        }
        $this->assertFalse($epochs->spentNow($latest, $latest->phase));
        $this->assertFalse($epochs->spentNow($latest, $latest->phase, new ParticipationContext($latest->phase)));
        $this->assertDatabaseCount('continuation_failures', 1);

        // An output revision inherits its own slot, but it is still a distinct
        // structural source when a later phase actually traverses that revision.
        $underOriginal = $this->node($this->phase($original, PhaseKind::Disperse), $original);
        $underRevision = $this->node($this->phase($latest, PhaseKind::Disperse), $latest);
        $this->assertSame($original->id, $positions->forProposition($underOriginal)['structural_source_id']);
        $this->assertSame($latest->id, $positions->forProposition($underRevision)['structural_source_id']);
        $this->assertNotSame($positions->forProposition($underOriginal), $positions->forProposition($underRevision));
    }

    public function test_selected_classification_uses_its_edge_and_reconciliation_uses_its_recorded_gate(): void
    {
        $left = $this->node($this->phase($this->origin, PhaseKind::Disperse), $this->origin);
        $right = $this->node($this->phase($this->origin, PhaseKind::Disperse), $this->origin);
        $phase = $this->phase($right, PhaseKind::Mediate);
        $result = $this->node($phase, $left);
        Supersession::where('target_proposition_id', $result->id)->update(['type' => 'reconcile']);
        (new Supersession)->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $right->id,
            'target_proposition_id' => $result->id, 'type' => 'reconcile'])->save();
        $other = $this->node($this->phase($left, PhaseKind::Mediate), $left);
        $classified = $this->node($this->phase($other, PhaseKind::Bias), $result);
        Supersession::where('target_proposition_id', $classified->id)->update(['type' => 'reclassify']);
        $positions = app(ExplorationPositionService::class);
        $context = new ParticipationContext($classified->phase);
        foreach ([null, $context] as $read) {
            $this->assertSame($right->id, $positions->forProposition($result, $read)['structural_source_id']);
            $this->assertSame($result->id, $positions->forProposition($classified, $read)['structural_source_id']);
        }
        $phase->forceFill(['gate_proposition_id' => $this->origin->id])->save();
        $this->assertNull($positions->forProposition($result), 'Ambiguous multi-input provenance must not select the first parent');
    }

    public function test_a_revision_cannot_move_to_another_producing_position(): void
    {
        $source = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $invalid = $this->node($this->phase($source, PhaseKind::Disperse), $source);
        Supersession::where('target_proposition_id', $invalid->id)->update(['type' => 'revise']);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A revision cannot change its exploration position');
        app(ExplorationPositionService::class)->forProposition($invalid);
    }

    public function test_revision_cycles_are_rejected_without_a_provenance_guess(): void
    {
        $first = $this->node($this->phase($this->origin, PhaseKind::Persist), $this->origin);
        $second = $this->node($first->phase, $first);
        Supersession::where('target_proposition_id', $first->id)->update(['type' => 'revise', 'source_proposition_id' => $second->id]);
        Supersession::where('target_proposition_id', $second->id)->update(['type' => 'revise']);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Exploration provenance contains a revision cycle');
        app(ExplorationPositionService::class)->forProposition($second);
    }

    public function test_wrong_producing_kind_never_counts_as_current_spentness(): void
    {
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $node = $this->node($phase, $this->origin);
        DB::table('continuation_failures')->insert(['proposition_id' => $node->id,
            'context_horizon_id' => $phase->cycle->context_horizon_id, 'structural_position' => 4,
            'exploration_epoch' => 0, 'phase_id' => $phase->id, 'failed_at' => now()]);
        $epochs = app(ExplorationEpochService::class);
        $this->assertFalse($epochs->spentNow($node, $phase));
        $this->assertFalse($epochs->spentNow($node, $phase, new ParticipationContext($phase)));
        $this->assertTrue($epochs->currentFailures($phase->cycle->context_horizon_id, collect([$node->id]))->isEmpty());
        $this->assertDatabaseCount('continuation_failures', 1);
    }

    public function test_publication_revalidates_the_structural_source_even_when_epoch_and_version_match(): void
    {
        [, , $terminal] = $this->testedLineage();
        $service = app(HorizonFailureService::class);
        $proof = $service->inspect($terminal);
        $this->assertNotEmpty($proof['failures']);
        $proof['failures'][0]['structural_source_id'] = $this->origin->id;
        $terminal->forceFill(['completed_at' => now()])->save();
        $terminal->cycle->forceFill(['completed_at' => now(), 'rested_at' => now(), 'rest_reason' => $proof['rest_reason']])->save();
        $service->publish($terminal, $proof);
        $this->assertDatabaseCount('continuation_failures', 0);
        $this->assertDatabaseCount('horizon_failure_witnesses', 0);
    }
}
