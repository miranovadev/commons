<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\ContributionDispatcher;
use App\Services\EvidenceExecutionService;
use App\Services\ExplorationEpochService;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\Participation\ParticipationContext;
use App\Services\PhaseGenerationService;
use App\Services\PotentialChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

class ExpressiveEvidenceGatingTest extends TestCase
{
    use ExhaustionFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeExhaustionFixture();
    }

    public static function expressivePhases(): array
    {
        return ['Distinguish' => [PhaseKind::Distinguish], 'Disperse' => [PhaseKind::Disperse]];
    }

    private function position(PhaseKind $kind): Phase
    {
        $gate = $kind === PhaseKind::Distinguish ? $this->origin
            : $this->node($this->phase($this->origin, PhaseKind::Constrain), $this->origin);

        return $this->phase($gate, $kind);
    }

    private function actions(Phase $phase): array
    {
        return app(InputPlanService::class)->forPhase($phase->cycle->participant, $phase)['schema']['$defs']['actions'];
    }

    private function sharedObservation(?Proposition $gate = null): Receipt
    {
        $gate ??= $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $persist = $this->phase($gate, PhaseKind::Persist);
        $instruments = app(EvidenceExecutionService::class);

        return $instruments->complete($instruments->start($persist, $gate, 'citation', $this->origin->id), $gate);
    }

    #[DataProvider('expressivePhases')]
    public function test_empty_position_then_shared_persist_evidence_then_attestation(PhaseKind $kind): void
    {
        $phase = $this->position($kind);
        if ($kind === PhaseKind::Disperse) {
            $this->assertArrayNotHasKey('proposition.variant', $this->actions($phase));
            $initialReceipt = $this->sharedObservation();
        }
        $this->assertArrayHasKey('proposition.variant', $this->actions($phase));
        $write = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.variant', 'statement' => 'Explore this possibility']);
        $actions = $this->actions($phase);
        if (isset($initialReceipt)) {
            // Consume the generation prerequisite before testing independent Attestation evidence.
            $consumerPhase = $this->phase($write, PhaseKind::Constrain);
            $consumer = $this->node($consumerPhase, $write);
            DB::transaction(fn () => app(HorizonReceiptService::class)->consume($consumerPhase, $write, $consumer, $initialReceipt->id));
            $actions = $this->actions($phase);
        }
        $this->assertSame($kind === PhaseKind::Disperse ? [$phase->gate_proposition_id] : [],
            $actions['instrument.execute']['properties']['proposition_id']['enum'] ?? []);
        $this->assertArrayNotHasKey('proposition.variant', $actions);
        $this->assertArrayNotHasKey('attestation.create', $actions);
        $this->assertArrayNotHasKey('proposition.reclassify', $actions);

        $instruments = app(EvidenceExecutionService::class);
        $observation = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $persist = $this->phase($observation, PhaseKind::Persist);
        $receipt = $instruments->complete($instruments->start($persist, $observation, 'citation', $this->origin->id), $observation);
        $actions = $this->actions($phase);
        $this->assertArrayHasKey('attestation.create', $actions);
        $this->assertArrayNotHasKey('instrument.execute', $actions);
        $this->assertArrayNotHasKey('proposition.variant', $actions);
        $other = $this->phase($phase->gate, $kind);
        $this->assertArrayNotHasKey('proposition.variant', $this->actions($other), 'Another author does not increase basin capacity');
        $this->assertArrayHasKey('attestation.create', $this->actions($other));

        $consumerPhase = $this->phase($write, PhaseKind::Constrain);
        $consumer = $this->node($consumerPhase, $write);
        DB::transaction(fn () => app(HorizonReceiptService::class)->consume($consumerPhase, $write, $consumer, $receipt->id));
        $this->assertArrayNotHasKey('proposition.variant', $this->actions($phase), 'Receipt consumption does not reopen Variant');
    }

    #[DataProvider('expressivePhases')]
    public function test_attestation_becomes_traverse_next_cycle_even_after_receipt_consumption(PhaseKind $kind): void
    {
        $phase = $this->position($kind);
        $write = $this->node($phase, $phase->gate);
        $vote = (new Attestation)->forceFill(['phase_id' => $phase->id, 'proposition_id' => $write->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        $this->assertArrayNotHasKey('proposition.traverse', $this->actions($phase));
        $phase->forceFill(['completed_at' => now()])->save();
        $phase->cycle->forceFill(['completed_at' => now()])->save();
        $cycle = (new Cycle)->forceFill(['participant_id' => $phase->cycle->participant_id,
            'context_horizon_id' => $phase->cycle->context_horizon_id, 'sequence' => 2]);
        $cycle->save();
        $next = (new Phase)->forceFill(['cycle_id' => $cycle->id, 'gate_proposition_id' => $phase->gate_proposition_id,
            'kind' => $kind, 'write_register' => $phase->write_register, 'started_at' => now()]);
        $next->save();
        $actions = $this->actions($next);
        $this->assertSame([$write->id], $actions['proposition.traverse']['properties']['proposition_id']['enum']);
        $this->assertArrayNotHasKey('proposition.variant', $actions);
        $this->assertSame($kind === PhaseKind::Disperse ? [$next->gate_proposition_id] : [],
            $actions['instrument.execute']['properties']['proposition_id']['enum'] ?? []);
    }

    #[DataProvider('expressivePhases')]
    public function test_another_variant_requires_spent_evidenced_routes_independently_of_potential(PhaseKind $kind): void
    {
        $phase = $this->position($kind);
        $first = $this->node($phase, $phase->gate);
        $generation = app(PhaseGenerationService::class);
        $this->assertFalse($generation->available($phase, 'proposition.variant'));
        $instruments = app(EvidenceExecutionService::class);
        $receipt = $this->sharedObservation($kind === PhaseKind::Distinguish ? $first : null);
        $this->assertFalse($generation->available($phase, 'proposition.variant'), 'Instrument alone is insufficient');
        $vote = (new Attestation)->forceFill(['phase_id' => $phase->id, 'proposition_id' => $first->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        foreach (Proposition::where('tier', 'expressive')->get() as $node) {
            $this->measure($node, 1);
        }
        $this->assertFalse(app(PotentialChargeService::class)->hasRemainingInRegion($phase));
        $this->assertFalse($generation->available($phase, 'proposition.variant'), 'An unspent route blocks generation even at zero Potential');
        $this->measure($first, 0);
        $this->assertFalse($generation->available($phase, 'proposition.variant'), 'A healthy evidenced route remains viable');
        $this->recordSpent($first, $phase);
        $this->assertTrue(app(PotentialChargeService::class)->hasRemainingInRegion($phase));
        $this->assertTrue($generation->available($phase, 'proposition.variant'));
        $this->assertTrue($generation->available($phase, 'proposition.variant', new ParticipationContext($phase)));
        $this->assertArrayHasKey('proposition.variant', $this->actions($phase));
        // Only the measured field changes: causal structure, evidence and epoch stay identical.
        $this->measure($first, 1);
        $this->assertFalse(app(PotentialChargeService::class)->hasRemainingInRegion($phase));
        $this->assertTrue($generation->available($phase, 'proposition.variant'));
        $this->assertTrue($generation->available($phase, 'proposition.variant', new ParticipationContext($phase)));
        $this->assertArrayHasKey('proposition.variant', $this->actions($phase));
        // Failure is a substrate fact, never an author-count or action-priority quota.
        $second = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.variant', 'statement' => 'The next independent possibility']);
        $this->assertFalse($generation->available($phase, 'proposition.variant'));
        $actions = $this->actions($phase);
        $this->assertArrayNotHasKey('instrument.execute', $actions);
        $this->assertContains($second->id, $actions['attestation.create']['properties']['proposition_id']['enum'] ?? []);
        $this->assertEmpty(app(HorizonReceiptService::class)->instrumentedIds($phase, collect([$second->id])));
        $this->assertFalse($generation->available($phase, 'proposition.variant'));
        $vote = (new Attestation)->forceFill(['phase_id' => $phase->id, 'proposition_id' => $second->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        $this->measure($second, 0);
        $this->assertFalse($generation->available($phase, 'proposition.variant'), 'New and reopened routes must be tested');
        $this->recordSpent($first, $phase);
        $this->recordSpent($second, $phase);
        $this->assertTrue($generation->available($phase, 'proposition.variant'), 'The rule repeats after all current routes fail');
        $this->assertNull($receipt->fresh()->consumption, 'Variant eligibility never consumes evidence');
    }

    #[DataProvider('expressivePhases')]
    public function test_attestation_without_evidence_and_positive_potential_does_not_unlock_variant(PhaseKind $kind): void
    {
        $phase = $this->position($kind);
        $write = $this->node($phase, $phase->gate);
        $vote = (new Attestation)->forceFill(['phase_id' => $phase->id, 'proposition_id' => $write->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        $this->measure($write, 0);
        $this->assertFalse(app(PhaseGenerationService::class)->available($phase, 'proposition.variant'));
        $mediate = $this->phase($write, PhaseKind::Mediate);
        $mediate->forceFill(['completed_at' => now()])->save();
        $mediate->cycle->forceFill(['rested_at' => now(), 'completed_at' => now()])->save();
        $this->assertFalse(app(PhaseGenerationService::class)->available($phase, 'proposition.variant'), 'Mediate Rest cannot bypass evidence');
    }

    #[DataProvider('expressivePhases')]
    public function test_zero_potential_does_not_bypass_evidence_or_current_epoch(PhaseKind $kind): void
    {
        // This is a controlled zero-Potential eligibility test. Keep its field
        // premise stable when evidence writes recalculate the projections too.
        $this->resolveExpressiveFieldFixture(1);
        $phase = $this->position($kind);
        $write = $this->node($phase, $phase->gate);
        foreach (Proposition::where('tier', 'expressive')->get() as $node) {
            $this->measure($node, 1);
        }
        $this->assertFalse(app(PotentialChargeService::class)->hasRemainingInRegion($phase));
        $this->recordSpent($write, $phase);
        $generation = app(PhaseGenerationService::class);
        $this->assertFalse($generation->available($phase, 'proposition.variant'));

        $vote = (new Attestation)->forceFill(['phase_id' => $phase->id, 'proposition_id' => $write->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        $this->assertFalse($generation->available($phase, 'proposition.variant'), 'A spent attested route still needs genuine evidence');
        $vote->delete();
        $this->sharedObservation($kind === PhaseKind::Distinguish ? $write : null);
        $this->assertFalse($generation->available($phase, 'proposition.variant'), 'A spent instrumented route still needs effective Attestation');
        $vote = (new Attestation)->forceFill(['phase_id' => $phase->id, 'proposition_id' => $write->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        $this->publishPotentialFixture($this->substrate);
        $this->assertFalse(app(PotentialChargeService::class)->hasRemainingInRegion($phase));
        $this->assertTrue($generation->available($phase, 'proposition.variant'));

        // A failure from a different epoch cannot satisfy current-epoch spentness.
        DB::table('continuation_failures')->where('proposition_id', $write->id)->update(['exploration_epoch' => 99]);
        $this->assertFalse($generation->available($phase, 'proposition.variant'));
        $this->assertArrayNotHasKey('proposition.variant', $this->actions($phase));
        $this->expectException(\DomainException::class);
        app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.variant', 'statement' => 'A stale plan must not bypass spentness']);
    }

    private function recordSpent($node, Phase $phase): void
    {
        DB::table('continuation_failures')->insert(['proposition_id' => $node->id,
            'context_horizon_id' => $phase->cycle->context_horizon_id, 'structural_position' => $phase->kind->value,
            'exploration_epoch' => app(ExplorationEpochService::class)->forPhase($phase),
            'phase_id' => $phase->id, 'failed_at' => now()]);
    }
}
