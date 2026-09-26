<?php

namespace Tests\Feature;

use App\Domain\Evidence\PropositionCollection;
use App\Enums\PhaseKind;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\ParticipantReceiptContext;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Supersession;
use App\Services\CommitmentAdmissibilityService;
use App\Services\ContributionService;
use App\Services\Evidence\InstrumentInvocation;
use App\Services\EvidenceEligibilityService;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\InputSchemaService;
use App\Services\LedgerService;
use App\Services\Observables\ReinforcementService;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantPromptLayout;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionSearchService;
use App\Services\ReceiptContextService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class PropositionSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_is_bounded_to_unseen_same_domain_recorded_lineage_with_true_distances(): void
    {
        $s = $this->scenario();
        $context = new ParticipationContext($s['phase']);
        $this->assertTrue($context->propositions->has($s['a']->id),
            'The calculation context deliberately hydrates history beyond the participant-facing Committal boundary.');
        $plan = app(InputPlanService::class)->forPhase($s['fixture']->viewer, $s['phase']);
        $visible = app(ParticipantContextSerializer::class)->visiblePropositionIds($plan);
        $this->assertContains($s['visible']->id, $visible);
        $this->assertContains($s['committal']->id, $visible);
        $this->assertNotContains($s['a']->id, $visible);

        $service = app(PropositionSearchService::class);
        $nearest = $service->search($s['phase'], $s['head'], ['kind' => 'proposition_search']);
        $this->assertNull($nearest->query);
        $this->assertSame(5, $nearest->limit);
        $this->assertSame($s['head']->id, $nearest->headPropositionId);
        $this->assertSame('emergence', $nearest->domain);
        $this->assertSame([3, 5, 6, 7, 8], array_column($nearest->results, 'lineage_distance'));
        $this->assertNotContains($s['visible']->id, array_column($nearest->results, 'proposition_id'));
        $this->assertNotContains($s['committal']->id, array_column($nearest->results, 'proposition_id'));
        $this->assertNotContains($s['foreign']->id, array_column($nearest->results, 'proposition_id'));

        $matched = $service->search($s['phase'], $s['head'], [
            'kind' => 'proposition_search', 'query' => 'needle', 'limit' => 10,
        ]);
        $this->assertSame([$s['a']->id, $s['b']->id, $s['c']->id],
            array_column($matched->results, 'proposition_id'));
        $this->assertSame([3, 6, 9], array_column($matched->results, 'lineage_distance'));
        $this->assertNotContains($s['foreign']->id, array_column($matched->results, 'proposition_id'),
            'A strongly matching complementary-Domain ancestor is still not searchable.');
        $this->assertNotContains($s['unrelated']->id, array_column($matched->results, 'proposition_id'),
            'Text relevance cannot import a sibling or unrelated Proposition.');
        $this->assertSame([3], array_column($service->search($s['phase'], $s['head'], [
            'kind' => 'proposition_search', 'query' => 'needle', 'limit' => 1,
        ])->results, 'lineage_distance'));
    }

    public function test_schema_and_domain_apply_defaults_and_reject_invalid_limits(): void
    {
        $s = $this->scenario();
        $normalized = app(InstrumentInvocation::class)->normalize(['kind' => 'proposition_search']);
        $this->assertSame(['kind' => 'proposition_search', 'limit' => PropositionCollection::DEFAULT_LIMIT], $normalized);

        $schema = app(InputSchemaService::class)->forPhase(PhaseKind::Constrain, ['instrument.execute']);
        $service = app(ContributionService::class);
        foreach ([null, 1, 10] as $limit) {
            $instrument = ['kind' => 'proposition_search'];
            if ($limit !== null) {
                $instrument['limit'] = $limit;
            }
            $service->validateSchema($schema, [
                'action' => 'instrument.execute', 'proposition_id' => $s['head']->id, 'instrument' => $instrument,
            ]);
        }
        foreach ([0, 11, 1.5, '5'] as $limit) {
            try {
                $service->validateSchema($schema, [
                    'action' => 'instrument.execute', 'proposition_id' => $s['head']->id,
                    'instrument' => ['kind' => 'proposition_search', 'limit' => $limit],
                ]);
                $this->fail('Invalid Proposition Search limit passed schema validation.');
            } catch (InvalidArgumentException) {
                // Expected.
            }
            try {
                app(InstrumentInvocation::class)->normalize(['kind' => 'proposition_search', 'limit' => $limit]);
                $this->fail('Invalid Proposition Search limit passed domain normalization.');
            } catch (DomainException) {
                // Expected.
            }
        }
        foreach (['', " \n\t "] as $query) {
            $this->expectDomainFailure(fn () => app(InstrumentInvocation::class)
                ->normalize(['kind' => 'proposition_search', 'query' => $query]));
        }
    }

    public function test_receipt_loads_as_cognitive_memory_without_evidence_reinforcement_or_authority(): void
    {
        $s = $this->scenario();
        $beforeEdges = Supersession::count();
        $input = ['kind' => 'proposition_search', 'query' => 'needle', 'limit' => 10];
        $receipt = app(OperationalInstrumentService::class)->execute($s['phase'], $s['head'], $input);

        $this->assertSame('proposition_search', $receipt->type);
        $this->assertTrue($receipt->isMemory());
        $this->assertNull($receipt->signature);
        $this->assertSame('non-reinforcing-memory', $receipt->metadata['reinforcement_version']);
        $this->assertSame([3, 6, 9], array_column($receipt->metadata['proposition_collection']['results'], 'lineage_distance'));
        $this->assertSame($beforeEdges, Supersession::count(), 'Searching history must create no causal edge.');
        $this->assertFalse(app(EvidenceEligibilityService::class)->hasReceipt($s['head']));
        $this->assertFalse(app(CommitmentAdmissibilityService::class)->grounding($s['head'])['requirements']['receipt']);
        $this->assertNotContains($receipt->id, app(HorizonReceiptService::class)->ordinaryAvailable($s['phase'])->pluck('id'));
        $this->assertNotContains($s['head']->id,
            app(HorizonReceiptService::class)->instrumentedIds($s['phase'], collect([$s['head']->id])));
        $this->assertSame(0, app(ReinforcementService::class)->count($s['head']));

        $context = app(ReceiptContextService::class);
        $available = $context->forPhase($s['phase'])->firstWhere('id', $receipt->id);
        $this->assertSame('available', $available['availability']);
        $this->assertSame('available', $available['status']);
        $this->assertSame('memory', $available['load_mode']);
        $this->assertFalse($available['loaded']);
        $this->assertFalse($available['active']);
        $this->assertArrayNotHasKey('evidence', $available);

        $load = $context->read($s['fixture']->viewer, $s['phase'], $receipt->id);
        $this->assertNull($load->evidence_signature);
        $this->assertSame($s['head']->id, $load->proposition_id);
        $loaded = $context->forPhase($s['phase'])->firstWhere('id', $receipt->id);
        $this->assertTrue($loaded['memory_active']);
        $this->assertSame([3, 6, 9], array_column($loaded['proposition_collection']['results'], 'lineage_distance'));
        $this->assertSame(0, app(ReinforcementService::class)->count($s['head']));

        $serialized = app(ParticipantContextSerializer::class)->receipts($context->forPhase($s['phase'])->all());
        $memory = collect($serialized)->firstWhere('id', $receipt->id);
        $this->assertNotNull($memory);
        $this->assertArrayHasKey('proposition_collection', $memory);
        $prompt = app(ParticipantPromptLayout::class)->make(['plan' => ['receipts' => $serialized]]);
        $this->assertSame([], json_decode($prompt['evidence'], true)['loaded_receipts']);
        $this->assertSame([3, 6, 9], array_column(
            collect(json_decode($prompt['user'], true)['plan']['receipts'])->firstWhere('id', $receipt->id)['proposition_collection']['results'],
            'lineage_distance',
        ));

        $resultIds = array_column($receipt->metadata['proposition_collection']['results'], 'proposition_id');
        $after = app(InputPlanService::class)->forPhase($s['fixture']->viewer, $s['phase']);
        $this->assertSame([], array_values(array_intersect($resultIds, $this->actionTargets($after['schema']))),
            'Search memory may inform cognition but cannot create Traverse, Gate, Write or other action targets.');
        $this->assertSame($beforeEdges, Supersession::count());
        $this->assertDatabaseCount('participant_receipt_contexts', 1);
    }

    public function test_duplicate_identity_uses_the_observed_collection_not_query_alone(): void
    {
        $s = $this->scenario();
        $input = ['kind' => 'proposition_search', 'query' => 'needle', 'limit' => 10];
        $first = app(OperationalInstrumentService::class)->execute($s['phase'], $s['head'], $input);

        try {
            app(OperationalInstrumentService::class)->execute($s['phase'], $s['head'], $input);
            $this->fail('The same query and same observed collection should be suppressed.');
        } catch (ConflictHttpException) {
            $this->assertSame(1, Receipt::where('type', 'proposition_search')->count());
        }

        $older = $this->historyNode($s['fixture'], null, $s['root'], 'needle older history');
        $s['fixture']->edge($older, $s['oldest'], 'variant');
        $second = app(OperationalInstrumentService::class)->execute($s['phase'], $s['head'], $input);
        $this->assertNotSame($first->metadata['observation_identity'], $second->metadata['observation_identity']);
        $this->assertSame([3, 6, 9, 11],
            array_column($second->metadata['proposition_collection']['results'], 'lineage_distance'));
        $this->assertSame(2, Receipt::where('type', 'proposition_search')->count());
    }

    private function scenario(): array
    {
        $fixture = new ParticipantContextFixture(2, 0, false);
        $fixture->phase->forceFill(['completed_at' => now()])->save();
        $fixture->phase->cycle->forceFill(['completed_at' => now()])->save();
        $root = $fixture->history->contextHorizon()->firstOrFail();
        $foreignHorizon = $this->foreignHorizon($fixture, $root);

        $oldest = $this->historyNode($fixture, null, $root, 'background oldest history');
        $c = $this->historyNode($fixture, $oldest, $root, 'needle historical C');
        $skip8 = $this->historyNode($fixture, $c, $root, 'background eight');
        $skip7 = $this->historyNode($fixture, $skip8, $root, 'background seven');
        $b = $this->historyNode($fixture, $skip7, $root, 'needle historical B');
        $skip5 = $this->historyNode($fixture, $b, $root, 'background five');
        $foreign = $this->historyNode($fixture, $skip5, $foreignHorizon, 'needle complementary domain');
        $a = $this->historyNode($fixture, $foreign, $root, 'needle historical A');

        $committal = $fixture->node($a, 'canon', 'commit', 'committal');
        $visible = $fixture->node($committal, 'activity', position: PhaseKind::Distinguish);
        $head = $fixture->node($visible, 'frontier', position: PhaseKind::Persist);

        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $fixture->viewer->id,
            'sequence' => (int) $fixture->viewer->cycles()->max('sequence') + 1,
            'context_horizon_id' => $root->id,
            'entry_committal_id' => $committal->id,
        ])->save();
        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->id,
            'kind' => PhaseKind::Constrain,
            'write_register' => 'canon',
            'gate_proposition_id' => $head->id,
            'started_at' => now(),
        ])->save();
        $fixture->viewer->forceFill(['current_proposition_id' => $head->id])->save();
        $unrelated = $this->historyNode($fixture, null, $root, 'needle unrelated sibling');

        return compact('fixture', 'root', 'phase', 'head', 'visible', 'committal',
            'a', 'b', 'c', 'foreign', 'unrelated', 'oldest');
    }

    private function historyNode(
        ParticipantContextFixture $fixture,
        ?Proposition $parent,
        ContextHorizon $horizon,
        string $statement,
    ): Proposition {
        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $fixture->viewer->id,
            'sequence' => (int) $fixture->viewer->cycles()->max('sequence') + 1,
            'context_horizon_id' => $horizon->id,
            'completed_at' => now(),
        ])->save();
        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->id,
            'kind' => PhaseKind::Distinguish,
            'write_register' => $horizon->domain->registerFor(PhaseKind::Distinguish),
            'started_at' => now(),
            'completed_at' => now(),
        ])->save();
        $node = new Proposition;
        $node->forceFill([
            'phase_id' => $phase->id,
            'register' => $phase->write_register,
            'tier' => 'expressive',
            'statement' => $statement,
        ])->save();
        app(LedgerService::class)->record($node);
        if ($parent !== null) {
            $fixture->edge($parent, $node, 'variant');
        }

        return $node;
    }

    /** A test-only complementary Domain owner; the search assertion is about filtering, not Horizon opening. */
    private function foreignHorizon(ParticipantContextFixture $fixture, ContextHorizon $root): ContextHorizon
    {
        $id = (string) str()->ulid();
        DB::table('context_horizons')->insert([
            'id' => $id,
            'substrate_id' => $fixture->substrate->id,
            'sequence' => (int) DB::table('context_horizons')->where('substrate_id', $fixture->substrate->id)->max('sequence') + 100,
            'domain' => 'settlement',
            'parent_context_horizon_id' => $root->id,
            'opened_by_proposition_id' => null,
            'closed_by_proposition_id' => null,
            'opened_at' => now(),
            'closed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ContextHorizon::findOrFail($id);
    }

    private function actionTargets(array $schema): array
    {
        return collect($schema['$defs']['actions'] ?? [])->flatMap(fn ($action) => array_merge(
            $action['properties']['proposition_id']['enum'] ?? [],
            $action['properties']['other_proposition_id']['enum'] ?? [],
        ))->filter()->unique()->values()->all();
    }

    private function expectDomainFailure(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a domain failure.');
        } catch (DomainException) {
            // Expected.
        }
    }
}
