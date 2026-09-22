<?php

namespace Tests\Feature;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Models\Connection;
use App\Models\ParticipantReceiptContext;
use App\Models\Proposition;
use App\Services\HorizonReceiptService;
use App\Services\InstrumentExecutionService;
use App\Services\MediationReadService;
use App\Services\Observables\ReinforcementService;
use App\Services\ParticipantPromptFactory;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\ReceiptContextService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class ReceiptRegionContextTest extends TestCase
{
    use RefreshDatabase;

    private function receipt(ParticipantContextFixture $fixture, Proposition $node, string $name = 'evidence')
    {
        $service = app(InstrumentExecutionService::class);

        return $service->complete($service->start($fixture->phase, $node, 'hyperlink', 'https://example.test/'.$name), $node,
            verification: new VerifiedHyperlink('example.test/'.$name, hash('sha256', $name), CarbonImmutable::now(), contentExcerpt: 'BODY-'.$name));
    }

    public function test_ordinary_catalogue_shares_branches_and_reconciliations_but_never_crosses_a_commit(): void
    {
        $f = new ParticipantContextFixture(2, 0, false);
        $r = $this->receipt($f, $f->gate);
        $service = app(HorizonReceiptService::class);
        $this->assertContains($r->id, $service->available($f->phase)->pluck('id'));
        $sibling = $f->node($f->seed, 'activity');
        $reconciled = $f->node($f->gate, 'frontier', 'reconcile');
        $f->edge($sibling, $reconciled, 'reconcile');
        foreach ([$sibling, $reconciled] as $gate) {
            $f->phase->forceFill(['gate_proposition_id' => $gate->id])->save();
            $this->assertContains($r->id, $service->available($f->phase)->pluck('id'));
        }
        $commit = $f->node($reconciled, 'canon', 'commit', 'committal');
        $f->phase->forceFill(['gate_proposition_id' => $commit->id])->save();
        $this->assertSame($r->context_horizon_id, $f->phase->cycle->context_horizon_id);
        $this->assertNotContains($r->id, $service->available($f->phase)->pluck('id'));
        $target = $f->node($commit, 'activity');
        $this->expectException(\DomainException::class);
        DB::transaction(fn () => $service->consume($target->phase, $commit, $target, $r->id));
    }

    public function test_missing_governance_stays_unresolved_despite_valid_causal_parents(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $orphan = $f->node(null, 'activity');
        $r = $this->receipt($f, $orphan);
        $this->assertNotContains($r->id, app(HorizonReceiptService::class)->available($f->phase)->pluck('id'));
        $other = $f->node(null, 'canon', 'variant', 'committal');
        $f->edge($f->seed, $orphan, 'reconcile');
        $f->edge($other, $orphan, 'reconcile');
        $this->assertNotContains($r->id, app(HorizonReceiptService::class)->available($f->phase)->pluck('id'));
        $f->phase->forceFill(['gate_proposition_id' => $orphan->id])->save();
        $this->assertEmpty(app(HorizonReceiptService::class)->available($f->phase));
    }

    public function test_valid_governance_is_not_erased_by_malformed_secondary_provenance(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $orphan = $f->node(null, 'activity');
        $mixed = $f->node($f->seed, 'activity');
        $f->edge($orphan, $mixed, 'reconcile');
        $r = $this->receipt($f, $mixed);
        $this->assertContains($r->id, app(HorizonReceiptService::class)->available($f->phase)->pluck('id'));
        $f->edge($mixed, $orphan, 'reconcile');
        $this->assertContains($r->id, app(HorizonReceiptService::class)->available($f->phase)->pluck('id'));
    }

    public function test_consumption_preserves_origin_and_removes_shared_availability(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $r = $this->receipt($f, $f->gate);
        $original = $r->fresh()->getAttributes();
        $target = $f->node($f->gate, 'frontier');
        DB::transaction(fn () => app(HorizonReceiptService::class)->consume($target->phase, $f->gate, $target, $r->id));
        $this->assertEquals($original, $r->fresh()->getAttributes());
        $this->assertNotContains($r->id, app(HorizonReceiptService::class)->available($f->phase)->pluck('id'));
        $this->assertSame($target->id, $r->fresh()->consumption->proposition_id);
        $this->assertDatabaseCount('participant_receipt_contexts', 0);
    }

    public function test_only_loaded_causal_read_evidence_is_active_and_all_other_roles_are_metadata(): void
    {
        $f = new ParticipantContextFixture(2, 1, false);
        $read = app(MediationReadService::class)->anchorFor($f->phase);
        $nodes = [$read, $f->gate, Proposition::findOrFail($f->writes[0]), $f->chain[1]];
        $receipts = [];
        foreach ($nodes as $index => $node) {
            $r = $this->receipt($f, $node, 'role-'.$index);
            $receipts[] = $r;
            app(ReceiptContextService::class)->read($f->viewer, $f->phase, $r->id);
        }
        $this->publishPotentialFixture($f->viewer);
        $state = app(ParticipationService::class)->stateFor($f->viewer);
        $this->assertSame([$read->id], array_column($state['plan']['read_options'], 'id'));
        $prompt = app(ParticipantPromptFactory::class)->make(new Connection, $state);
        $this->assertSame([$receipts[0]->id], array_column(json_decode($prompt['evidence'], true)['loaded_receipts'], 'id'));
        $this->assertSame(1, substr_count($prompt['evidence'].$prompt['user'], 'BODY-role-0'));
        foreach ([1, 2, 3] as $index) {
            $this->assertStringNotContainsString('BODY-role-'.$index, $prompt['evidence'].$prompt['user']);
        }
        foreach (json_decode($prompt['user'], true)['plan']['receipts'] as $ref) {
            $this->assertEqualsCanonicalizing(['id', 'type', 'reference', 'loaded'], array_keys($ref));
            $this->assertTrue($ref['loaded']);
        }
        $this->assertSame(4, ParticipantReceiptContext::active()->count());
        $reinforcement = app(ReinforcementService::class);
        $this->assertSame(1, $reinforcement->count($read));
        app(ReceiptContextService::class)->clear($f->viewer, $f->phase, $receipts[0]->id);
        $this->publishPotentialFixture($f->viewer);
        $prompt = app(ParticipantPromptFactory::class)->make(new Connection, app(ParticipationService::class)->stateFor($f->viewer));
        $this->assertSame([], json_decode($prompt['evidence'], true)['loaded_receipts']);
        $this->assertSame(0, $reinforcement->count($read));
        $this->assertSame(1, $reinforcement->count($f->gate));
    }

    public function test_catalogue_query_count_is_constant_with_many_receipts_in_one_region(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $this->receipt($f, $f->gate, 'first');
        $measure = function () use ($f): array {
            $context = new ParticipationContext($f->phase);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $items = app(HorizonReceiptService::class)->available($f->phase, $context);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return [$queries, $items->count()];
        };
        [$before] = $measure();
        for ($i = 0; $i < 12; $i++) {
            $this->receipt($f, $f->gate, 'growth-'.$i);
        }
        [$after, $count] = $measure();
        $this->assertSame(13, $count);
        $this->assertSame($before, $after);
        $this->assertLessThanOrEqual(6, $after);
    }
}

