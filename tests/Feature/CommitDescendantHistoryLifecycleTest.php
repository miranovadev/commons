<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\ParticipantReceiptContext;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Supersession;
use App\Services\CommitmentAdmissibilityService;
use App\Services\ContributionDispatcher;
use App\Services\HomologousPositionService;
use App\Services\Observables\CommittalLineageService;
use App\Services\Observables\ProspectiveClosureService;
use App\Services\Observables\ReinforcementService;
use App\Services\ParticipationService;
use App\Services\PropositionSearchService;
use App\Services\PropositionStateService;
use App\Services\ReceiptContextService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\HorizonCycleFixture;
use Tests\Support\RefreshIntegrationDatabase as RefreshDatabase;
use Tests\TestCase;

/** Separate coverage for post-Commit reset, selected search lineage and frozen history. */
#[Group('integration')]
class CommitDescendantHistoryLifecycleTest extends TestCase
{
    use HorizonCycleFixture, RefreshDatabase;

    public function test_real_commit_descendant_resets_evidence_and_searches_selected_history_without_authority(): void
    {
        $gates = [];
        $otherOperand = null;
        $commit = $this->driveHorizonCycle(realClosure: true, actionBudget: 101, expressiveDirection: 'refute',
            recordAction: function ($participant, $state, $input, $result) use (&$gates, &$otherOperand): void {
                $gates[$state['plan']['phase']['number']] = $state['plan']['gate_id'];
                if ($input['action'] === 'proposition.reconcile') {
                    $otherOperand = $input['other_proposition_id'];
                }
            });
        $actor = Participant::sole();
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
        $next = $service->contribute($actor, $before['plan']['phase']['id'],
            ['action' => 'proposition.variant', 'statement' => 'A fresh post-Commit question.']);
        $head = $actor->fresh()->position;
        $phase = Phase::findOrFail($next['plan']['phase']['id']);
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
        $this->assertNotNull($otherOperand);
        $this->assertNotContains($otherOperand, $expected, 'Reconcile does not import the other branch into selected history.');
        $this->assertNotContains($origin->id, array_column($search->results, 'proposition_id'));
        $this->assertSame('settlement', $search->domain);
        // Persist observes its current Gate. The ordinary Receipt supplies evidence,
        // while its result collection grants no structural authority.
        $edges = Supersession::count();
        app(ContributionDispatcher::class)->dispatch($actor, $phase,
            ['action' => 'instrument.execute', 'proposition_id' => $head->id,
                'instrument' => ['kind' => 'proposition_search', 'limit' => 10]]);
        $memory = Receipt::where('type', 'proposition_search')->sole();
        $this->assertSame($expected, array_column($memory->metadata['proposition_collection']['results'], 'proposition_id'));
        $this->assertSame([2, 3, 4, 5, 6, 7], array_column($memory->metadata['proposition_collection']['results'], 'lineage_distance'));
        $this->assertSame($head->id, $memory->instrumentExecution->proposition_id);
        $this->assertSame(1, $head->instrumentExecutions()->count());
        $this->assertSame($memory->metadata['observation_identity'], $memory->signature);
        $this->assertTrue(app(CommitmentAdmissibilityService::class)->grounding($head)['requirements']['receipt']);
        $this->assertSame(1, app(ReinforcementService::class)->count($head));
        $this->publishPotentialFixture($actor);
        $this->assertArrayHasKey('proposition.variant', $service->stateFor($actor)['plan']['schema']['$defs']['actions']);
        $load = app(ReceiptContextService::class)->read($actor, $phase, $memory->id);
        $this->assertSame($memory->signature, $load->evidence_signature);
        $this->assertSame($edges, Supersession::count());
        $this->assertSame($head->id, $actor->fresh()->current_proposition_id);
        $this->assertSame($votes, $state->attestations(collect([$commit]))->pluck('id')->all());
        $this->assertSame($receipts, $state->receipts($commit)->pluck('id')->all());
        $this->assertSame($frozen, $closure->stored($commit->fresh()));
        $this->assertSame($snapshot, $commit->fresh()->commitmentSnapshot->getAttributes());
    }
}
