<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Supersession;
use App\Services\ActiveParticipantLineage;
use App\Services\ContributionDispatcher;
use App\Services\LedgerService;
use App\Services\PhaseProgressionService;
use App\Services\PropositionMeasurementService;
use App\Services\PropositionOptionService;
use App\Services\TerminalContinuationService;
use App\Services\TragedyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\ParticipantExplorationFixture;
use Tests\TestCase;

class SiblingRevisionTest extends TestCase
{
    use ParticipantExplorationFixture, RefreshDatabase;

    public function test_unchanged_evidence_does_not_reopen_a_personal_judgment(): void
    {
        [$a, $b, $c, $x, $terminal] = $this->personalJourney();
        $phase = $this->returnAfterRest($terminal);
        foreach ([$a, $b, $c] as $node) {
            $phase = app(PhaseProgressionService::class)->advance($phase, $node);
        }
        // The final expression's judgment followed every fixture observation.
        self::assertNotContains($x->id, app(PropositionOptionService::class)->attestationOptionsFor($phase)->pluck('id'));
    }

    private function laterRefutedPosition(): array
    {
        [$a, $b, $c, $x, $terminal] = $this->personalJourney(headDirection: 'refute');
        $this->receiptFixture($x);
        $this->publishPotentialFixture($this->substrate);
        $phase = $this->returnAfterRest($terminal);
        foreach ([$a, $b, $c] as $node) {
            $phase = app(PhaseProgressionService::class)->advance($phase, $node);
        }

        return [$c, $x, $phase];
    }

    public function test_revision_has_soft_edge_from_shared_parent_and_hard_edge_from_source(): void
    {
        [$parent, $x, $phase] = $this->laterRefutedPosition();
        $y = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.revise', 'proposition_id' => $x->id, 'statement' => 'A same-position alternative.']);
        self::assertSame($x->phase->kind, $y->phase->kind);
        self::assertDatabaseHas('supersessions', ['source_proposition_id' => $x->id, 'target_proposition_id' => $y->id, 'type' => 'revise']);
        self::assertDatabaseHas('supersessions', ['source_proposition_id' => $parent->id, 'target_proposition_id' => $y->id, 'type' => 'variant']);
    }

    public function test_existing_continuation_does_not_prohibit_a_sibling_revision(): void
    {
        [, $x, $phase] = $this->laterRefutedPosition();
        $this->node($this->phase($x, PhaseKind::Mediate), $x);
        self::assertContains($x->id, app(PropositionOptionService::class)->revisionOptionsFor($phase, $phase->cycle->participant)->pluck('id'));
    }

    public function test_a_terminal_revision_child_keeps_its_source_closed_to_further_revision(): void
    {
        [, $source, $phase] = $this->laterRefutedPosition();
        // A live branch keeps the source itself out of terminal path propagation.
        $this->node($this->phase($source, PhaseKind::Mediate), $source);
        $participant = $phase->cycle->participant;
        $input = ['action' => 'proposition.revise', 'proposition_id' => $source->id, 'statement' => 'Revision at the boundary'];
        $child = app(ContributionDispatcher::class)->dispatch($participant, $phase, $input);
        $this->controlledBirthClosureFixture(1, $child->id);
        app(PropositionMeasurementService::class)->project($child, 1, 1, 1);
        $this->assertNotNull(app(TragedyService::class)->record($child, $phase, 'proposition.revise'));

        $terminal = app(TerminalContinuationService::class);
        $this->assertTrue($terminal->closed($child));
        $this->assertFalse($terminal->closed($source));
        $options = app(PropositionOptionService::class);
        $this->assertContains($source->id, $options->writeOptionsFor($phase)->pluck('id')->all());
        $this->assertNotContains($child->id, $options->writeOptionsFor($phase)->pluck('id')->all());
        $this->assertNotContains($source->id, $options->revisionOptionsFor($phase, $participant)->pluck('id')->all());
        $this->assertSame($child->id, $source->children()->wherePivot('type', 'revise')->sole()->id);
    }

    public function test_personal_refute_can_revise_another_authors_variant(): void
    {
        [$parent, , $phase] = $this->laterRefutedPosition();
        $other = $this->node($this->phase($parent, PhaseKind::Disperse), $parent);
        $ownPrior = $phase->cycle->participant->cycles()->where('sequence', '<', $phase->cycle->sequence)->orderByDesc('sequence')->firstOrFail();
        $prior = $ownPrior->phases()->where('kind', PhaseKind::Disperse->value)->firstOrFail();
        $this->personalVote($prior, $other, 'refute');
        self::assertContains($other->id, app(PropositionOptionService::class)->revisionOptionsFor($phase, $phase->cycle->participant)->pluck('id'));
    }

    public function test_original_continuation_retains_its_original_measurement_lineage(): void
    {
        [, , $parent, $x, $mediate] = $this->personalJourney();
        $z = $this->node($mediate, $x);
        $at = $this->phase($parent, PhaseKind::Disperse);
        $y = $this->node($at, $parent);
        $edge = (new Supersession)->forceFill(['phase_id' => $at->id, 'source_proposition_id' => $x->id,
            'target_proposition_id' => $y->id, 'type' => 'revise']);
        $edge->save();
        app(LedgerService::class)->record($edge);
        self::assertNotContains($x->id, app(ActiveParticipantLineage::class)->endingAt($y)->pluck('id'));
        self::assertContains($x->id, app(ActiveParticipantLineage::class)->endingAt($z)->pluck('id'));
        $measurements = app(\App\Services\Observables\LineageMeasurementService::class);
        $context = new \App\Services\Participation\ParticipationContext($at);
        foreach ([null, $context] as $scope) {
            $lineages = app(\App\Services\Observables\ContextLineageComparisonService::class);
            self::assertNotContains($x->id, $lineages->for($y, $scope)->current->propositions->pluck('id'));
            self::assertContains($x->id, $lineages->for($z, $scope)->current->propositions->pluck('id'));
            $shape = app(\App\Services\Observables\CardinalMorphologyService::class);
            self::assertNotContains($x->id, $shape->current($y, $y->phase->governance()->contextHorizon, $scope)->propositions->pluck('id'));
            self::assertContains($x->id, $shape->current($z, $z->phase->governance()->contextHorizon, $scope)->propositions->pluck('id'));
            self::assertNotContains($x->id, $measurements->positions($y, $scope)->map(fn ($row) => $row[0]->id));
            self::assertContains($x->id, $measurements->positions($z, $scope)->map(fn ($row) => $row[0]->id));
        }
    }

    public function test_classification_and_reconciliation_positions_do_not_offer_revision(): void
    {
        foreach ([PhaseKind::Constrain, PhaseKind::Mediate, PhaseKind::Bias] as $kind) {
            self::assertNotContains('proposition.revise', config('phases.'.$kind->value.'.actions', []));
        }
    }
}
