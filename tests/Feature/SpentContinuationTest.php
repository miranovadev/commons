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
use App\Services\PropositionOptionService;
use App\Services\ReceiptContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\SpentContinuationFixture;
use Tests\TestCase;

class SpentContinuationTest extends TestCase
{
    use RefreshDatabase, SpentContinuationFixture;

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
        $horizon = $return->cycle->context_horizon_id;
        $distinguishEpoch = $epochs->current($horizon, PhaseKind::Distinguish);
        $disperseEpoch = $epochs->current($horizon, PhaseKind::Disperse);
        $fresh = app(ContributionDispatcher::class)->dispatch($return->cycle->participant, $return,
            ['action' => 'proposition.variant', 'statement' => 'A new distinction changes this field']);
        $this->assertSame($distinguishEpoch + 1, $epochs->current($horizon, PhaseKind::Distinguish));
        $this->assertSame($disperseEpoch, $epochs->current($horizon, PhaseKind::Disperse));
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
        $this->assertSame($disperseEpoch + 1, $epochs->current($horizon, PhaseKind::Disperse));
        $this->assertSame($distinguishEpoch + 1, $epochs->current($horizon, PhaseKind::Distinguish));
        $this->assertFalse($epochs->spentNow($activity, $disperse));
        $this->assertSame($history, DB::table('continuation_failures')->orderBy('proposition_id')->get()->toJson());
    }

    public function test_all_spent_without_potential_uses_tragedy_without_advancing_an_epoch(): void
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
        $this->assertSame(['participant.rest'], array_keys($plan['schema']['$defs']['actions']));
        $this->assertSame('no_remaining_potential', $plan['rest_reason']);
        $this->rest($return);
        $this->assertSame(1, Tragedy::count());
        $this->assertNull($return->cycle->contextHorizon->fresh()->exhausted_at);
        $this->assertSame(0, app(ExplorationEpochService::class)->current($return->cycle->context_horizon_id, PhaseKind::Distinguish));
    }
}
