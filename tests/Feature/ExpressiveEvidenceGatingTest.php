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

    public function test_missing_binary_counterpart_allows_one_participant_to_build_plurality(): void
    {
        $phase = $this->position(PhaseKind::Disperse);
        $first = $this->node($phase, $phase->gate);
        $generation = app(PhaseGenerationService::class);
        $this->assertFalse($generation->available($phase, 'proposition.variant'));
        $mediate = $this->phase($first, PhaseKind::Mediate);
        $mediate->forceFill(['completed_at' => now()])->save();
        $mediate->cycle->forceFill(['rested_at' => now(), 'completed_at' => now()])->save();
        $this->assertTrue($generation->available($phase, 'proposition.variant'));
        $second = $this->node($phase, $phase->gate);
        $this->assertNotSame($first->id, $second->id);
        $this->assertFalse($generation->available($phase, 'proposition.variant'), 'Binary demand does not authorize endless siblings');
    }
}
