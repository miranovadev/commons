<?php

namespace Tests\Feature;

use App\Domain\Observables\CorrespondenceValues;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\ActiveParticipantLineage;
use App\Services\BiasGateService;
use App\Services\CommitRegionService;
use App\Services\ContributionDispatcher;
use App\Services\ContributionService;
use App\Services\ExplorationPositionService;
use App\Services\GraphProjectionService;
use App\Services\HomologousBiasService;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\MediatedIncomingBiasService;
use App\Services\Participation\ParticipationContext;
use App\Services\ParticipationService;
use App\Services\PhaseProgressionService;
use App\Services\PropositionMeasurementService;
use App\Services\PropositionOptionService;
use App\Services\ReceiptContextService;
use App\Services\RegionalExhaustionMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\CreatesHomologousPositions;
use Tests\TestCase;

class MediatedIncomingBiasTest extends TestCase
{
    use CreatesHomologousPositions, RefreshDatabase;

    private Proposition $a;

    private Proposition $b;

    private Proposition $gateA;

    private Proposition $gateB;

    private Proposition $readB;

    private Proposition $bResult;

    private Proposition $result;

    private Phase $mediate;

    private Phase $bias;

    private Participant $mediator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createHomologyActor();
        $origin = $this->commit(null, 'emergence');
        $this->a = $this->commit($origin, 'settlement');
        $this->b = $this->commit($origin, 'settlement');
        [$readA, $this->gateA] = $this->pair($this->a);
        [$this->readB, $this->gateB] = $this->pair($this->b);
        $localB = $this->expression($this->b, $this->readB, PhaseKind::Disperse);
        Phase::query()->update(['completed_at' => now()]);
        Cycle::query()->update(['completed_at' => now()]);
        $previous = $this->phase($this->a, $readA, PhaseKind::Disperse);
        $previous->forceFill(['completed_at' => now()])->save();
        $this->mediate = (new Phase)->forceFill(['cycle_id' => $previous->cycle_id,
            'kind' => PhaseKind::Mediate, 'write_register' => 'stance',
            'gate_proposition_id' => $this->gateA->id, 'started_at' => now()]);
        $this->mediate->save();
        $this->actor->forceFill(['current_proposition_id' => $this->gateA->id])->save();
        $this->publishPotentialFixture($this->actor);
        $this->resolveExpressiveFieldFixture(.8);
        $response = app(ParticipationService::class)->contribute($this->actor, $this->mediate->id,
            ['action' => 'proposition.reconcile', 'other_proposition_id' => $this->gateB->id]);
        $this->result = Proposition::findOrFail($response['current_proposition_id']);
        $this->mediator = $this->actor;
        $this->actor = (new Participant)->forceFill(['substrate_id' => $this->mediator->substrate_id,
            'callsign' => 'incoming', 'current_proposition_id' => $this->gateB->id]);
        $this->actor->save();
        $this->bias = $this->progressToBias($this->b, $this->readB, $this->gateB, $localB);
        $this->bResult = $this->bias->gate;
        // Keep A outside the independent homologous population's dominant sign.
        app(PropositionMeasurementService::class)->project($this->result, .8, .1, .8);
        app(PropositionMeasurementService::class)->project($this->bResult, .8, -.9, .8);
    }

    public function test_reconcile_retains_ownership_and_only_the_exact_other_operand_opens_the_doorway(): void
    {
        $result = $this->result;
        $this->assertSame($this->mediate->cycle_id, $result->phase->cycle_id);
        $this->assertSame($this->a->id, app(CommitRegionService::class)->seedFor($result)->id);
        $this->assertSame($this->mediate->cycle->context_horizon_id, $result->contextHorizonMembership->context_horizon_id);
        $this->assertSame($result->id, $this->mediator->fresh()->current_proposition_id);
        $this->assertContains($this->a->id, app(ActiveParticipantLineage::class)->endingAt($result)->pluck('id'));
        $this->assertNotContains($this->b->id, app(ActiveParticipantLineage::class)->endingAt($result)->pluck('id'));
        $this->assertEqualsCanonicalizing([$this->gateA->id, $this->gateB->id], $result->parents()->pluck('propositions.id')->all());
        $this->assertSame(2, Supersession::where('target_proposition_id', $result->id)->where('type', 'reconcile')->count());
        $service = app(MediatedIncomingBiasService::class);
        foreach ([null, new ParticipationContext($this->bias)] as $context) {
            $this->assertSame([$result->id], $service->forPhase($this->bias, $context)->pluck('id')->all());
            $this->assertFalse(app(HomologousBiasService::class)->forPhase($this->bias, $context)->contains('id', $result->id));
            $this->assertSame($this->bResult->id, $this->bias->gate_proposition_id);
            $this->assertSame($this->gateB->id, $this->bResult->phase->gate_proposition_id);
            $this->assertFalse(app(PropositionOptionService::class)->traversalOptionsFor($this->bias, $context)->contains('id', $result->id));
        }
        $aBias = Phase::where('cycle_id', $this->mediate->cycle_id)->where('kind', PhaseKind::Bias)->sole();
        $this->assertTrue($service->populationFor($aBias)->isEmpty(), 'No reverse relation was recorded');
        // A different local chain reaches Bias normally but has a different Mediate Gate.
        $otherGate = $this->expression($this->b, $this->readB, PhaseKind::Disperse);
        $otherOperand = $this->expression($this->b, $this->readB, PhaseKind::Disperse);
        $this->bias->forceFill(['completed_at' => now()])->save();
        $this->bias->cycle->forceFill(['completed_at' => now()])->save();
        $other = $this->progressToBias($this->b, $this->readB, $otherGate, $otherOperand);
        $this->assertTrue($service->populationFor($other)->isEmpty());
        foreach (Proposition::whereHas('phase', fn ($q) => $q->where('kind', PhaseKind::Mediate))->where('id', '!=', $result->id)->get() as $node) {
            app(PropositionMeasurementService::class)->project($node, .8, -.9, .8);
        }
        $this->assertFalse(app(BiasGateService::class)->operationalFor($other)->contains('id', $result->id));
        $this->assertFalse(app(PropositionOptionService::class)->traversalOptionsFor($this->mediate)->contains('id', $this->gateB->id));
        $this->assertDatabaseCount('tragedies', 0);
    }

    public function test_ordinary_attestation_and_own_region_receipt_authorize_reclassification_without_transport(): void
    {
        $foreign = $this->loadedReceiptFixture($this->result);
        $this->assertTrue($this->biasOptions()->isEmpty(), 'No effective Attestation or own-Region Receipt');
        $this->attestResult();
        $this->assertTrue($this->biasOptions()->isEmpty(), 'Foreign evidence is not a local Receipt allowance');
        $own = $this->loadedReceiptFixture($this->gateB);
        $this->publishPotentialFixture($this->actor);
        $context = new ParticipationContext($this->bias);
        $this->assertSame([$this->result->id], $this->biasOptions($context)->pluck('id')->all());
        $this->assertFalse(app(HorizonReceiptService::class)->ordinaryAvailable($this->bias, $context)->contains('id', $foreign->id));
        $this->assertFalse(app(ReceiptContextService::class)->forPhase($this->bias, $context)->contains('id', $foreign->id));
        $plan = app(InputPlanService::class)->forPhase($this->actor, $this->bias);
        $row = collect($plan['gate_options'])->firstWhere('id', $this->result->id);
        $this->assertSame([], $row['receipts']);
        $this->assertSame([], $row['instruments']);
        $before = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $this->input($foreign->id));
            $this->fail('A foreign Receipt must not cross with the option');
        } catch (\DomainException) {
            $this->assertSame($before, Proposition::count());
            $this->assertDatabaseCount('receipt_consumptions', 0);
        }
        $response = app(ParticipationService::class)->contribute($this->actor, $this->bias->id, $this->input($own->id));
        $target = Proposition::findOrFail($response['current_proposition_id']);
        $this->assertSame($this->b->id, app(CommitRegionService::class)->seedFor($target)->id);
        $this->assertSame($this->bias->cycle->context_horizon_id, $target->phase->cycle->context_horizon_id);
        $this->assertSame([$this->result->id], $target->parents()->pluck('propositions.id')->all());
        $this->assertSame($target->id, $own->fresh()->consumption->proposition_id);
        $this->assertNull($foreign->fresh()->consumption);
        $this->assertTrue($this->biasOptions()->isEmpty(), 'Shared source/register uniqueness remains authoritative');
        $this->assertDatabaseCount('tragedies', 0);
        $this->publishPotentialFixture($this->actor);
    }

    public function test_missing_attestation_still_blocks_with_an_own_region_receipt(): void
    {
        $this->loadedReceiptFixture($this->gateB);
        $this->assertTrue($this->biasOptions()->isEmpty());
        $this->attestResult();
        $this->assertSame([$this->result->id], $this->biasOptions()->pluck('id')->all());
    }

    public function test_spent_and_exhausted_sources_do_not_gain_a_doorway_allowance(): void
    {
        $this->attestResult();
        $this->loadedReceiptFixture($this->gateB);
        $position = app(ExplorationPositionService::class)->forProposition($this->result);
        DB::table('continuation_failures')->insert(['proposition_id' => $this->result->id,
            'context_horizon_id' => $position['context_horizon_id'], 'structural_position' => $position['structural_position'],
            'exploration_epoch' => 0, 'phase_id' => $this->result->phase_id, 'failed_at' => now()]);
        $this->assertTrue($this->biasOptions()->isEmpty());
        DB::table('horizon_exploration_states')->insert([...$position, 'epoch' => 1]);
        $this->assertCount(1, $this->biasOptions());
        $tragedy = $this->boundaryTragedyFixture($this->result);
        DB::table('commit_region_exhaustions')->insert(['governing_committal_id' => $this->a->id,
            'context_horizon_id' => $tragedy->context_horizon_id, 'tragedy_id' => $tragedy->id,
            'receipt_id' => $tragedy->receipt_id, 'phase_id' => $this->result->phase_id,
            'terminal_proposition_id' => $this->result->id, 'observed_field_version' => 1, 'exhausted_at' => now()]);
        app(RegionalExhaustionMembership::class)->publish($this->a->id, [$this->result->id]);
        $this->assertTrue($this->biasOptions()->isEmpty());
    }

    public function test_committed_source_cannot_be_used_even_with_evidence(): void
    {
        $this->attestResult();
        $this->loadedReceiptFixture($this->gateB);
        $this->assertCount(1, $this->biasOptions());
        $commit = $this->commit($this->a, 'settlement');
        $this->edge($this->result, $commit, 'commit');
        $this->assertTrue($this->biasOptions()->isEmpty());
    }

    public function test_same_region_expressive_paths_use_the_same_ordinary_doorway_rules(): void
    {
        $this->bias->forceFill(['completed_at' => now()])->save();
        $this->bias->cycle->forceFill(['completed_at' => now()])->save();
        $aGate = $this->expression($this->b, $this->readB, PhaseKind::Disperse);
        $bGate = $this->expression($this->b, $this->readB, PhaseKind::Disperse);
        $other = $this->expression($this->b, $this->readB, PhaseKind::Disperse);
        foreach ([$aGate, $bGate, $other] as $node) {
            $node->phase->forceFill(['completed_at' => now()])->save();
            $node->phase->cycle->forceFill(['completed_at' => now()])->save();
        }
        $fromA = $this->progressToBias($this->b, $this->readB, $aGate, $bGate);
        $localResult = $fromA->gate;
        $fromA->forceFill(['completed_at' => now()])->save();
        $fromA->cycle->forceFill(['completed_at' => now()])->save();
        $this->bias = $this->progressToBias($this->b, $this->readB, $bGate, $other);
        $this->result = $localResult;
        $this->attestResult();
        $this->loadedReceiptFixture($bGate);
        $this->assertSame([$localResult->id], $this->biasOptions()->pluck('id')->all());
        $context = new ParticipationContext($this->bias);
        $this->assertFalse(app(MediatedIncomingBiasService::class)->foreignGateIdsFor($this->bias, $context)->contains($localResult->id));
        $this->assertDatabaseCount('tragedies', 0);
    }

    public function test_incomplete_or_reversed_provenance_does_not_create_an_incoming_option(): void
    {
        $edge = Supersession::where('target_proposition_id', $this->result->id)->where('source_proposition_id', $this->gateA->id)->sole();
        DB::table('supersessions')->where('id', $edge->id)->update(['type' => 'variant']);
        $this->assertTrue(app(MediatedIncomingBiasService::class)->populationFor($this->bias)->isEmpty());
    }

    public function test_mediated_authority_remains_ordinary_even_when_the_source_also_qualifies_by_homology(): void
    {
        $this->attestResult();
        $own = $this->loadedReceiptFixture($this->gateB);
        app(PropositionMeasurementService::class)->project($this->result, .8, -.9, .8);
        $this->assertTrue(app(HomologousBiasService::class)->forPhase($this->bias)->contains('id', $this->result->id));
        $this->assertCount(1, $this->biasOptions());
        $tragedy = $this->boundaryTragedyFixture($this->bResult);
        DB::transaction(fn () => app(ReceiptContextService::class)->desired($this->actor, $this->bias, ['load' => [$tragedy->receipt_id]]));
        app(PropositionMeasurementService::class)->project($this->bResult, .8, -.9, .8);
        $this->publishPotentialFixture($this->actor);
        $schema = app(InputPlanService::class)->forPhase($this->actor, $this->bias)['schema'];
        try {
            app(ContributionService::class)->validateSchema($schema, $this->input($tragedy->receipt_id));
            $this->fail('A doorway cannot use a Tragedy Receipt.');
        } catch (\InvalidArgumentException) {
            $this->assertDatabaseCount('receipt_consumptions', 0);
        }
        try {
            app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $this->input($tragedy->receipt_id));
            $this->fail('Server authority must reject a Tragedy Receipt for a recorded doorway.');
        } catch (\DomainException) {
            $this->assertDatabaseCount('receipt_consumptions', 0);
        }
        $classified = app(ContributionDispatcher::class)->dispatch($this->actor, $this->bias, $this->input($own->id));
        app(GraphProjectionService::class)->load($this->actor->substrate, collect([$classified]), historical: true);
        $this->assertFalse($classified->renewal);
        $this->assertDatabaseMissing('receipt_consumptions', ['receipt_id' => $tragedy->receipt_id]);
        $this->assertNotNull($own->fresh()->consumption);
    }

    private function progressToBias(Proposition $owner, Proposition $read, Proposition $gate, Proposition $other): Phase
    {
        $disperse = $this->phase($owner, $read, PhaseKind::Disperse);
        $mediate = app(PhaseProgressionService::class)->advance($disperse, $gate);
        $this->publishPotentialFixture($this->actor);
        app(ParticipationService::class)->contribute($this->actor, $mediate->id,
            ['action' => 'proposition.reconcile', 'other_proposition_id' => $other->id]);

        return Phase::where('cycle_id', $mediate->cycle_id)->where('kind', PhaseKind::Bias)->sole();
    }

    private function biasOptions(?ParticipationContext $context = null): Collection
    {
        return app(PropositionOptionService::class)->gateOptionsFor($this->bias, $context)->where('id', $this->result->id)->values();
    }

    private function input(string $receipt): array
    {
        return ['action' => 'proposition.reclassify', 'proposition_id' => $this->result->id, 'receipt_id' => $receipt];
    }

    private function attestResult(): void
    {
        $vote = (new Attestation)->forceFill(['phase_id' => $this->result->phase_id,
            'proposition_id' => $this->result->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);
    }

    private function pair(Proposition $governor): array
    {
        $read = $this->expression($governor, $governor, PhaseKind::Constrain);
        $gate = $this->expression($governor, $read, PhaseKind::Disperse);
        $this->measure($read, -0.5);

        return [$read, $gate];
    }

    private function expression(Proposition $governor, Proposition $parent, PhaseKind $kind, ?string $register = null, string $edgeType = 'variant'): Proposition
    {
        $phase = $this->phase($governor, $parent, $kind);
        if ($register !== null) {
            $phase->forceFill(['write_register' => $register])->save();
        }
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'tier' => 'expressive',
            'register' => $register ?? $phase->write_register, 'statement' => 'Comparison fixture'])->save();
        app(LedgerService::class)->record($node);
        $this->edge($parent, $node, $edgeType);
        if ($kind === PhaseKind::Disperse) {
            // Author eligible Mediate inputs; this suite tests the later Bias doorway.
            app(PropositionMeasurementService::class)->project($node, .8, 0, 0,
                correspondenceComponents: new CorrespondenceValues(.8, .8));
        }

        return $node;
    }

    private function edge(Proposition $source, Proposition $target, string $type): void
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $target->phase_id, 'source_proposition_id' => $source->id,
            'target_proposition_id' => $target->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
    }

    private function measure(Proposition $node, float $value): void
    {
        app(PropositionMeasurementService::class)->project($node, $value, 0, 0);
    }
}
