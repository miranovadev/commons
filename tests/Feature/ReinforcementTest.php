<?php

namespace Tests\Feature;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Reinforcement;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\CommitmentAdmissibilityService;
use App\Services\CommitmentSnapshotService;
use App\Services\Evidence\HyperlinkVerifier;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\Observables\ContextLineageComparisonService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\Observables\ReinforcementService;
use App\Services\OperationalInstrumentService;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionMeasurementService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReinforcementTest extends TestCase
{
    use RefreshDatabase;

    private Proposition $node;

    private Phase $later;

    protected function setUp(): void
    {
        parent::setUp();
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'reinforcement', 'title' => 'Reinforcement', 'visibility' => 'public', 'configuration' => []])->save();
        $creation = $this->phase($substrate);
        $this->node = new Proposition;
        $this->node->forceFill(['phase_id' => $creation->id, 'register' => 'canon', 'tier' => 'expressive', 'statement' => 'Persistence must be earned.'])->save();
        app(LedgerService::class)->record($this->node);
        $this->later = $this->phase($substrate, $creation->cycle->participant);
        $attestation = new Attestation;
        $attestation->forceFill(['phase_id' => $this->later->id, 'proposition_id' => $this->node->id, 'direction' => 'support'])->save();
        app(LedgerService::class)->record($attestation);
    }

    public function test_citation_and_unverified_metadata_cannot_reinforce_or_unlock_commit(): void
    {
        app(PropositionMeasurementService::class)->project($this->node, 1, 1, 1);
        $cite = $this->receipt('citation', $this->node->id);
        $this->assertNull($cite->signature);
        $execution = app(InstrumentExecutionService::class)->start($this->later, $this->node, 'hyperlink', 'https://example.test/page');
        $forged = app(InstrumentExecutionService::class)->complete($execution, $this->node, 'user-signature',
            ['content_verified' => true, 'last_modified' => '2000-01-01', 'fingerprint' => 'fake']);
        $this->assertNull($forged->signature);
        $this->assertFalse($forged->metadata['content_verified']);
        $this->assertSame(2, Receipt::count());
        $this->assertSame(0, Reinforcement::count());
        $gate = app(CommitmentAdmissibilityService::class)->evaluate($this->node, $this->later);
        $this->assertTrue($gate['requirements']['receipt']);
        $this->assertTrue($gate['requirements']['closure']);
        $this->assertFalse($gate['admissible']);
        $this->assertContains('missing_counted_reinforcement', $gate['reasons']);
    }

    public function test_signatures_are_shared_but_only_one_reinforcement_per_participant_counts(): void
    {
        $first = $this->receipt('hyperlink', 'https://EXAMPLE.test/article?id=123#section');
        $duplicate = $this->receipt('hyperlink', 'http://example.test/article?utm_source=x');
        $otherSource = $this->receipt('hyperlink', 'https://example.test/another');
        $this->assertSame($first->signature, $duplicate->signature);
        $this->assertNotSame($first->signature, $otherSource->signature);
        $this->assertSame('participant-already-counted', $duplicate->metadata['reinforcement_status']);
        $other = $this->phase($this->later->cycle->participant->substrate);
        $second = $this->receipt('hyperlink', 'https://example.test/article', $other);
        $this->assertSame($first->signature, $second->signature);
        $this->assertSame(2, $this->reinforcementCount());
        $this->assertSame(4, Receipt::count());
        $this->assertSame(4, DB::table('substrate_ledger_entries')->where('kind', 'receipt')->count());
        $this->assertSame(2, app(ReinforcementService::class)->count($this->node, new ParticipationContext($this->later)));
        $this->assertTrue(app(CommitmentAdmissibilityService::class)->evaluate($this->node, $this->later)['admissible']);
    }

    public function test_originating_evidence_does_not_count_but_author_can_revisit_later(): void
    {
        $origin = $this->receipt('hyperlink', 'https://example.test/page', $this->node->phase);
        $this->assertNotNull($origin->signature);
        $this->assertSame('originating-evidence', $origin->metadata['reinforcement_status']);
        $this->assertSame(0, $this->reinforcementCount());
        $later = $this->receipt('hyperlink', 'https://example.test/page');
        $this->assertSame($origin->signature, $later->signature);
        $this->assertSame(1, $this->reinforcementCount());
    }

    public function test_local_closure_forms_before_attenuation_and_duplicate_events_add_no_weight(): void
    {
        $local = app(LocalMeasurementService::class);
        $before = $local->calculate($this->node);
        $this->receipt();
        $after = $local->calculate($this->node);
        $this->assertSame($before->depth, $after->depth);
        $this->assertSame($before->cadence, $after->cadence);
        $this->assertEqualsWithDelta(round(($before->depth + $before->cadence + 1 / 3) / (2 + 1 / 3), 7), $after->closure()->average(), 1e-7);
        $this->assertGreaterThan($before->closure()->average(), $after->closure()->average());
        $this->receipt('hyperlink', 'https://example.test/different');
        $this->assertEquals($after, $local->calculate($this->node));

        $child = new Proposition;
        $child->forceFill(['phase_id' => $this->later->id, 'register' => 'canon', 'tier' => 'expressive', 'statement' => 'A child with fresh evidence state.'])->save();
        app(LedgerService::class)->record($child);
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $this->later->id, 'source_proposition_id' => $this->node->id, 'target_proposition_id' => $child->id, 'type' => 'variant'])->save();
        app(LedgerService::class)->record($edge);
        $this->assertSame(0, app(ReinforcementService::class)->count($child));
        $childClosure = $local->calculate($child)->closure()->average();
        $weight = LineageMeasurementService::attenuation(1);
        $expected = round(($childClosure + $after->closure()->average() * $weight) / (1 + $weight), 7);
        $this->assertEqualsWithDelta($expected, app(LineageMeasurementService::class)->calculate($child)->closure()->average(), 1e-7);
        $this->assertEqualsWithDelta($expected, app(LineageMeasurementService::class)->calculate($child, context: new ParticipationContext($this->later))->closure()->average(), 1e-7);
    }

    public function test_source_updates_require_observed_stability_and_timestamps_cannot_accelerate_it(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-01'));
        $version = 'original';
        $this->mock(HyperlinkVerifier::class, function ($mock) use (&$version) {
            $mock->shouldReceive('verify')->andReturnUsing(function () use (&$version) {
                return new VerifiedHyperlink('example.test/page', hash('sha256', $version), CarbonImmutable::now(), CarbonImmutable::parse('2000-01-01'));
            });
        });
        $first = $this->receipt();
        $version = 'changed';
        $this->travel(1)->minutes();
        $changed = $this->receipt();
        $this->assertNull($changed->signature);
        $this->travel(6)->days();
        $this->assertNull($this->receipt()->signature);
        $this->travel(2)->days();
        $eligible = $this->receipt();
        $this->assertNotNull($eligible->signature);
        $this->assertNotSame($first->signature, $eligible->signature);
        $this->assertSame(1, $this->reinforcementCount());
        $this->assertSame(4, Receipt::count());
        $version = 'churn';
        $this->assertNull($this->receipt()->signature);
        $this->travel(8)->days();
        $version = 'another mutation';
        $this->assertNull($this->receipt()->signature, 'A newly seen body starts its own stability interval.');
    }

    public function test_failed_verification_records_history_without_reinforcement(): void
    {
        $this->mock(HyperlinkVerifier::class, fn ($mock) => $mock->shouldReceive('verify')->andThrow(new DomainException('Unavailable.')));
        $receipt = $this->receipt();
        $this->assertFalse($receipt->metadata['content_verified']);
        $this->assertNull($receipt->signature);
        $this->assertSame(0, $this->reinforcementCount());
        $this->assertNotNull($receipt->ledgerEntry);
    }

    public function test_commit_inherits_reinforcement_by_reference_but_expressive_alternatives_do_not(): void
    {
        $receipt = $this->receipt();
        $comparison = app(ContextLineageComparisonService::class)->prospectiveCommit($this->node, $this->later);
        $preview = $comparison->current->propositions->last();
        $this->assertSame(1, app(ReinforcementService::class)->count($preview));
        foreach (['commit', 'reconcile', 'variant'] as $type) {
            $node = new Proposition;
            $node->forceFill(['phase_id' => $this->later->id, 'register' => 'canon', 'tier' => $type === 'commit' ? 'committal' : 'expressive', 'statement' => $type])->save();
            app(LedgerService::class)->record($node);
            $edge = new Supersession;
            $edge->forceFill(['phase_id' => $this->later->id, 'source_proposition_id' => $this->node->id, 'target_proposition_id' => $node->id, 'type' => $type])->save();
            app(LedgerService::class)->record($edge);
            $this->assertSame($type === 'commit' ? 1 : 0, app(ReinforcementService::class)->count($node));
        }
        $this->assertSame($this->node->id, $receipt->fresh()->receiptable_id);
        $this->assertSame(1, Reinforcement::count());
    }

    public function test_snapshot_boundary_cannot_bypass_reinforcement_with_strong_measurements(): void
    {
        $this->node->tier = 'committal';
        $this->node->save();
        $this->receipt('citation', $this->node->id);
        app(PropositionMeasurementService::class)->project($this->node, 1, 1, 1);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('counted Reinforcement');
        app(CommitmentSnapshotService::class)->record($this->node, 1, 0, LineageMeasurementService::VERSION);
    }

    public function test_database_prevents_duplicate_participant_allocation(): void
    {
        $this->receipt();
        $duplicate = $this->receipt('hyperlink', 'https://example.test/other');
        $this->expectException(QueryException::class);
        DB::table('reinforcements')->insert(['id' => strtolower((string) str()->ulid()), 'proposition_id' => $this->node->id,
            'participant_id' => $this->later->cycle->participant_id, 'receipt_id' => $duplicate->id, 'created_at' => now()]);
    }

    public function test_real_verifier_flows_through_receipt_allocation_and_local_measurement(): void
    {
        $this->app->bind(HyperlinkVerifier::class, HyperlinkVerifier::class);
        $this->mock(\App\Services\Evidence\PublicResourceAddress::class,
            fn ($mock) => $mock->shouldReceive('resolve')->once()->with('example.test')->andReturn('93.184.216.34'));
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake(['https://example.test/page' => \Illuminate\Support\Facades\Http::response('Verified body', 200, ['Content-Type' => 'text/plain'])]);
        $before = app(LocalMeasurementService::class)->calculate($this->node)->closure()->average();
        $receipt = $this->receipt('hyperlink', 'https://EXAMPLE.test/page?tracking=x#part');
        $this->assertTrue($receipt->metadata['content_verified']);
        $this->assertSame(hash('sha256', 'Verified body'), $receipt->metadata['fingerprint']);
        $this->assertSame('counted', $receipt->metadata['reinforcement_status']);
        $this->assertSame(1, $this->reinforcementCount());
        $this->assertGreaterThan($before, app(LocalMeasurementService::class)->calculate($this->node)->closure()->average());
    }

    private function reinforcementCount(): int
    {
        return app(ReinforcementService::class)->count($this->node);
    }

    private function receipt(string $kind = 'hyperlink', string $reference = 'https://example.test/page', ?Phase $phase = null): Receipt
    {
        return app(OperationalInstrumentService::class)->execute($phase ?? $this->later, $this->node, compact('kind', 'reference'));
    }

    private function phase(Substrate $substrate, ?Participant $participant = null): Phase
    {
        if ($participant === null) {
            $participant = new Participant;
            $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'Observer '.Participant::count()])->save();
        }
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => $participant->cycles()->count() + 1])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Order, 'write_register' => 'canon', 'started_at' => now()])->save();

        return $phase;
    }
}
