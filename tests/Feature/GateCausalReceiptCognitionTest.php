<?php

namespace Tests\Feature;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\PhaseKind;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\EvidenceExecutionService;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\Observables\ProspectiveClosureService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantReceiptProjection;
use App\Services\ParticipationService;
use App\Services\ReceiptContextService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

/** Recorded graph components; operational authorization is asserted separately. */
class GateCausalReceiptCognitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_gate_observations_keep_their_execution_origins_across_participants_and_descendants(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $other = (new Participant)->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'Second observer']);
        $other->save();
        $a = $f->node($f->seed, 'activity');
        $b = $f->node($f->seed, 'activity', participant: $other);
        $one = $this->receipt($f, $f->seed, 'shared-one');
        $two = $this->receipt($f, $f->seed, 'shared-two', $b->phase);
        $sibling = $this->receipt($f, $b, 'sibling', $b->phase);
        [$catalogue] = $this->project($f, $a);
        $this->assertSame([$one->id, $two->id], array_column($catalogue, 'id'));
        foreach ([$one, $two] as $receipt) {
            $this->assertSame($f->seed->id, $receipt->evidenceExecution->proposition_id);
            $this->assertSame($f->seed->id, $receipt->receiptable_id);
            $this->assertSame($f->seed->id, collect($catalogue)->firstWhere('id', $receipt->id)['originating_gate_id']);
        }
        $this->assertNotContains($sibling->id, array_column($catalogue, 'id'));
        [$otherView] = $this->project($f, $b, viewer: $other);
        $this->assertEqualsCanonicalizing([$one->id, $two->id, $sibling->id], array_column($otherView, 'id'));
        $this->assertFalse(collect($otherView)->firstWhere('id', $one->id)['loaded']);
        $this->assertSame('Observation shared-one', collect($otherView)->firstWhere('id', $one->id)['summary']['title']);
    }

    public function test_encountered_reconciliation_unions_ancestry_once_without_importing_secondary_siblings_or_continuations(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $a = $f->node($f->seed, 'activity');
        $b0 = $f->node($f->seed, 'activity');
        $b = $f->node($b0, 'activity');
        $unrelated = $f->node($b0, 'frontier');
        $continuation = $f->node($b, 'frontier');
        $join = $f->node($a, 'frontier', 'reconcile', additionalParent: $b);
        $shared = $this->receipt($f, $f->seed, 'shared');
        $left = $this->receipt($f, $a, 'left');
        $right = $this->receipt($f, $b0, 'right-ancestor');
        $this->receipt($f, $unrelated, 'excluded-sibling');
        $this->receipt($f, $continuation, 'excluded-continuation');
        DB::table('proposition_receipt_links')->insert(['proposition_id' => $b->id, 'receipt_id' => $shared->id, 'created_at' => now()]);
        foreach ([$a, $join] as $gate) {
            [$catalogue, $registers] = $this->project($f, $gate);
            $this->assertSame([$shared->id, $left->id, $right->id], array_column($catalogue, 'id'));
            $this->assertSame($b0->id, collect($catalogue)->firstWhere('id', $right->id)['originating_gate_id']);
            $this->assertSame([$gate->id], array_column($registers['gate']['propositions'], 'id'));
            $this->assertSame([], $registers['read']['propositions']);
            $this->assertSame([], $registers['write']['propositions']);
        }
        // Hard Reconcile supersession leaves both immutable observations intact.
        $this->assertSame($a->id, $left->fresh()->receiptable_id);
        $this->assertSame($b0->id, $right->fresh()->receiptable_id);
    }

    public static function readPhases(): array
    {
        return [['mediate', PhaseKind::Constrain], ['order', PhaseKind::Bias]];
    }

    #[DataProvider('readPhases')]
    public function test_read_inclusion_uses_only_direct_consumption_and_never_persists_a_load(string $kind, PhaseKind $classification): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $source = $f->node($f->seed, 'frontier', position: $classification === PhaseKind::Bias ? PhaseKind::Mediate : PhaseKind::Persist);
        $receipt = $this->receipt($f, $source, 'consumed');
        $ancestor = $this->receipt($f, $f->seed, 'ancestor');
        // Match production ordering: consume before installing the hard edge,
        // while Bias can still resolve the active source's authority.
        $phase = $source->phase->replicate();
        $phase->forceFill(['kind' => $classification, 'gate_proposition_id' => $source->id, 'write_register' => 'canon'])->save();
        $target = (new Proposition)->forceFill(['phase_id' => $phase->id, 'tier' => 'expressive',
            'register' => 'canon', 'statement' => $source->statement]);
        $target->save();
        app(LedgerService::class)->record($target, $source);
        DB::transaction(fn () => app(HorizonReceiptService::class)->consume($target->phase, $source, $target, $receipt->id));
        $f->edge($source, $target, 'reclassify');
        app(ProspectiveClosureService::class)->initialize($target);
        $contexts = app(ReceiptContextService::class);
        foreach ([$receipt, $ancestor] as $r) {
            $contexts->clear($f->viewer, $f->phase, $r->id);
        }
        $gate = $f->node($target, 'activity');
        $before = $this->snapshot();
        [$catalogue, $registers] = $this->project($f, $gate, [$target], $kind);
        $entry = collect($catalogue)->firstWhere('id', $receipt->id);
        $this->assertFalse($entry['loaded']);
        $this->assertSame('read_context', $entry['load_mode']);
        $this->assertTrue($entry['read_context']);
        $this->assertSame('BODY-consumed', $entry['loaded_inline_content']['content_excerpt']);
        $this->assertSame('Observation consumed', $entry['summary']['title']);
        $this->assertSame($source->id, $entry['originating_gate_id']);
        $this->assertSame($target->id, $entry['consumed_by_proposition_id']);
        $this->assertSame([$receipt->id], $registers['read']['propositions'][0]['receipts']);
        $this->assertCount(1, array_filter($catalogue, fn ($r) => $r['id'] === $receipt->id));
        $this->assertArrayNotHasKey('loaded_inline_content', collect($catalogue)->firstWhere('id', $ancestor->id));
        [$withoutRead] = $this->project($f, $gate, kind: $kind);
        $this->assertArrayNotHasKey('loaded_inline_content', collect($withoutRead)->firstWhere('id', $receipt->id));
        $this->assertSame($before, $this->snapshot());
        $this->assertFalse(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase)->contains('id', $receipt->id));
        try {
            DB::transaction(fn () => app(HorizonReceiptService::class)->consume($target->phase, $source, $target, $receipt->id));
            $this->fail('A visible consumed Receipt was consumed twice.');
        } catch (DomainException) {
            $this->assertSame($before, $this->snapshot());
        }
        // Explicit loading remains independent, including after Read disappears.
        $f->phase->forceFill(['gate_proposition_id' => $target->id])->save();
        $f->viewer->forceFill(['current_proposition_id' => $target->id])->save();
        $contexts->read($f->viewer, $f->phase, $receipt->id);
        [$loaded] = $this->project($f, $gate, [$target], $kind);
        $entry = collect($loaded)->firstWhere('id', $receipt->id);
        $this->assertTrue($entry['loaded']);
        $this->assertTrue($entry['read_context']);
        $this->assertSame('participant', $entry['load_mode']);
        $this->assertSame(1, substr_count(json_encode($loaded), 'BODY-consumed'));
        [$loadedWithoutRead] = $this->project($f, $gate, kind: $kind);
        $this->assertSame('participant', collect($loadedWithoutRead)->firstWhere('id', $receipt->id)['load_mode']);
    }

    public function test_committal_references_preserve_the_real_consumer_without_inheriting_automatic_loading(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $r = $this->receipt($f, $f->gate, 'canonical');
        $target = $f->node($f->gate, 'canon', 'reclassify', position: PhaseKind::Constrain);
        DB::transaction(fn () => app(HorizonReceiptService::class)->consume($target->phase, $f->gate, $target, $r->id));
        $commit = $f->node($target, 'canon', 'commit', 'committal');
        app(ReceiptContextService::class)->clear($f->viewer, $f->phase, $r->id);
        $before = $this->snapshot();
        [$catalogue, $registers] = $this->project($f, $commit, [$commit], 'distinguish');
        $entry = collect($catalogue)->firstWhere('id', $r->id);
        $this->assertSame([$r->id], $registers['read']['propositions'][0]['receipts']);
        $this->assertSame($target->id, $entry['consumed_by_proposition_id']);
        $this->assertArrayNotHasKey('loaded_inline_content', $entry);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_read_context_citation_resolves_the_canonical_subject_without_expanding_its_lineage(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $service = app(EvidenceExecutionService::class);
        $receipt = $service->complete($service->start($f->phase, $f->gate, 'citation', $f->seed->id), $f->gate,
            metadata: ['statement' => 'Caller-supplied text is not the citation subject']);
        $target = $f->node($f->gate, 'canon', 'reclassify', position: PhaseKind::Constrain);
        DB::transaction(fn () => app(HorizonReceiptService::class)->consume($target->phase, $f->gate, $target, $receipt->id));
        app(ReceiptContextService::class)->clear($f->viewer, $f->phase, $receipt->id);
        [$catalogue, $registers] = $this->project($f, $target, [$target]);
        $entry = collect($catalogue)->firstWhere('id', $receipt->id);
        $this->assertFalse($entry['loaded']);
        $this->assertSame('read_context', $entry['load_mode']);
        $this->assertSame($f->seed->id, $entry['loaded_inline_content']['proposition_id']);
        $this->assertSame($f->seed->statement, $entry['loaded_inline_content']['statement']);
        $this->assertSame([$target->id], array_column($registers['read']['propositions'], 'id'));
        $this->assertStringNotContainsString('Caller-supplied', json_encode($catalogue));
    }

    public function test_human_and_inference_catalogues_match_without_changing_action_authority(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $this->receipt($f, $f->seed, 'foundation');
        $this->receipt($f, $f->gate, 'current');
        $this->publishPotentialFixture($f->viewer);
        $plan = app(InputPlanService::class)->forPhase($f->viewer, $f->phase);
        $serializer = app(ParticipantContextSerializer::class);
        $state = ['participant' => $f->viewer->only(['id']), 'plan' => $serializer->plan($plan)];
        $before = $this->snapshot();
        $human = $serializer->receiptCognition($state);
        $inference = $serializer->inference($state);
        $this->assertSame($human['receipts'], $inference['receipts']);
        foreach (['read', 'gate', 'write'] as $role) {
            $this->assertSame(array_column($human[$role]['propositions'], 'receipts'), array_column($inference[$role]['propositions'], 'receipts'));
        }
        $this->assertSame($before, $this->snapshot());
        $this->assertSame($plan['schema'], app(InputPlanService::class)->forPhase($f->viewer, $f->phase)['schema']);
        $participation = app(ParticipationService::class);
        $workspace = $participation->workspaceFor($f->viewer);
        $autonomous = $serializer->inference($participation->stateFor($f->viewer));
        $this->assertSame($autonomous['receipts'], $workspace['plan']['receipt_cognition']['receipts']);
    }

    #[Group('query')]
    public function test_overlapping_operand_ancestry_batches_queries_and_deduplicates_payload_growth(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $shared = $this->receipt($f, $f->seed, 'shared');
        $a = $f->node($f->seed, 'activity');
        $b = $f->node($f->seed, 'activity');
        $f->node($a, 'frontier', 'reconcile', additionalParent: $b);
        $measure = function () use ($f, $a): array {
            DB::flushQueryLog();
            DB::enableQueryLog();
            [$catalogue] = $this->project($f, $a);
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            foreach ($queries as $query) {
                $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|alter|create|drop)\b/i', $query['query']);
            }

            return [$catalogue, count($queries), strlen(json_encode($catalogue))];
        };
        [$small, $smallQueries, $smallBytes] = $measure();
        foreach (range(1, 20) as $i) {
            $this->receipt($f, $b, 'overlap-'.$i);
            $join = $f->node($a, 'frontier', 'reconcile', additionalParent: $b);
            DB::table('proposition_receipt_links')->insert(['proposition_id' => $join->id,
                'receipt_id' => $shared->id, 'created_at' => now()]);
        }
        [$large, $largeQueries, $largeBytes] = $measure();
        $this->assertCount(1, $small);
        $this->assertCount(21, $large);
        $this->assertSame($smallQueries, $largeQueries);
        $this->assertLessThanOrEqual(60, $largeQueries);
        $this->assertLessThan(21 * 2000, $largeBytes);
        $this->assertSame(1, substr_count(json_encode($large), 'BODY-shared'));
        $this->assertSame(1, substr_count(json_encode($large), 'BODY-overlap-20'));
        fwrite(STDERR, sprintf("\nRECEIPT_COGNITION overlap: %d -> %d queries; %d -> %d bytes; 1 -> 21 Receipts\n",
            $smallQueries, $largeQueries, $smallBytes, $largeBytes));
    }

    private function receipt(ParticipantContextFixture $f, Proposition $gate, string $tag, ?Phase $phase = null): Receipt
    {
        $service = app(EvidenceExecutionService::class);

        return $service->complete($service->start($phase ?? $f->phase, $gate, 'hyperlink', 'https://example.test/'.$tag), $gate,
            verification: new VerifiedHyperlink('example.test/'.$tag, hash('sha256', $tag), CarbonImmutable::now(), contentExcerpt: 'BODY-'.$tag,
                description: ['title' => 'Observation '.$tag]));
    }

    private function project(ParticipantContextFixture $f, Proposition $gate, array $reads = [], string $kind = 'mediate', ?Participant $viewer = null): array
    {
        $registers = ['read' => ['propositions' => array_map(fn ($node) => ['id' => $node->id], $reads)],
            'gate' => ['propositions' => [['id' => $gate->id]]], 'write' => ['propositions' => []]];
        $state = ['participant' => ['id' => ($viewer ?? $f->viewer)->id],
            'plan' => ['gate_id' => $gate->id, 'phase' => ['id' => $f->phase->id, 'kind' => $kind]]];

        return [app(ParticipantReceiptProjection::class)->normalize($registers, $state), $registers];
    }

    private function snapshot(): array
    {
        return collect(['receipts', 'evidence_executions', 'receipt_consumptions', 'proposition_receipt_links',
            'participant_receipt_contexts', 'receipt_context_events', 'substrate_ledger_entries', 'supersessions',
            'proposition_measurements'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->toJson()])->all();
    }
}
