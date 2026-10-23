<?php

namespace Tests\Feature;

use App\Domain\Evidence\PropositionCollection;
use App\Enums\PhaseKind;
use App\Models\ContextHorizon;
use App\Models\Cycle;
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
use App\Services\ParticipantReceiptProjection;
use App\Services\Participation\ParticipationContext;
use App\Services\PhaseProgressionService;
use App\Services\PropositionSearchService;
use App\Services\ReceiptContextService;
use App\Services\RelevantInstrumentService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\GovernedExpressionFixture;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[Group('component-contract')]
class PropositionSearchTest extends TestCase
{
    use IsolatesClosureOutput;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateClosureOutput();
    }

    public function test_postgresql_kind_constraints_preserve_tragedy_and_add_proposition_search(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL CHECK constraint regression.');
        }

        $definition = function (string $name): string {
            return (string) DB::table('pg_constraint')
                ->where('conname', $name)
                ->selectRaw('pg_get_constraintdef(oid) as definition')
                ->value('definition');
        };

        $receipts = $definition('receipts_type_check');
        $executions = $definition('instrument_executions_kind_check');

        $this->assertStringContainsString('tragedy', $receipts);
        $this->assertStringContainsString('proposition_search', $receipts);
        $this->assertStringContainsString('proposition_search', $executions);
    }

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
        // Input normalization does not require the historical distance scenario.
        $fixture = new GovernedExpressionFixture;
        $s = ['head' => $fixture->expression];
        $normalized = app(InstrumentInvocation::class)->normalize(['kind' => 'proposition_search']);
        $this->assertSame(['kind' => 'proposition_search', 'limit' => PropositionCollection::DEFAULT_LIMIT], $normalized);

        $schema = app(InputSchemaService::class)->forPhase(PhaseKind::Persist, ['instrument.execute']);
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

    public function test_ordinary_receipt_qualifies_and_loads_without_granting_result_authority(): void
    {
        $s = $this->scenario();
        $beforeEdges = Supersession::count();
        $fieldVersion = $s['fixture']->substrate->fresh()->field_version;
        $input = ['kind' => 'proposition_search', 'query' => 'needle', 'limit' => 10];
        $receipt = app(OperationalInstrumentService::class)->execute($s['phase'], $s['head'], $input);

        $this->assertGreaterThan($fieldVersion, $s['fixture']->substrate->fresh()->field_version);
        $this->assertSame('proposition_search', $receipt->type);
        $this->assertFalse($receipt->isSpecial());
        $this->assertTrue($receipt->isEvidence());
        $this->assertNull($receipt->consumption);
        $this->assertSame($receipt->metadata['observation_identity'], $receipt->signature);
        $this->assertSame('observed-proposition-search', $receipt->metadata['reinforcement_version']);
        $this->assertSame([3, 6, 9], array_column($receipt->metadata['proposition_collection']['results'], 'lineage_distance'));
        $this->assertSame($beforeEdges, Supersession::count(), 'Searching history must create no causal edge.');
        $this->assertTrue(app(EvidenceEligibilityService::class)->hasReceipt($s['head']));
        $this->assertTrue(app(CommitmentAdmissibilityService::class)->grounding($s['head'])['requirements']['receipt']);
        $this->assertContains($receipt->id, app(HorizonReceiptService::class)->ordinaryAvailable($s['phase'])->pluck('id'));
        $this->assertContains($s['head']->id,
            app(HorizonReceiptService::class)->instrumentedIds($s['phase'], collect([$s['head']->id])));
        $this->assertSame(1, app(ReinforcementService::class)->count($s['head']));

        $context = app(ReceiptContextService::class);
        $available = $context->forPhase($s['phase'])->firstWhere('id', $receipt->id);
        $this->assertSame('available', $available['availability']);
        $this->assertSame('loaded', $available['status']);
        $this->assertArrayNotHasKey('load_mode', $available);
        $this->assertTrue($available['loaded']);
        $this->assertFalse($available['active']);
        $this->assertSame($receipt->metadata['proposition_collection'], $available['evidence']);

        $load = $context->read($s['fixture']->viewer, $s['phase'], $receipt->id);
        $this->assertGreaterThan($fieldVersion, $s['fixture']->substrate->fresh()->field_version);
        $this->assertSame($receipt->signature, $load->evidence_signature);
        $this->assertSame($s['head']->id, $load->proposition_id);
        $loaded = $context->forPhase($s['phase'])->firstWhere('id', $receipt->id);
        $this->assertTrue($loaded['loaded']);
        $this->assertSame([3, 6, 9], array_column($loaded['proposition_collection']['results'], 'lineage_distance'));
        $this->assertSame(1, app(ReinforcementService::class)->count($s['head']));

        $serialized = app(ParticipantContextSerializer::class)->receipts($context->forPhase($s['phase'])->all());
        $memory = collect($serialized)->firstWhere('id', $receipt->id);
        $this->assertNotNull($memory);
        $this->assertArrayHasKey('proposition_collection', $memory);
        $registers = array_fill_keys(['read', 'gate', 'write'], ['propositions' => []]);
        $catalogue = app(ParticipantReceiptProjection::class)->normalize($registers,
            ['participant' => ['id' => $s['fixture']->viewer->id], 'plan' => ['receipts' => $serialized]]);
        $prompt = app(ParticipantPromptLayout::class)->make(['receipts' => $catalogue, ...$registers]);
        $this->assertSame('', $prompt['evidence']);
        $this->assertArrayHasKey('loaded_inline_content', json_decode($prompt['user'], true)['receipts'][0]);
        $this->assertSame([3, 6, 9], array_column(
            collect(json_decode($prompt['user'], true)['receipts'])->firstWhere('id', $receipt->id)['loaded_inline_content']['results'],
            'lineage_distance',
        ));

        $resultIds = array_column($receipt->metadata['proposition_collection']['results'], 'proposition_id');
        $after = app(InputPlanService::class)->forPhase($s['fixture']->viewer, $s['phase']);
        $this->assertSame([], array_values(array_intersect($resultIds, $this->actionTargets($after['schema']))),
            'Search memory may inform cognition but cannot create Traverse, Gate, Write or other action targets.');
        $this->assertSame($beforeEdges, Supersession::count());
        $this->assertDatabaseCount('participant_receipt_contexts', 1);
    }

    public function test_stale_internal_history_observation_is_rejected_before_persistence(): void
    {
        $s = $this->scenario();
        $service = app(OperationalInstrumentService::class);
        $input = ['kind' => 'proposition_search', 'query' => 'needle', 'limit' => 10];
        $observed = $service->observeFor($s['phase'], $s['head'], $input);

        $this->historyNode($s['fixture'], null, $s['root'], 'concurrent unrelated ledger change');

        try {
            $service->execute($s['phase'], $s['head'], $input, observed: $observed);
            $this->fail('A stale Proposition Search collection was persisted.');
        } catch (ConflictHttpException) {
            $this->assertSame(0, Receipt::where('type', 'proposition_search')->count());
        }
    }

    public function test_duplicate_identity_uses_the_observed_collection_not_query_alone(): void
    {
        $s = $this->scenario();
        $input = ['kind' => 'proposition_search', 'query' => 'needle', 'limit' => 10];
        $first = app(OperationalInstrumentService::class)->execute($s['phase'], $s['head'], $input);
        // Hold the observed population constant: loaded memory is excluded by the existing search contract.
        app(ReceiptContextService::class)->clear($s['fixture']->viewer, $s['phase'], $first->id);

        foreach ([$input, [...$input, 'limit' => 5]] as $duplicate) {
            try {
                $collection = app(PropositionSearchService::class)->search($s['phase'], $s['head'], $duplicate);
                app(RelevantInstrumentService::class)->assertAvailable($s['head'], $duplicate, $collection);
                app(OperationalInstrumentService::class)->execute($s['phase'], $s['head'], $duplicate);
                $this->fail('The same query and same observed collection should be suppressed.');
            } catch (ConflictHttpException) {
                $this->assertSame(1, Receipt::where('type', 'proposition_search')->count());
            }
        }

        $older = $this->historyNode($s['fixture'], null, $s['root'], 'needle older history');
        $s['fixture']->edge($older, $s['oldest'], 'variant');
        // A new collection still requires a fresh personal Cycle allowance.
        $s['phase']->forceFill(['completed_at' => now()])->save();
        $s['phase']->cycle->forceFill(['completed_at' => now()])->save();
        $cycle = $s['phase']->cycle->replicate();
        $cycle->forceFill(['sequence' => (int) $s['fixture']->viewer->cycles()->max('sequence') + 1, 'completed_at' => null])->save();
        $phase = $s['phase']->replicate();
        $phase->forceFill(['cycle_id' => $cycle->id, 'completed_at' => null])->save();
        $s['fixture']->phase = $phase;
        $this->consumeObservationFixture($s['fixture'], $first);
        $second = app(OperationalInstrumentService::class)->execute($phase->fresh(), $s['head'], $input);
        $this->assertNotSame($first->metadata['observation_identity'], $second->metadata['observation_identity']);
        $this->assertSame([3, 6, 9, 11],
            array_column($second->metadata['proposition_collection']['results'], 'lineage_distance'));
        $this->assertSame(2, Receipt::where('type', 'proposition_search')->count());
    }

    public function test_kind_migration_refuses_to_discard_recorded_proposition_search_history(): void
    {
        // The migration protects a real recorded search, including an empty result.
        $fixture = new GovernedExpressionFixture;
        $s = ['head' => $fixture->expression, 'phase' => app(PhaseProgressionService::class)->advance($fixture->phase, $fixture->expression)];
        app(OperationalInstrumentService::class)->execute($s['phase'], $s['head'], [
            'kind' => 'proposition_search', 'query' => 'needle',
        ]);

        $migration = require database_path('migrations/2026_09_26_000000_add_proposition_search_instrument_kind.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot discard Proposition Search Instrument or Receipt history.');
        $migration->down();
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

        $committal = $fixture->projectedCommittal($a, 'canon');
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
            'kind' => PhaseKind::Persist,
            'write_register' => 'frontier',
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
            'entry_committal_id' => $horizon->opened_by_proposition_id,
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
        return $this->laterHorizonFixture($root, 1);
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
