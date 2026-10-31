<?php

namespace Tests\Feature;

use App\Models\ParticipantReceiptContext;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\CommitmentAdmissibilityService;
use App\Services\CrossoverMarginService;
use App\Services\EvidenceExecutionService;
use App\Services\HomologousPositionService;
use App\Services\LedgerService;
use App\Services\Observables\CommittalLineageService;
use App\Services\Observables\ProspectiveClosureService;
use App\Services\Observables\ReinforcementService;
use App\Services\ParticipationService;
use App\Services\PhaseProgressionService;
use App\Services\PropositionSearchService;
use App\Services\PropositionStateService;
use App\Services\ReceiptContextService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\HorizonCycleFixture;
use Tests\Fixtures\PropositionBirth;
use Tests\Support\RefreshEvidenceDatabase as RefreshDatabase;
use Tests\TestCase;

/** Separate coverage for post-Commit reset, selected search lineage and frozen history. */
#[Group('integration')]
class CommitDescendantHistoryLifecycleTest extends TestCase
{
    use HorizonCycleFixture, RefreshDatabase;

    public function test_historical_descendant_of_a_real_closed_commit_retains_its_own_evidence_and_selected_search_history(): void
    {
        $gates = [];
        $commit = $this->driveStaggeredCommit(
            recordAction: function ($participant, $state, $input, $result) use (&$gates): void {
                $gates[$state['plan']['phase']['number']] = $state['plan']['gate_id'];
            });
        $actor = $commit->phase->cycle->participant;
        $source = $commit->commitSources()->sole();
        $origin = Proposition::findOrFail($commit->phase->cycle->entry_committal_id);
        $closure = app(ProspectiveClosureService::class);
        $frozen = $closure->stored($commit);
        $snapshot = $commit->commitmentSnapshot->getAttributes();
        $state = app(PropositionStateService::class);
        $votes = $state->attestations(collect([$commit]))->pluck('id')->all();
        $receipts = $state->receipts($commit)->pluck('id')->all();
        $this->assertNotEmpty($votes);
        $this->assertNotEmpty($receipts);
        $this->assertSame($source->id, $gates[7]);
        $this->assertSame([$origin->id], app(CommittalLineageService::class)->ancestors($commit)->pluck('proposition_id')->all());
        $this->assertSame([$commit->id => ['ancestor_id' => $commit->id, 'distance' => 0]],
            app(HomologousPositionService::class)->committedCoordinatesFor($commit, collect([$origin, $commit])));
        $service = app(ParticipationService::class);
        $before = $service->stateFor($actor);
        $entry = Phase::findOrFail($before['plan']['phase']['id']);
        $this->assertTrue(app(CrossoverMarginService::class)->inspect($entry->cycle)['closed']);
        $this->assertSame(['participant.rest'], array_keys($before['plan']['schema']['$defs']['actions']));

        // Read-model input from the former admission contract. Today neither
        // this search nor this Variant can be submitted at this closed entry.
        $input = ['kind' => 'proposition_search', 'limit' => 10];
        $collection = app(PropositionSearchService::class)->search($entry, $commit, $input);
        $executions = app(EvidenceExecutionService::class);
        $record = $executions->start($entry, $commit, 'proposition_search', 'nearest-history', $input);
        $discovery = $executions->complete($record, $commit, propositionCollection: $collection);
        $this->assertSame($commit->id, $discovery->evidenceExecution->proposition_id);
        $receiptsAfterDiscovery = [...$receipts, $discovery->id];
        $head = (new Proposition)->forceFill(['phase_id' => $entry->id, 'register' => $entry->write_register,
            'tier' => 'expressive', 'statement' => 'A historical post-Commit question.']);
        $head->save();
        app(LedgerService::class)->record($head, $commit);
        $edge = (new Supersession)->forceFill(['phase_id' => $entry->id, 'source_proposition_id' => $commit->id,
            'target_proposition_id' => $head->id, 'type' => 'variant']);
        $edge->save();
        app(LedgerService::class)->record($edge);
        PropositionBirth::complete($head);
        $phase = app(PhaseProgressionService::class)->advance($entry, $head);
        $this->publishPotentialFixture($actor);
        $this->assertSame('expressive', $head->tier);
        $this->assertSame([$commit->id], $head->parents()->pluck('propositions.id')->all());
        $this->assertSame($commit->id, $head->phase->cycle->entry_committal_id);
        $this->assertSame('settlement', $head->phase->cycle->contextHorizon->domain->value);
        $this->assertSame([$commit->id, $origin->id], app(CommittalLineageService::class)->ancestors($head)->pluck('proposition_id')->all());
        $this->assertSame([$head->id], $state->sourceIds($head)->all());
        $this->assertCount(0, $state->attestations(collect([$head])));
        $this->assertCount(0, $state->receipts($head));
        $this->assertSame(1, $state->touchCount($head)); // Real progression into Persist, not inherited touches.
        $this->assertDatabaseHas('phase_profile_observations', ['proposition_id' => $head->id]);

        // C2's creator loads retain cited history in personal context. Explicitly
        // unload it before testing the unchanged search over unavailable history.
        foreach (ParticipantReceiptContext::active()->where('participant_id', $actor->id)->get() as $load) {
            app(ReceiptContextService::class)->clear($actor, $phase, $load->receipt_id);
        }

        // The final traversal records Bias, Mediate, local Disperse, Constrain,
        // Persist, Distinguish in that order behind the real Commit hard edge.
        $expected = [$gates[7], $gates[6], $gates[5], $gates[4], $gates[3], $gates[2]];
        $search = app(PropositionSearchService::class)->search($phase, $head,
            ['kind' => 'proposition_search', 'limit' => 10]);
        $this->assertSame($expected, array_column($search->results, 'proposition_id'));
        $this->assertSame([2, 3, 4, 5, 6, 7], array_column($search->results, 'lineage_distance'));
        // The committed route can revisit an earlier synthesis. Its unselected
        // operand is not necessarily the operand of the last Reconcile anywhere.
        $operands = Supersession::where('type', 'reconcile')->where('target_proposition_id', $gates[6])
            ->pluck('source_proposition_id');
        $this->assertCount(2, $operands);
        $this->assertContains($gates[5], $operands);
        $otherOperand = $operands->reject(fn ($id) => $id === $gates[5])->sole();
        $this->assertNotContains($otherOperand, $expected, 'Reconcile does not import the other branch into selected history.');
        $this->assertNotContains($origin->id, array_column($search->results, 'proposition_id'));
        $this->assertSame('settlement', $search->domain);
        // The real search was recorded against the Committal before this
        // descendant existed. Moving its results into personal memory grants no
        // evidence ownership to the descendant and requires its own Phase's attempt.
        $edges = Supersession::count();
        $memory = $discovery;
        // At initial entry the two loaded subjects are already cognitively visible.
        $this->assertSame([$gates[7], $gates[6], $gates[5], $gates[3]],
            array_column($memory->metadata['proposition_collection']['results'], 'proposition_id'));
        $this->assertSame([1, 2, 3, 5], array_column($memory->metadata['proposition_collection']['results'], 'lineage_distance'));
        $this->assertSame($commit->id, $memory->evidenceExecution->proposition_id);
        $this->assertSame(0, $head->evidenceExecutions()->count());
        $this->assertSame($memory->metadata['observation_identity'], $memory->signature);
        $this->assertFalse(app(CommitmentAdmissibilityService::class)->grounding($head)['requirements']['receipt']);
        $this->assertSame(0, app(ReinforcementService::class)->count($head));
        $this->publishPotentialFixture($actor);
        $this->assertArrayNotHasKey('proposition.variant', $service->stateFor($actor)['plan']['schema']['$defs']['actions']);
        $load = app(ReceiptContextService::class)->read($actor, $phase, $memory->id);
        $this->assertSame($memory->signature, $load->evidence_signature);
        $this->assertSame($edges, Supersession::count());
        $this->assertSame($head->id, $actor->fresh()->current_proposition_id);
        $this->assertSame($votes, $state->attestations(collect([$commit]))->pluck('id')->all());
        $this->assertEqualsCanonicalizing($receiptsAfterDiscovery, $state->receipts($commit)->pluck('id')->all());
        $this->assertSame($frozen, $closure->stored($commit->fresh()));
        $this->assertSame($snapshot, $commit->fresh()->commitmentSnapshot->getAttributes());
    }
}
