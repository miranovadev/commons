<?php

namespace Tests\Feature;

use App\Enums\SupersessionStrength;
use App\Models\Attestation;
use App\Models\InstrumentExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Receipt;
use App\Models\Supersession;
use App\Services\ContributionDispatcher;
use App\Services\HorizonReceiptService;
use App\Services\Observables\ProspectiveClosureService;
use App\Services\ParticipationService;
use App\Services\PhaseProgressionService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\GovernedExpressionFixture;
use Tests\TestCase;

/** Lawful replacements for the Variant/Reclassify fresh-history acceptance criteria. */
class PropositionHistoryLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public static function operations(): array
    {
        return ['Persist Variant' => ['variant'], 'Constrain Reclassify' => ['reclassify']];
    }

    #[DataProvider('operations')]
    public function test_authorised_operation_resets_judgments_and_touches_without_copying_source_history(string $operation): void
    {
        $fixture = new GovernedExpressionFixture;
        $dispatcher = app(ContributionDispatcher::class);
        $progression = app(PhaseProgressionService::class);
        if ($operation === 'reclassify') {
            $fixture->advanceToConstrain();
            $source = $fixture->expression;
            $phase = $fixture->phase;
        } else {
            $source = $fixture->expression;
            $phase = $progression->advance($fixture->phase, $source);
            $dispatcher->dispatch($fixture->actor, $phase,
                ['action' => 'instrument.execute', 'proposition_id' => $source->id,
                    'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/fresh-history']]);
            // A second Participant lawfully attests the grounded distinction,
            // then develops it in Persist; no Instrument in Distinguish is needed.
            $actor = (new Participant)->forceFill(['substrate_id' => $fixture->actor->substrate_id, 'callsign' => 'developer']);
            $actor->save();
            app(PotentialProjectionService::class)->refresh($actor->substrate_id);
            $state = app(ParticipationService::class)->enter($actor);
            $phase = Phase::findOrFail($state['plan']['phase']['id']);
            $dispatcher->dispatch($actor, $phase,
                ['action' => 'attestation.create', 'proposition_id' => $source->id, 'direction' => 'support']);
            $phase = $progression->advance($phase, $source);
            $fixture->actor = $actor;
        }
        $state = app(PropositionStateService::class);
        $sourceTouches = $state->touchCount($source);
        $votes = $state->attestations(collect([$source]))->pluck('id')->all();
        $receipts = $state->receipts($source)->pluck('id')->all();
        $this->assertCount(1, $votes);
        $this->assertCount($operation === 'variant' ? 1 : 0, $receipts);
        $availableReceipt = app(HorizonReceiptService::class)->ordinaryAvailable($phase)->sole();
        $this->assertGreaterThan(0, $sourceTouches);
        $before = [Attestation::count(), Receipt::count(), InstrumentExecution::count()];
        $closure = app(ProspectiveClosureService::class)->stored($source);
        $input = $operation === 'variant'
            ? ['action' => 'proposition.variant', 'statement' => 'A fresh development with its own judgments.']
            : ['action' => 'proposition.reclassify', 'proposition_id' => $source->id, 'receipt_id' => $availableReceipt->id];
        $target = $dispatcher->dispatch($fixture->actor, $phase, $input);

        $this->assertSame('expressive', $target->tier);
        $this->assertSame($phase->write_register, $target->register);
        $this->assertSame($operation === 'variant' ? $input['statement'] : $source->statement, $target->statement);
        $this->assertSame([$source->id], $target->parents()->pluck('propositions.id')->all());
        $edge = Supersession::where('target_proposition_id', $target->id)->sole();
        $this->assertSame($operation, $edge->type);
        $this->assertSame($operation === 'variant' ? SupersessionStrength::Soft : SupersessionStrength::Hard,
            SupersessionStrength::for($edge->type));
        $this->assertSame([$target->id], $state->sourceIds($target)->all());
        $this->assertSame(0, $state->touchCount($target));
        $this->assertCount(0, $state->attestations(collect([$target])));
        $this->assertCount($operation === 'variant' ? 0 : 1, $state->receipts($target));
        $this->assertSame(0, $target->instrumentExecutions()->count());
        $this->assertSame($before, [Attestation::count(), Receipt::count(), InstrumentExecution::count()]);
        $this->assertSame($closure, app(ProspectiveClosureService::class)->stored($source));
        $this->assertDatabaseHas('phase_profile_observations', ['proposition_id' => $target->id]);
        $next = $progression->advance($phase, $target);
        $this->assertSame($target->id, $next->gate_proposition_id);
        $this->assertSame(1, $state->touchCount($target));
        $this->assertSame($sourceTouches, $state->touchCount($source));
        $this->assertSame($votes, $state->attestations(collect([$source]))->pluck('id')->all());
        $this->assertSame($receipts, $state->receipts($source)->pluck('id')->all());
    }
}
