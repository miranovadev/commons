<?php

namespace Tests\Feature;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\AttestationEvidenceService;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\Observables\ReinforcementService;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionStateService;
use App\Services\ReceiptContextService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class PropositionReceiptWeightTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposition_loads_are_neutral_and_survive_attestation_supersession(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $other = (new Participant)->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'other']);
        $other->save();
        $cycle = (new Cycle)->forceFill(['participant_id' => $other->id, 'context_horizon_id' => $f->phase->cycle->context_horizon_id, 'sequence' => 1]);
        $cycle->save();
        $otherPhase = (new Phase)->forceFill(['cycle_id' => $cycle->id, 'kind' => $f->phase->kind,
            'gate_proposition_id' => $f->gate->id, 'write_register' => $f->phase->write_register, 'started_at' => now()]);
        $otherPhase->save();
        $attest = function ($phase, $direction, $previous = null) use ($f) {
            if ($previous !== null) {
                $phase = $phase->replicate();
                $phase->save();
            }
            $vote = (new Attestation)->forceFill(['phase_id' => $phase->id, 'proposition_id' => $f->gate->id,
                'direction' => $direction, 'supersedes_attestation_id' => $previous?->id]);
            $vote->save();
            app(LedgerService::class)->record($vote);

            return $vote;
        };
        $a = $attest($f->phase, 'support');
        $b = $attest($otherPhase, 'refute');
        $executions = app(InstrumentExecutionService::class);
        $r = $executions->complete($executions->start($f->phase, $f->gate, 'hyperlink', 'https://example.test/weight'), $a,
            verification: new VerifiedHyperlink('example.test/weight', hash('sha256', 'weight'), CarbonImmutable::now()));
        $this->assertSame((new Proposition)->getMorphClass(), $r->receiptable_type);
        $this->assertSame($f->gate->id, $r->receiptable_id);
        $count = fn () => app(ReinforcementService::class)->count($f->gate);
        $value = fn () => app(AttestationEvidenceService::class)->valueFor(collect([$f->gate]));
        $this->assertSame(0.0, $value());
        $this->assertSame(1, $count());
        app(ReceiptContextService::class)->read($f->viewer, $f->phase, $r->id);
        $this->assertSame(1, $count());
        $this->assertSame(0.0, $value());
        $replacement = $attest($f->phase, 'refute', $a);
        $this->assertSame(-1.0, $value());
        $attest($otherPhase, 'support', $b);
        $this->assertSame(0.0, $value());
        app(ReceiptContextService::class)->read($other, $otherPhase, $r->id);
        $this->assertSame(2, $count());
        $this->assertSame(0.0, $value());
        app(ReceiptContextService::class)->clear($other, $otherPhase, $r->id);
        $this->assertSame(1, $count());
        $this->assertSame(0.0, $value());
        $this->assertEqualsWithDelta($value(), app(AttestationEvidenceService::class)->valueFor(collect([$f->gate]), new ParticipationContext($f->phase)), 1e-9);
        $this->assertSame($r->id, $r->fresh()->id);
        $this->assertSame($replacement->id, $a->fresh()->supersededBy->id);
    }

    public function test_historical_attestation_owned_evidence_stays_readable_after_supersession_without_rewriting_it(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $a = (new Attestation)->forceFill(['phase_id' => $f->phase->id, 'proposition_id' => $f->gate->id, 'direction' => 'support']);
        $a->save();
        app(LedgerService::class)->record($a);
        $executions = app(InstrumentExecutionService::class);
        $r = $executions->complete($executions->start($f->phase, $f->gate, 'hyperlink', 'https://example.test/legacy'), $a,
            verification: new VerifiedHyperlink('example.test/legacy', hash('sha256', 'legacy'), CarbonImmutable::now()));
        // A historical storage fixture, not a new application write path.
        DB::table('receipts')->where('id', $r->id)->update(['receiptable_type' => $a->getMorphClass(), 'receiptable_id' => $a->id]);
        $original = $r->fresh()->getAttributes();
        $later = $f->phase->replicate();
        $later->save();
        $b = (new Attestation)->forceFill(['phase_id' => $later->id, 'proposition_id' => $f->gate->id,
            'direction' => 'refute', 'supersedes_attestation_id' => $a->id]);
        $b->save();
        app(LedgerService::class)->record($b);
        $this->assertContains($r->id, app(PropositionStateService::class)->receipts($f->gate)->pluck('id'));
        $this->assertContains($r->id, app(PropositionStateService::class)->receipts($f->gate, new ParticipationContext($f->phase))->pluck('id'));
        $load = app(ReceiptContextService::class)->read($f->viewer, $f->phase, $r->id);
        $this->assertSame($f->gate->id, $load->proposition_id);
        $this->assertEquals($original, $r->fresh()->getAttributes());
    }
}
