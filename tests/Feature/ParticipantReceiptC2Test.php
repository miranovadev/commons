<?php

namespace Tests\Feature;

use App\Domain\Evidence\SearchObservation;
use App\Domain\Evidence\VerifiedHyperlink;
use App\Models\Participant;
use App\Models\ParticipantReceiptContext;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\ContributionDispatcher;
use App\Services\Evidence\ReceiptEvidenceService;
use App\Services\InputPlanService;
use App\Services\InstrumentExecutionService;
use App\Services\ParticipantReceiptProjection;
use App\Services\Participation\ParticipationContext;
use App\Services\ReceiptContextService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class ParticipantReceiptC2Test extends TestCase
{
    use RefreshDatabase;

    public static function visibility(): array
    {
        $cases = [];
        foreach ([['read'], ['gate'], ['write'], ['read', 'gate'], ['read', 'write'], ['gate', 'write'], ['read', 'gate', 'write']] as $roles) {
            foreach ([true, false] as $loaded) {
                $cases[implode('-', $roles).($loaded ? '-loaded' : '-unloaded')] = [$roles, $loaded];
            }
        }

        return $cases;
    }

    private function receipt(ParticipantContextFixture $f, ?Proposition $node = null): Receipt
    {
        $node ??= $f->gate;
        $service = app(InstrumentExecutionService::class);

        return $service->complete($service->start($f->phase, $node, 'hyperlink', 'https://example.test/body'), $node,
            verification: new VerifiedHyperlink('example.test/body', hash('sha256', 'body'), CarbonImmutable::now(), contentExcerpt: 'C2-IMMUTABLE-BODY'));
    }

    private function catalogue(ParticipantContextFixture $f, $receipt, array $roles, ?Participant $viewer = null): array
    {
        $registers = array_fill_keys(['read', 'gate', 'write'], ['propositions' => []]);
        foreach ($roles as $role) {
            $registers[$role]['propositions'][] = ['id' => $f->gate->id, 'receipts' => [$receipt->only(['id', 'type', 'reference'])]];
        }

        return app(ParticipantReceiptProjection::class)->normalize($registers,
            ['participant' => ['id' => ($viewer ?? $f->viewer)->id], 'plan' => ['phase' => ['id' => $f->phase->id], 'receipts' => []]]);
    }

    #[DataProvider('visibility')]
    public function test_visibility_depends_on_load_and_read_or_gate_without_mutating_history(array $roles, bool $loaded): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $r = $this->receipt($f);
        if (! $loaded) {
            app(ReceiptContextService::class)->clear($f->viewer, $f->phase, $r->id);
        }
        $before = $this->snapshot();
        $catalogue = $this->catalogue($f, $r, $roles);
        $this->assertCount(1, $catalogue);
        $this->assertSame($loaded, $catalogue[0]['loaded']);
        $visible = $loaded && $roles !== ['write'];
        $this->assertSame($visible, array_key_exists('loaded_inline_content', $catalogue[0]));
        $this->assertSame($visible ? 1 : 0, substr_count(json_encode($catalogue), 'C2-IMMUTABLE-BODY'));
        $this->assertSame($before, $this->snapshot());
    }

    public function test_creator_auto_load_is_personal_and_failure_is_distinct_from_empty_and_missing_evidence(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $other = (new Participant)->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'Other']);
        $other->save();
        $service = app(InstrumentExecutionService::class);
        $empty = $service->complete($service->start($f->phase, $f->gate, 'web_search', 'query', ['query' => 'query']), $f->gate,
            observation: new SearchObservation('query', CarbonImmutable::now(), [], 'fixture'));
        $failed = $service->complete($service->start($f->phase, $f->gate, 'web_search', 'failure'), $f->gate,
            metadata: ['validation' => 'search-failed', 'observation_error' => 'Unavailable']);
        $missing = $service->complete($service->start($f->phase, $f->gate, 'hyperlink', 'missing'), $f->gate);
        $this->assertSame(3, ParticipantReceiptContext::active()->where('participant_id', $f->viewer->id)->count());
        $this->assertSame([], $this->catalogue($f, $empty, ['gate'])[0]['loaded_inline_content']['results']);
        $failure = $this->catalogue($f, $failed, ['read'])[0]['loaded_inline_content'];
        $this->assertFalse($failure['content_verified']);
        $this->assertSame('Unavailable', $failure['observation_error']);
        $this->assertNull(ParticipantReceiptContext::where('receipt_id', $failed->id)->value('evidence_signature'));
        $this->assertArrayNotHasKey('loaded_inline_content', $this->catalogue($f, $missing, ['gate'])[0]);
        $this->assertFalse($this->catalogue($f, $empty, ['gate'], $other)[0]['loaded']);
        $this->assertArrayNotHasKey('loaded_inline_content', $this->catalogue($f, $empty, ['gate'], $other)[0]);
    }

    public function test_instrument_transaction_rollback_removes_receipt_and_creator_load(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $before = $this->snapshot();
        try {
            DB::transaction(function () use ($f) {
                $this->receipt($f);
                throw new DomainException('Rollback');
            });
        } catch (DomainException $e) {
            $this->assertSame('Rollback', $e->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
        $this->assertDatabaseCount('instrument_executions', 0);
    }

    public function test_instrument_failure_without_a_receipt_creates_no_load(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $service = app(InstrumentExecutionService::class);
        $execution = $service->start($f->phase, $f->gate, 'hyperlink', 'https://example.test/unavailable');
        $service->fail($execution, 'Failed before an observation was recorded.');
        $this->assertSame('failed', $execution->fresh()->status);
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseCount('participant_receipt_contexts', 0);
        $this->assertDatabaseCount('receipt_context_events', 0);
    }

    public function test_hydrated_committal_read_preserves_recorded_receipt_inheritance(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $r = $this->receipt($f);
        $commit = $f->node($f->gate, 'canon', 'commit', 'committal');
        $registers = ['read' => ['propositions' => [['id' => $commit->id]]],
            'gate' => ['propositions' => []], 'write' => ['propositions' => []]];
        $before = $this->snapshot();
        $catalogue = app(ParticipantReceiptProjection::class)->normalize($registers,
            ['participant' => ['id' => $f->viewer->id], 'plan' => ['phase' => ['id' => $f->phase->id], 'receipts' => []]]);
        $this->assertSame([$r->id], $registers['read']['propositions'][0]['receipts']);
        $this->assertSame([$r->id], array_column($catalogue, 'id'));
        $this->assertStringContainsString('C2-IMMUTABLE-BODY', json_encode($catalogue));
        $this->assertSame($before, $this->snapshot());
    }

    public function test_loaded_historical_citation_uses_canonical_content_without_operational_activation(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $service = app(InstrumentExecutionService::class);
        $receipt = $service->complete($service->start($f->phase, $f->gate, 'citation', $f->seed->id), $f->gate);
        $context = new ParticipationContext($f->phase);
        // Historical cognitive Reads may be outside the current operational working set.
        $context->propositions->forget($f->gate->id);
        $receipt->context_horizon_id = 'historical-horizon';
        $evidence = app(ReceiptEvidenceService::class);
        $this->assertNull($evidence->describe($receipt, $context));
        $body = $evidence->citation($receipt, $context)['evidence'];
        $this->assertSame($f->seed->id, $body['proposition_id']);
        $this->assertSame($f->seed->statement, $body['statement']);
        $this->assertNull($evidence->describe($receipt, $context));
        $this->assertNotSame('historical-horizon', $receipt->fresh()->context_horizon_id);
    }

    public function test_unload_rejects_unowned_foreign_substrate_and_stale_phase_without_mutation(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $foreign = new ParticipantContextFixture(1, 0, false);
        $r = $this->receipt($foreign);
        $context = app(ReceiptContextService::class);
        $reject = function ($phase) use ($f, $r, $context) {
            $before = $this->snapshot();
            try {
                DB::transaction(fn () => $context->desired($f->viewer, $phase, ['unload' => [$r->id]]));
                $this->fail('Invalid personal unload was accepted.');
            } catch (DomainException) {
                $this->assertSame($before, $this->snapshot());
            }
        };
        $reject($f->phase); // Another Participant's load grants no personal authority.
        // An inconsistent historical row cannot bypass the same-substrate guard.
        (new ParticipantReceiptContext)->forceFill(['participant_id' => $f->viewer->id, 'receipt_id' => $r->id,
            'proposition_id' => $foreign->gate->id, 'loaded_at' => now()])->save();
        $this->assertNotContains($r->id, $context->unloadOptions($f->phase));
        $reject($f->phase);
        $reject($foreign->phase); // The Phase must belong to this Participant.
        $f->phase->forceFill(['completed_at' => now()])->save();
        $reject($f->phase);
    }

    public function test_write_loading_and_hidden_personal_unloading_preserve_authority_and_immutable_receipts(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $write = Proposition::findOrFail($f->writes[0]);
        $r = $this->receipt($f, $write);
        $context = app(ReceiptContextService::class);
        $context->clear($f->viewer, $f->phase, $r->id);
        $this->publishPotentialFixture($f->viewer);
        $schema = app(InputPlanService::class)->forPhase($f->viewer, $f->phase)['schema'];
        $this->assertContains($r->id, $schema['$defs']['actions']['attestation.create']['properties']['receipt_options']['properties']['load']['items']['enum']);
        $this->assertSame(0, $schema['$defs']['actions']['attestation.create']['properties']['receipt_options']['properties']['unload']['maxItems']);
        app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, ['action' => 'attestation.create',
            'proposition_id' => $write->id, 'direction' => 'support', 'receipt_options' => ['load' => [$r->id]]]);
        $events = DB::table('receipt_context_events')->count();
        DB::transaction(fn () => $context->desired($f->viewer, $f->phase, ['load' => [$r->id]]));
        $this->assertDatabaseCount('receipt_context_events', $events);
        $this->assertArrayNotHasKey('loaded_inline_content', $this->catalogue($f, $r, ['write'])[0]);
        $this->assertArrayHasKey('loaded_inline_content', $this->catalogue($f, $r, ['gate'])[0]);

        // A different branch's loaded Receipt is not a current load option, but remains unloadable.
        $foreign = $f->node(null, 'canon', 'variant', 'committal');
        $hidden = $this->receipt($f, $f->node($foreign, 'activity'));
        $immutable = $hidden->fresh()->getAttributes();
        $this->assertNotContains($hidden->id, $context->loadOptions($f->phase)->pluck('id'));
        $this->assertContains($hidden->id, $context->unloadOptions($f->phase));
        DB::transaction(fn () => $context->desired($f->viewer, $f->phase, ['unload' => [$hidden->id]]));
        $this->assertSame($immutable, $hidden->fresh()->getAttributes());
        $this->expectException(DomainException::class);
        DB::transaction(fn () => $context->desired($f->viewer, $f->phase, ['unload' => [$hidden->id]]));
    }

    private function snapshot(): array
    {
        return collect(['receipts', 'participant_receipt_contexts', 'receipt_context_events', 'supersessions',
            'proposition_measurements', 'substrate_ledger_entries', 'phases', 'participants'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy($table === 'proposition_measurements' ? 'proposition_id' : 'id')->get()->toJson()])->all();
    }
}
