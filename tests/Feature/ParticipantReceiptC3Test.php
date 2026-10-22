<?php

namespace Tests\Feature;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\ContributionDispatcher;
use App\Services\InputPlanService;
use App\Services\InstrumentExecutionService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantReceiptProjection;
use App\Services\PhaseProgressionService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionMeasurementService;
use App\Services\TerminalContinuationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\GovernedExpressionFixture;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class ParticipantReceiptC3Test extends TestCase
{
    use RefreshDatabase;

    public function test_extended_evidence_is_personal_deduplicated_and_never_inserts_operational_nodes(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $e1 = $f->node($f->seed, 'activity');
        $e2 = $f->node($e1, 'frontier');
        $e3 = $f->node($e2, 'canon');
        $sibling = $f->node($e1, 'frontier');
        $foundation = $this->receipt($f, $f->seed, 'foundation');
        $past = $this->receipt($f, $e1, 'past');
        $future = $this->receipt($f, $e3, 'future');
        $other = $this->receipt($f, $sibling, 'sibling');
        // Isolated independent recorded association; this does not claim a
        // second consume operation is lawful or fabricate a causal edge.
        DB::table('proposition_receipt_links')->insert(['proposition_id' => $e2->id,
            'receipt_id' => $past->id, 'created_at' => now()]);
        $before = $this->snapshot();
        foreach (['persist', 'constrain', 'disperse', 'mediate', 'bias', 'order'] as $kind) {
            [$catalogue, $registers] = $this->project($f, $e2, $kind);
            $this->assertEqualsCanonicalizing([$foundation->id, $past->id, $future->id], array_column($catalogue, 'id'));
            $this->assertNotContains($other->id, array_column($catalogue, 'id'));
            $this->assertSame([$e2->id], array_column($registers['gate']['propositions'], 'id'));
            $this->assertSame([], $registers['read']['propositions']);
            $this->assertSame([], $registers['write']['propositions']);
            $this->assertSame(1, substr_count(json_encode($catalogue), 'C3-BODY-past'));
            $this->assertCount(3, array_filter($catalogue, fn ($r) => isset($r['loaded_inline_content'])));
        }
        $this->assertSame($before, $this->snapshot());
        $viewer = (new Participant)->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'Unloaded']);
        $viewer->save();
        [$catalogue] = $this->project($f, $e2, viewer: $viewer);
        $this->assertCount(3, $catalogue);
        $this->assertSame([], array_filter($catalogue, fn ($r) => $r['loaded'] || isset($r['loaded_inline_content'])));
        $this->assertDatabaseHas('participant_receipt_contexts', ['participant_id' => $f->viewer->id, 'receipt_id' => $other->id, 'cleared_at' => null]);
    }

    public function test_distinguish_keeps_loaded_write_evidence_and_commit_retains_only_its_source(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $foundation = $this->receipt($f, $f->seed, 'origin');
        $source = $f->node($f->seed, 'canon');
        $carried = $this->receipt($f, $source, 'carried');
        $hidden = $f->node($f->seed, 'activity');
        $unselected = $this->receipt($f, $hidden, 'unselected');
        $commit = $f->node($source, 'canon', 'commit', 'committal');
        $child = $f->node($commit, 'activity');
        $writeReceipt = $this->receipt($f, $child, 'write');
        [$catalogue, $registers] = $this->project($f, $commit, 'distinguish', [$child]);
        $this->assertEqualsCanonicalizing([$foundation->id, $carried->id, $writeReceipt->id], array_column($catalogue, 'id'));
        $this->assertCount(3, array_filter($catalogue, fn ($r) => isset($r['loaded_inline_content'])));
        $this->assertSame([$writeReceipt->id], $registers['write']['propositions'][0]['receipts']);
        [$later] = $this->project($f, $child);
        $this->assertEqualsCanonicalizing([$carried->id, $writeReceipt->id], array_column($later, 'id'));
        $this->assertNotContains($unselected->id, array_column($later, 'id'));
        [$beforeCommit] = $this->project($f, $source);
        $this->assertNotContains($writeReceipt->id, array_column($beforeCommit, 'id'));
    }

    public function test_direct_foreign_counterpart_does_not_expand_its_history_or_grant_actions(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $foreign = $f->node($f->gate, 'canon', 'commit', 'committal');
        $parent = $f->node($foreign, 'activity');
        $counterpart = $f->node($parent, 'activity');
        $r = $this->receipt($f, $counterpart, 'counterpart');
        $hidden = $this->receipt($f, $parent, 'foreign-history');
        $registers = ['read' => ['propositions' => [['id' => $counterpart->id, 'relation' => 'counterpart']]],
            'gate' => ['propositions' => [['id' => $f->gate->id]]], 'write' => ['propositions' => []]];
        $state = ['participant' => $f->viewer->only(['id']), 'plan' => ['gate_id' => $f->gate->id,
            'phase' => ['id' => $f->phase->id, 'kind' => 'mediate'], 'schema' => ['fixture' => 'unchanged']]];
        $before = $this->snapshot();
        $catalogue = app(ParticipantReceiptProjection::class)->normalize($registers, $state);
        $this->assertSame([$r->id], array_column($catalogue, 'id'));
        $this->assertNotContains($hidden->id, array_column($catalogue, 'id'));
        $this->assertSame(['fixture' => 'unchanged'], $state['plan']['schema']);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_authorized_terminal_encounter_preserves_evidence_when_directly_selected_without_projection_mutation(): void
    {
        // Real Origin entry, dispatched Variant/Persist and dispatched Instrument.
        // Only the numerical boundary is isolated, as in DirectTragedyResolutionTest.
        $f = new GovernedExpressionFixture;
        $f->phase = app(PhaseProgressionService::class)->advance($f->phase, $f->expression);
        $this->controlledBirthClosureFixture(1, $f->expression->id);
        app(PropositionMeasurementService::class)->project($f->expression, 1, 1, 1);
        app(PotentialProjectionService::class)->rebuild($f->actor->substrate_id, false);
        $this->resolveExpressiveFieldFixture(1);
        app(ContributionDispatcher::class)->dispatch($f->actor, $f->phase, ['action' => 'instrument.execute',
            'proposition_id' => $f->expression->id,
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/c3-terminal']]);
        $this->assertDatabaseHas('terminal_encounters', ['proposition_id' => $f->expression->id, 'action' => 'instrument.execute']);
        $this->assertTrue(app(TerminalContinuationService::class)->closed($f->expression));
        $plan = app(InputPlanService::class)->forPhase($f->actor, $f->phase);
        $state = ['participant' => $f->actor->only(['id', 'callsign', 'designation']),
            'plan' => app(ParticipantContextSerializer::class)->plan($plan)];
        $before = $this->snapshot();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $packet = app(ParticipantContextSerializer::class)->inference($state);
        foreach (DB::getQueryLog() as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|alter|create|drop)\b/i', $query['query']);
        }
        DB::disableQueryLog();
        // The real planner may remove a closed Write. C3 does not override that
        // operational selection. Exercise the conditional visibility contract
        // explicitly against the genuinely dispatched terminal record.
        foreach (['read', 'gate', 'write'] as $role) {
            $registers = array_fill_keys(['read', 'gate', 'write'], ['propositions' => []]);
            $registers[$role]['propositions'] = [['id' => $f->expression->id]];
            $catalogue = app(ParticipantReceiptProjection::class)->normalize($registers, $state);
            $ids = $registers[$role]['propositions'][0]['receipts'];
            $this->assertNotEmpty($ids);
            foreach ($ids as $id) {
                $this->assertArrayHasKey('loaded_inline_content', collect($catalogue)->firstWhere('id', $id));
            }
        }
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(json_encode($plan, JSON_THROW_ON_ERROR),
            json_encode(app(InputPlanService::class)->forPhase($f->actor, $f->phase), JSON_THROW_ON_ERROR));
    }

    public function test_unresolved_basin_grants_no_extra_discovery_but_keeps_direct_evidence(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        // Construct incomplete ownership initially; established ownership is immutable.
        $cycle = (new Cycle)->forceFill(['participant_id' => $f->viewer->id,
            'sequence' => $f->viewer->cycles()->max('sequence') + 1]);
        $cycle->save();
        $phase = (new Phase)->forceFill(['cycle_id' => $cycle->id,
            'kind' => PhaseKind::Persist, 'write_register' => 'activity', 'started_at' => now()]);
        $phase->save();
        $gate = (new Proposition)->forceFill(['phase_id' => $phase->id,
            'tier' => 'expressive', 'register' => 'activity', 'statement' => 'Incomplete isolated fixture']);
        $gate->save();
        $direct = $this->receipt($f, $gate, 'direct');
        $child = $f->node($f->gate, 'frontier');
        $this->receipt($f, $child, 'unresolved-child');
        [$catalogue] = $this->project($f, $gate);
        $this->assertSame([$direct->id], array_column($catalogue, 'id'));
        $this->assertArrayHasKey('loaded_inline_content', $catalogue[0]);
    }

    private function receipt(ParticipantContextFixture $f, Proposition $node, string $tag)
    {
        $service = app(InstrumentExecutionService::class);

        return $service->complete($service->start($f->phase, $node, 'hyperlink', 'https://example.test/'.$tag), $node,
            verification: new VerifiedHyperlink('example.test/'.$tag, hash('sha256', $tag), CarbonImmutable::now(), contentExcerpt: 'C3-BODY-'.$tag));
    }

    private function project(ParticipantContextFixture $f, Proposition $gate, string $kind = 'persist', array $writes = [], ?Participant $viewer = null): array
    {
        $registers = ['read' => ['propositions' => []], 'gate' => ['propositions' => [['id' => $gate->id]]],
            'write' => ['propositions' => array_map(fn ($node) => ['id' => $node->id], $writes)]];
        $catalogue = app(ParticipantReceiptProjection::class)->normalize($registers, [
            'participant' => ['id' => ($viewer ?? $f->viewer)->id],
            'plan' => ['gate_id' => $gate->id, 'phase' => ['id' => $f->phase->id, 'kind' => $kind]],
        ]);
        foreach ($registers as $register) {
            foreach ($register['propositions'] as $node) {
                foreach ($node['receipts'] as $id) {
                    $this->assertCount(1, array_filter($catalogue, fn ($receipt) => $receipt['id'] === $id));
                }
            }
        }

        return [$catalogue, $registers];
    }

    private function snapshot(): array
    {
        $tables = ['propositions', 'supersessions', 'attestations', 'receipts', 'instrument_executions',
            'proposition_receipt_links', 'receipt_consumptions', 'participant_receipt_contexts', 'receipt_context_events',
            'proposition_measurements', 'commitment_snapshots', 'phases', 'cycles', 'participants',
            'context_horizons', 'terminal_encounters', 'tragedies', 'commit_region_exhaustions', 'substrate_ledger_entries'];

        return collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()
            ->map(fn ($row) => json_encode($row))->sort()->values()->all()])->all();
    }
}
