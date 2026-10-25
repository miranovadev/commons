<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Supersession;
use App\Services\Participation\ParticipationContext;
use App\Services\StructuralSourceService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\SpentContinuationFixture;
use Tests\TestCase;

class StructuralSourceTest extends TestCase
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
        $positions = app(StructuralSourceService::class);
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
        $positions = app(StructuralSourceService::class);
        $expected = $positions->forProposition($original);
        $context = new ParticipationContext($latest->phase);
        foreach ([$original, $revision, $latest] as $node) {
            $this->assertSame($source->id, $positions->forProposition($node)['structural_source_id']);
            $this->assertSame($expected, $positions->forProposition($node, $context));
        }
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
        $positions = app(StructuralSourceService::class);
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
        $this->expectExceptionMessage('A revision cannot change its structural position');
        app(StructuralSourceService::class)->forProposition($invalid);
    }

    public function test_revision_cycles_are_rejected_without_a_provenance_guess(): void
    {
        $first = $this->node($this->phase($this->origin, PhaseKind::Persist), $this->origin);
        $second = $this->node($first->phase, $first);
        Supersession::where('target_proposition_id', $first->id)->update(['type' => 'revise', 'source_proposition_id' => $second->id]);
        Supersession::where('target_proposition_id', $second->id)->update(['type' => 'revise']);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Structural provenance contains a revision cycle');
        app(StructuralSourceService::class)->forProposition($second);
    }
}
