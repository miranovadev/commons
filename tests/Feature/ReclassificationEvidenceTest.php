<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\ParticipantReceiptContext;
use App\Models\Receipt;
use App\Services\ContributionDispatcher;
use App\Services\Observables\ReinforcementService;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class ReclassificationEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reclassification_preserves_evidence_and_content_without_copying_judgments_or_uptake(): void
    {
        $f = new ParticipantContextFixture(1, 0);
        $source = $f->node($f->seed, 'frontier', position: PhaseKind::Persist);
        $source->forceFill(['statement' => "  Exact content\nwith whitespace.  "])->save();
        $receipt = $this->loadedReceiptFixture($source);
        $f->evidence($source); // Old attestation evidence must not follow the new classification.
        $f->phase->forceFill(['kind' => PhaseKind::Constrain, 'gate_proposition_id' => $source->id, 'write_register' => 'canon'])->save();
        $loads = ParticipantReceiptContext::count();
        $receipts = Receipt::count();
        $target = app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase->fresh(), $this->reclassificationInputFixture($f->phase->fresh(), $source));
        $state = app(PropositionStateService::class);
        $counts = app(ReinforcementService::class);
        $this->assertSame($source->statement, $target->statement);
        $this->assertSame([$receipt->id], $state->receipts($target)->pluck('id')->all());
        $this->assertCount(0, $state->attestations(collect([$target])));
        $this->assertSame(0, $state->touchCount($target));
        $this->assertSame($loads, ParticipantReceiptContext::count());
        $this->assertSame($receipts, Receipt::count());
        $this->assertSame(1, $counts->count($source));
        $this->assertSame(1, $counts->count($target));
        $this->assertSame(1, $counts->count($source));
        $this->assertSame(1, $counts->count($target));
        $context = new ParticipationContext($f->phase->fresh());
        $this->assertSame([$receipt->id], $state->receipts($target, $context)->pluck('id')->all());
        $this->assertSame(1, $counts->count($target, $context));
        $this->assertSame($target->id, $receipt->fresh()->consumption->proposition_id);
        $this->assertSame(1, DB::table('proposition_receipt_links')->count());
        $counts->loadCounts(collect([$source, $target]));
        $this->assertSame(1, $target->reinforcement_count);
    }

    public function test_reclassification_rejects_authored_text_even_for_direct_dispatch_callers(): void
    {
        $f = new ParticipantContextFixture(1, 0);
        $f->gate = $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
        $f->phase->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $f->gate->id])->save();
        $f->evidence($f->gate);
        $this->expectException(\DomainException::class);

        app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase->fresh(), ['action' => 'proposition.reclassify', 'statement' => 'Changed']);
    }
}
