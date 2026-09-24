<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Proposition;
use App\Models\Tragedy;
use App\Services\ContributionDispatcher;
use App\Services\ExplorationEpochService;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\PhaseGenerationService;
use App\Services\PotentialChargeService;
use App\Services\PropositionOptionService;
use App\Services\ReceiptContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\SpentContinuationFixture;
use Tests\TestCase;

class SpentContinuationTest extends TestCase
{
    use RefreshDatabase, SpentContinuationFixture;

    public function test_real_mediate_arrival_can_rest_without_mandatory_receipt_reads(): void
    {
        $this->initializeExhaustionFixture();
        [, , $terminal] = $this->testedLineage();
        $plan = app(InputPlanService::class)->forPhase($terminal->cycle->participant, $terminal, true);
        $this->assertArrayNotHasKey('receipt.read', $plan['schema']['$defs']['actions']);
        $this->assertArrayHasKey('participant.rest', $plan['schema']['$defs']['actions']);
    }

    public function test_mediate_failure_blocks_the_previously_attested_distinguish_route(): void
    {
        $this->initializeExhaustionFixture();
        [$signal, $activity, $terminal, $return] = $this->testedLineage();
        $plan = fn () => app(InputPlanService::class)->forPhase($return->cycle->participant, $return);
        $this->assertContains($signal->id, array_column($plan()['traversal_options'], 'id'));

        $this->rest($terminal);
        $this->assertSame(0, Tragedy::count(), 'Ordinary exploration failure is not Tragedy');
        $this->assertNotContains($signal->id, array_column($plan()['traversal_options'], 'id'));
        $this->assertContains($signal->id, array_column($plan()['write_options'], 'id'), 'Spent evidence stays visible');
        foreach (['attestation_options', 'instrument_options'] as $options) {
            $this->assertNotContains($signal->id, array_column($plan()[$options], 'id'));
        }
        foreach (['proposition.traverse', 'attestation.create'] as $action) {
            try {
                app(ContributionDispatcher::class)->dispatch($return->cycle->participant, $return,
                    ['action' => $action, 'proposition_id' => $signal->id, 'direction' => 'support']);
                $this->fail('A stale action target must be rejected under the write lock');
            } catch (\DomainException) {
                $this->assertTrue(true);
            }
        }

        $this->measure($activity, 0);
        $this->assertArrayHasKey('proposition.variant', $plan()['schema']['$defs']['actions']);
        $history = DB::table('continuation_failures')->orderBy('proposition_id')->get()->toJson();
        $epochs = app(ExplorationEpochService::class);
        $distinguishEpoch = $epochs->forProposition($signal);
        $disperseEpoch = $epochs->forProposition($activity);
        $fresh = app(ContributionDispatcher::class)->dispatch($return->cycle->participant, $return,
            ['action' => 'proposition.variant', 'statement' => 'A new distinction changes this field']);
        $this->assertSame($distinguishEpoch + 1, $epochs->forProposition($signal));
        $this->assertSame($disperseEpoch, $epochs->forProposition($activity));
        $this->assertFalse($epochs->spentNow($signal, $return));
        $this->assertTrue($epochs->spentNow($activity, $return));
        $this->assertContains($signal->id, app(PropositionOptionService::class)->traversalOptionsFor($return)->pluck('id'));
        $this->assertFalse(app(PhaseGenerationService::class)->available($return, 'proposition.variant'), 'Reopened viable routes prevent Variant confetti');

        $disperse = $this->phase($activity->phase->gate, PhaseKind::Disperse);
        $this->measure($fresh, 0);
        $this->assertTrue(app(PhaseGenerationService::class)->available($disperse, 'proposition.variant'));
        $this->assertTrue(app(PropositionOptionService::class)->attestationOptionsFor($disperse)->isEmpty());
        app(ContributionDispatcher::class)->dispatch($disperse->cycle->participant, $disperse,
            ['action' => 'proposition.variant', 'statement' => 'A new dispersed possibility']);
        $this->assertSame($disperseEpoch + 1, $epochs->forProposition($activity));
        $this->assertSame($distinguishEpoch + 1, $epochs->forProposition($signal));
        $this->assertFalse($epochs->spentNow($activity, $disperse));
        $this->assertSame($history, DB::table('continuation_failures')->orderBy('proposition_id')->get()->toJson());

        // Testing the same route in the changed field appends a second fact.
        $again = $this->phase($activity, PhaseKind::Mediate);
        foreach (app(HorizonReceiptService::class)->available($again) as $receipt) {
            app(ReceiptContextService::class)->read($again->cycle->participant, $again, $receipt->id);
        }
        $this->measure($activity, 0);
        $this->rest($again);
        $this->assertTrue($epochs->spentNow($activity, $disperse));
        $this->assertSame([$disperseEpoch, $disperseEpoch + 1], DB::table('continuation_failures')
            ->where('proposition_id', $activity->id)->orderBy('exploration_epoch')->pluck('exploration_epoch')->map(fn ($epoch) => (int) $epoch)->all());
        $this->assertSame($history, DB::table('continuation_failures')->where('exploration_epoch', 0)->orderBy('proposition_id')->get()->toJson());
        $this->assertFalse($epochs->spentNow($signal, $return), 'The fresh Disperse sibling stops propagation to this reopened ancestor');
        $this->assertSame(0, Tragedy::count());
    }

    public function test_all_spent_without_potential_can_generate_and_advance_its_epoch(): void
    {
        $this->initializeExhaustionFixture();
        [$signal, , $terminal, $return] = $this->testedLineage();
        $this->rest($terminal);
        foreach (app(HorizonReceiptService::class)->available($return) as $receipt) {
            app(ReceiptContextService::class)->read($return->cycle->participant, $return, $receipt->id);
        }
        foreach (Proposition::where('tier', 'expressive')->get() as $node) {
            $this->measure($node, 1);
        }
        $plan = app(InputPlanService::class)->forPhase($return->cycle->participant, $return);
        $this->assertFalse(app(PotentialChargeService::class)->hasRemainingInRegion($return));
        $this->assertArrayHasKey('proposition.variant', $plan['schema']['$defs']['actions']);
        $this->assertArrayNotHasKey('participant.rest', $plan['schema']['$defs']['actions']);
        $epochs = app(ExplorationEpochService::class);
        $before = $epochs->forPhase($return);
        app(ContributionDispatcher::class)->dispatch($return->cycle->participant, $return,
            ['action' => 'proposition.variant', 'statement' => 'Expression needs no prior tension reduction']);
        $this->assertSame($before + 1, $epochs->forPhase($return));
        $this->assertFalse($epochs->spentNow($signal, $return));
        $this->assertSame(0, Tragedy::count());
    }
}
