<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Phase;
use App\Services\ContributionDispatcher;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\PhaseGenerationService;
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

    #[DataProvider('expressivePhases')]
    public function test_empty_position_then_instrument_then_attestation(PhaseKind $kind): void
    {
        $phase = $this->position($kind);
        $this->assertArrayHasKey('proposition.variant', $this->actions($phase));
        $write = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.variant', 'statement' => 'Explore this possibility']);
        $actions = $this->actions($phase);
        $this->assertArrayHasKey('instrument.execute', $actions);
        $this->assertArrayNotHasKey('proposition.variant', $actions);
        $this->assertArrayNotHasKey('attestation.create', $actions);
        $this->assertArrayNotHasKey('proposition.reclassify', $actions);

        $instruments = app(InstrumentExecutionService::class);
        $receipt = $instruments->complete($instruments->start($phase, $write, 'citation', $this->origin->id), $write);
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
        $this->assertArrayNotHasKey('instrument.execute', $actions);
    }

    #[DataProvider('expressivePhases')]
    public function test_another_variant_requires_instrument_attestation_and_remaining_potential(PhaseKind $kind): void
    {
        $phase = $this->position($kind);
        $first = $this->node($phase, $phase->gate);
        $generation = app(PhaseGenerationService::class);
        $this->assertFalse($generation->available($phase, 'proposition.variant'));
        $instruments = app(InstrumentExecutionService::class);
        $receipt = $instruments->complete($instruments->start($phase, $first, 'citation', $this->origin->id), $first);
        $this->assertFalse($generation->available($phase, 'proposition.variant'), 'Instrument alone is insufficient');
        $vote = (new Attestation)->forceFill(['phase_id' => $phase->id, 'proposition_id' => $first->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        $this->measure($first, 1);
        $this->assertFalse($generation->available($phase, 'proposition.variant'), 'Evidence cannot invent Potential');
        $this->measure($first, 0);
        $this->assertTrue($generation->available($phase, 'proposition.variant'));
        $this->assertArrayHasKey('proposition.variant', $this->actions($phase));
        // No Rest or other Participant is needed to authorize the next Variant.
        $second = app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'proposition.variant', 'statement' => 'The next independent possibility']);
        $this->assertFalse($generation->available($phase, 'proposition.variant'));
        $actions = $this->actions($phase);
        $this->assertContains($second->id, $actions['instrument.execute']['properties']['proposition_id']['enum']);
        $this->assertNotContains($second->id, $actions['attestation.create']['properties']['proposition_id']['enum'] ?? []);
        $instruments->complete($instruments->start($phase, $second, 'citation', $this->origin->id), $second);
        $this->assertFalse($generation->available($phase, 'proposition.variant'));
        $vote = (new Attestation)->forceFill(['phase_id' => $phase->id, 'proposition_id' => $second->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        $this->measure($second, 0);
        $this->assertTrue($generation->available($phase, 'proposition.variant'), 'The rule repeats beyond two Variants');
        $this->assertNull($receipt->fresh()->consumption, 'Variant eligibility never consumes evidence');
    }

    #[DataProvider('expressivePhases')]
    public function test_attestation_without_own_instrument_and_positive_potential_does_not_unlock_variant(PhaseKind $kind): void
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
}
