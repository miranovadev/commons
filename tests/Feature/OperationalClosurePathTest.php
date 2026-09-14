<?php

namespace Tests\Feature;

use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Enums\SupersessionStrength;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\ContextHorizon;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\BiasGateService;
use App\Services\ContributionDispatcher;
use App\Services\Observables\ClosureCalculator;
use App\Services\Observables\ContextLineageComparisonService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipationService;
use App\Services\PropositionStateService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalClosurePathTest extends TestCase
{
    use RefreshDatabase;

    private Substrate $substrate;

    /** No fabricated graph, Phase, evidence, measurement, snapshot or Closure mock. */
    public function test_real_late_phase_path_rebuilds_concord_history_and_commits_only_when_closure_allows(): void
    {
        $this->substrate = new Substrate;
        $this->substrate->forceFill([
            'slug' => 'closure-path', 'title' => 'Operational Closure',
            'visibility' => 'public', 'configuration' => [],
        ])->save();
        $author = $this->participant('author');
        $state = app(ParticipationService::class)->enter($author, [
            'statement' => 'Origin for a shared investigation.',
            'attestation' => ['direction' => 'support'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin'],
        ]);
        $origin = $this->gate($state);
        $this->assertSame('settlement', $state['domain']);
        $this->assertSame(2, $state['horizon_sequence']);
        $this->assertTrue($origin->commitmentSnapshot->isClosureBoundary());
        $horizon = ContextHorizon::whereNull('closed_at')->sole();

        // The author creates the first branch through all four early phases.
        $signal = $this->gate($this->move($author, ['action' => 'proposition.variant', 'statement' => 'Initial Signal']));
        $earlyStance = $this->gate($this->move($author, ['action' => 'proposition.reclassify']));
        app(OperationalInstrumentService::class)->execute($earlyStance->phase, $earlyStance, ['kind' => 'citation', 'reference' => $signal->id]);
        $read = $this->gate($this->move($author, ['action' => 'proposition.reclassify']));
        $left = $this->gate($this->move($author, ['action' => 'proposition.variant', 'statement' => 'First dispersed Signal']));
        $this->assertSame(PhaseKind::Disperse, $left->phase->kind);

        // A second participant reads the existing early structure, then Disperse branches.
        $builder = $this->participant('builder');
        $this->reachDisperse($builder, $signal, $earlyStance, $read);
        $state = $this->move($builder, ['action' => 'proposition.variant', 'statement' => 'Second dispersed Signal']);
        $right = $this->gate($state);
        $this->assertSame('mediate', $state['plan']['phase']['kind']);
        $this->assertSame($read->id, $state['plan']['read_options'][0]['id']);
        $this->assertNotEquals(0.0, (float) $state['plan']['read_options'][0]['observables']['correspondence']);
        $this->assertContains($left->id, array_column($state['plan']['gate_options'], 'id'));
        $this->assertSame([$read->id], $left->parents()->pluck('propositions.id')->all());
        $this->assertSame([$read->id], $right->parents()->pluck('propositions.id')->all());

        // A third participant grounds a still-active Signal before reconciliation.
        // This earns source evidence that must be reset at the following transitions.
        $grounder = $this->participant('grounder');
        $this->reachDisperse($grounder, $signal, $earlyStance, $read);
        $this->move($grounder, [
            'action' => 'instrument.execute', 'proposition_id' => $left->id,
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/dispersed'],
        ]);
        $history = app(PropositionStateService::class);
        $this->assertCount(1, $history->receipts($left));

        $state = $this->move($builder, ['action' => 'proposition.reconcile', 'other_proposition_id' => $left->id]);
        $stance = $this->gate($state);
        $this->assertSame('stance', $stance->register);
        $this->assertSame('bias', $state['plan']['phase']['kind']);
        $parents = $stance->parents()->get();
        $this->assertEqualsCanonicalizing([$left->id, $right->id], $parents->pluck('id')->all());
        $this->assertTrue($parents->every(fn ($parent) => $parent->pivot->type === 'reconcile'));
        $this->assertCount(0, $history->attestations(collect([$stance])));
        $this->assertCount(0, $history->receipts($stance));
        $this->assertSame(1, $history->touchCount($stance)); // Newly opened Bias only.
        $this->assertArrayNotHasKey('proposition.bias', $state['plan']['schema']['$defs']['actions']);
        $this->assertSame([$stance->id], array_column($state['plan']['gate_options'], 'id')); // Position remains contextual, without a Bias action.

        // Mediate participants reinforce the inherited direction of their immediate Write Stance.
        $this->receiptFixture($stance);
        foreach ([$author, $grounder] as $participant) {
            $this->move($participant, ['action' => 'attestation.create', 'proposition_id' => $stance->id, 'direction' => 'support']);
        }
        $bias = Phase::findOrFail($this->state($builder)['plan']['phase']['id']);
        $this->assertSame(1.0, app(BiasGateService::class)->leanFor($bias));
        $this->assertSame([$stance->id], array_column($this->state($builder)['plan']['gate_options'], 'id'));
        $state = $this->move($builder, ['action' => 'proposition.bias', 'proposition_id' => $stance->id]);
        $concord = $this->gate($state);
        $this->assertSame('concord', $concord->register);
        $this->assertSame('expressive', $concord->tier);
        $this->assertSame($stance->statement, $concord->statement);
        $edge = $concord->parents()->sole()->pivot;
        $this->assertSame($stance->id, $edge->source_proposition_id);
        $this->assertSame('reclassify', $edge->type);
        $this->assertSame(SupersessionStrength::Hard, SupersessionStrength::for($edge->type));
        $this->assertCount(0, app(BiasGateService::class)->populationFor($bias));
        $this->assertSame([$concord->id], $history->sourceIds($concord)->all());
        $this->assertCount(0, $history->attestations(collect([$concord])));
        $this->assertCount(1, $history->receipts($concord));
        $this->assertSame(1, $history->touchCount($concord)); // New Order, no Stance history.
        $this->assertSame(3, $history->touchCount($stance));
        $this->assertFalse($state['plan']['commitment']['requirements']['attestation']);
        $this->assertTrue($state['plan']['commitment']['requirements']['receipt']);
        $this->assertCommitRejected($builder, 'missing_effective_attestation');

        // Bias exposes this new Concord as an existing Write child to the other participants.
        $this->move($author, ['action' => 'attestation.create', 'proposition_id' => $concord->id, 'direction' => 'support']);
        $state = $this->state($builder);
        $this->assertTrue($state['plan']['commitment']['requirements']['attestation']);
        $this->assertTrue($state['plan']['commitment']['requirements']['receipt']);
        $this->move($grounder, [
            'action' => 'instrument.execute', 'proposition_id' => $concord->id,
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/final-concord'],
        ]);

        $receiptId = $concord->instrumentExecutions()->with('receipt')->latest('id')->firstOrFail()->receipt->id;
        $this->assertSame(0, $this->state($builder)['plan']['commitment']['reinforcement_count']);
        $this->move($builder, ['action' => 'receipt.read', 'receipt_id' => $receiptId]);

        // These three participants have each observed Signal, Read and final Concord once.
        // Equal active-head touches have zero CV, matching the Origin; depth has grown.
        $order = Phase::findOrFail($this->state($builder)['plan']['phase']['id']);
        $comparison = app(ContextLineageComparisonService::class)->prospectiveCommit($concord, $order);
        $this->assertSame(0.0, app(ClosureCalculator::class)->coefficientOfVariation($comparison->current));
        $state = $this->state($builder);
        $eligibility = $state['plan']['commitment'];
        $this->assertTrue($eligibility['requirements']['attestation']);
        $this->assertTrue($eligibility['requirements']['receipt']);
        $this->assertFalse($eligibility['requirements']['closure'], json_encode($eligibility));
        $this->assertSame('negative', $eligibility['closure']['depth_direction']);
        $this->assertSame('positive', $eligibility['closure']['cadence_direction']);
        $this->assertCommitRejected($builder, 'depth_cadence_directions_diverge');

        // The early Signal/Stance were hard-reclassified. A new observer reaches
        // the still-active Read to create real touch variability on active history.
        // No counts, graph shapes or Closure values are patched.
        $observer = $this->participant('observer');
        $this->reachDisperse($observer, $signal, $earlyStance, $read);
        $comparison = app(ContextLineageComparisonService::class)->prospectiveCommit($concord, $order);
        $this->assertGreaterThan(0, app(ClosureCalculator::class)->coefficientOfVariation($comparison->current));
        $state = $this->state($builder);
        $eligibility = $state['plan']['commitment'];
        $this->assertTrue($eligibility['admissible'], json_encode($eligibility));
        $this->assertSame('negative', $eligibility['closure']['depth_direction']);
        $this->assertSame('negative', $eligibility['closure']['cadence_direction']);
        $this->assertSame($origin->id, $eligibility['closure']['history_proposition_id']);
        $this->assertArrayHasKey('proposition.commit', $state['plan']['schema']['$defs']['actions']);

        $earnedAttestations = $history->attestations(collect([$concord]))->pluck('id')->all();
        $earnedReceipts = $history->receipts($concord)->pluck('id')->all();
        $earnedTouches = $history->touchCount($concord);
        $this->assertCount(1, $earnedAttestations);
        $this->assertCount(2, $earnedReceipts);
        $this->assertSame(3, $earnedTouches);
        $synthetic = $comparison->current->propositions->last();
        $this->assertSame($earnedTouches, $history->touchCount($synthetic));
        $this->assertSame($earnedAttestations, $history->attestations(collect([$synthetic]))->pluck('id')->all());
        $this->assertSame($earnedReceipts, $history->receipts($synthetic)->pluck('id')->all());
        $counts = [Attestation::count(), Receipt::count(), Phase::count()];
        $state = $this->move($builder, ['action' => 'proposition.commit']);
        $committed = Proposition::findOrFail($builder->fresh()->current_proposition_id);
        $this->assertSame('committal', $committed->tier);
        $this->assertSame($concord->statement, $committed->statement);
        $this->assertSame([$committed->id, $concord->id], $history->sourceIds($committed)->all());
        $this->assertSame($earnedTouches, $history->touchCount($committed));
        $this->assertSame($earnedAttestations, $history->attestations(collect([$committed]))->pluck('id')->all());
        $this->assertSame($earnedReceipts, $history->receipts($committed)->pluck('id')->all());
        $this->assertSame($counts, [Attestation::count(), Receipt::count(), Phase::count()]);
        $this->assertSame($concord->id, Attestation::findOrFail($earnedAttestations[0])->proposition_id);
        $this->assertEqualsCanonicalizing([$stance->id, $concord->id], Receipt::whereKey($earnedReceipts)->pluck('receiptable_id')->all());
        $this->assertSame('commit', $committed->parents()->sole()->pivot->type);
        $this->assertNotNull($order->fresh()->completed_at);
        $this->assertNotNull($order->cycle->fresh()->completed_at);

        // Actual snapshot evaluation must agree with the preview before any new observations.
        $snapshot = $committed->commitmentSnapshot;
        foreach (['depth', 'cadence', 'closure'] as $component) {
            $this->assertEqualsWithDelta((float) $eligibility['closure'][$component], (float) $snapshot->{$component}, 0.0000001);
        }
        $this->assertSame($eligibility['closure']['depth_direction'], $snapshot->depth_direction->value);
        $this->assertSame($eligibility['closure']['cadence_direction'], $snapshot->cadence_direction->value);
        $this->assertSame($origin->id, $snapshot->closure_history_proposition_id);
        $this->assertSame(LineageMeasurementService::VERSION, $snapshot->calculator_version);
        $this->assertSame(ContextDomain::after((float) $snapshot->tension, ContextDomain::Settlement), $snapshot->direction_after);
        $this->assertSame('waiting', $state['status']);
        $this->assertSame($snapshot->isClosureBoundary(), $state['last_commitment']['closure_boundary']);
        $this->assertSame($snapshot->direction_after->value, $state['domain']);
        $this->assertSame($horizon->sequence + (int) $snapshot->isClosureBoundary(), $state['horizon_sequence']);
        $this->assertSame($horizon->id, $committed->contextHorizonMembership->context_horizon_id);
        $active = ContextHorizon::whereNull('closed_at')->sole();
        if ($snapshot->isClosureBoundary()) {
            $this->assertSame($committed->id, $horizon->fresh()->closed_by_proposition_id);
            $this->assertSame($committed->id, $active->opened_by_proposition_id);
        } else {
            $this->assertSame($horizon->id, $active->id);
            $this->assertNull($horizon->fresh()->closed_at);
        }
        $this->assertSame($state['last_commitment'], $this->state($builder)['last_commitment']);
        $this->assertSame(2, CommitmentSnapshot::count());

        // A real successful Commit may be followed by a fresh Cycle. Selection
        // can choose either eligible commitment, but never the expressive head.
        $restart = app(ParticipationService::class)->enter($builder);
        $entry = $this->gate($restart);
        $this->assertSame('committal', $entry->tier);
        $this->assertContains($entry->id, [$origin->id, $committed->id]);
        $fresh = $this->gate($this->move($builder, ['action' => 'proposition.variant', 'statement' => 'A fresh beginning.']));
        $this->assertSame([$entry->id], $fresh->parents()->pluck('propositions.id')->all());
        foreach (Proposition::where('tier', 'expressive')->get() as $proposition) {
            $this->assertExpressiveDepth($proposition, 6);
        }
    }

    public function test_another_participant_reenters_before_layer_six_is_committed(): void
    {
        $this->substrate = new Substrate;
        $this->substrate->forceFill([
            'slug' => 'concurrent-entry', 'title' => 'Concurrent Cycle entry',
            'visibility' => 'public', 'configuration' => [],
        ])->save();
        $author = $this->participant('author');
        $origin = $this->gate(app(ParticipationService::class)->enter($author, [
            'statement' => 'A shared Origin.',
            'attestation' => ['direction' => 'support'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin'],
        ]));
        $signal = $this->gate($this->move($author, ['action' => 'proposition.variant', 'statement' => 'Signal']));
        $earlyStance = $this->gate($this->move($author, ['action' => 'proposition.reclassify']));
        app(OperationalInstrumentService::class)->execute($earlyStance->phase, $earlyStance, ['kind' => 'citation', 'reference' => $signal->id]);
        $read = $this->gate($this->move($author, ['action' => 'proposition.reclassify']));
        $left = $this->gate($this->move($author, ['action' => 'proposition.variant', 'statement' => 'Dispersed Signal']));
        $builder = $this->participant('builder');
        $this->reachDisperse($builder, $signal, $earlyStance, $read);
        $this->move($builder, ['action' => 'proposition.variant', 'statement' => 'Other Signal']);
        $stance = $this->gate($this->move($builder, ['action' => 'proposition.reconcile', 'other_proposition_id' => $left->id]));
        $this->receiptFixture($stance);
        $this->move($author, ['action' => 'attestation.create', 'proposition_id' => $stance->id, 'direction' => 'support']);
        $concord = $this->gate($this->move($builder, ['action' => 'proposition.bias', 'proposition_id' => $stance->id]));

        // Builder remains in Order. Author completes Order by grounding the same
        // expressive Concord, then re-enters before Builder has committed it.
        $this->move($author, ['action' => 'attestation.create', 'proposition_id' => $concord->id, 'direction' => 'support']);
        $completed = $this->move($author, [
            'action' => 'instrument.execute', 'proposition_id' => $concord->id,
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/concord'],
        ]);
        $this->assertSame('waiting', $completed['status']);
        $this->assertSame($concord->id, $author->fresh()->current_proposition_id);
        $this->assertSame('order', $this->state($builder)['plan']['phase']['kind']);
        $this->assertSame(1, CommitmentSnapshot::count());
        $this->assertExpressiveDepth($concord, 6);

        $state = app(ParticipationService::class)->enter($author);
        $this->assertSame($origin->id, $state['plan']['gate_id']);
        $this->assertSame('committal', $state['plan']['gate_options'][0]['tier']);
        $this->assertSame('distinguish', $state['plan']['phase']['kind']);
        $this->assertArrayNotHasKey('proposition.variant', $state['plan']['schema']['$defs']['actions']);
        $before = Proposition::count();
        $continued = $this->gate($this->move($author, [
            'action' => 'proposition.traverse', 'proposition_id' => $signal->id,
        ]));
        $this->assertSame($signal->id, $continued->id);
        $this->assertSame($before, Proposition::count());
        $this->assertSame([$origin->id], $continued->parents()->pluck('propositions.id')->all());
        $this->assertDatabaseMissing('supersessions', [
            'source_proposition_id' => $concord->id,
            'target_proposition_id' => $continued->id,
        ]);
        foreach (Proposition::where('tier', 'expressive')->get() as $proposition) {
            $this->assertExpressiveDepth($proposition, 6);
        }
    }

    /** Check every parent branch, including both Reconcile sources. */
    private function assertExpressiveDepth(Proposition $proposition, int $remaining): void
    {
        if ($proposition->tier === 'committal') {
            return;
        }
        $this->assertGreaterThan(0, $remaining, 'More than six consecutive expressive tiers.');
        $parents = $proposition->parents()->get();
        $this->assertNotEmpty($parents);
        foreach ($parents as $parent) {
            $this->assertExpressiveDepth($parent, $remaining - 1);
        }
    }

    private function assertCommitRejected(Participant $participant, string $reason): void
    {
        $state = $this->state($participant);
        $this->assertContains($reason, $state['plan']['commitment']['reasons']);
        $this->assertArrayNotHasKey('proposition.commit', $state['plan']['schema']['$defs']['actions']);
        $phase = Phase::findOrFail($state['plan']['phase']['id']);
        $counts = [Proposition::count(), Supersession::count(), CommitmentSnapshot::count(), $this->substrate->ledgerEntries()->count()];
        try {
            app(ContributionDispatcher::class)->dispatch($participant, $phase, ['action' => 'proposition.commit', 'proposition_id' => $phase->gate_proposition_id]);
            $this->fail('Execution must enforce the same refusal as preview/schema.');
        } catch (DomainException) {
            $this->assertNull($phase->fresh()->completed_at);
            $this->assertSame($counts, [Proposition::count(), Supersession::count(), CommitmentSnapshot::count(), $this->substrate->ledgerEntries()->count()]);
        }
    }

    private function reachDisperse(Participant $participant, Proposition $signal, Proposition $stance, Proposition $read): void
    {
        app(ParticipationService::class)->enter($participant);
        foreach ([$signal, $stance, $read] as $proposition) {
            $this->receiptFixture($proposition);
            $this->move($participant, ['action' => 'attestation.create', 'proposition_id' => $proposition->id, 'direction' => 'support']);
        }
        $this->assertSame('disperse', $this->state($participant)['plan']['phase']['kind']);
    }

    private function participant(string $callsign): Participant
    {
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $this->substrate->id, 'callsign' => $callsign, 'max_read_count' => 25])->save();

        return $participant;
    }

    private function state(Participant $participant): array
    {
        return app(ParticipationService::class)->stateFor($participant);
    }

    private function move(Participant $participant, array $input): array
    {
        if ($input['action'] === 'proposition.reclassify') {
            $this->classificationEvidenceFixture($participant->fresh()->position);
        }
        if ($input['action'] === 'proposition.commit') {
            $input['proposition_id'] = $participant->fresh()->current_proposition_id;
        }
        $phaseId = $this->state($participant)['plan']['phase']['id'];

        return app(ParticipationService::class)->contribute($participant, $phaseId, $input);
    }

    private function gate(array $state): Proposition
    {
        return Proposition::findOrFail($state['plan']['gate_id']);
    }
}
