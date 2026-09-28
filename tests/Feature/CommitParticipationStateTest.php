<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\CommitmentAdmissibilityService;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\Observables\ChiralityCalculator;
use App\Services\Observables\ContextLineageComparisonService;
use App\Services\Observables\ReinforcementService;
use App\Services\OperationalInstrumentService;
use App\Services\PropositionStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CommitParticipationStateTest extends TestCase
{
    use RefreshDatabase;

    private Phase $phase;

    protected function setUp(): void
    {
        parent::setUp();
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'inheritance', 'title' => 'Inheritance', 'visibility' => 'public', 'configuration' => []])->save();
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer'])->save();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => 1])->save();
        $this->phase = new Phase;
        $this->phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Order, 'write_register' => 'canon', 'started_at' => now()])->save();
    }

    public function test_prospective_and_persisted_commits_inherit_touches_and_evidence_without_copying(): void
    {
        $gate = $this->node('expressive');
        $this->phase->gate_proposition_id = $gate->id;
        $this->phase->save();
        $this->touch($gate);
        $attestation = new Attestation;
        $attestation->forceFill(['phase_id' => $this->phase->id, 'proposition_id' => $gate->id, 'direction' => 'support'])->save();
        app(LedgerService::class)->record($attestation);
        $receipt = $this->loadedReceiptFixture($gate);
        $counts = [Attestation::count(), Receipt::count(), Phase::count()];
        $comparison = app(ContextLineageComparisonService::class)->prospectiveCommit($gate, $this->phase);
        $preview = $comparison->current->propositions->last();
        $actual = $this->node('committal');
        $this->edge($gate, $actual, 'commit');
        $state = app(PropositionStateService::class);
        foreach ([$preview, $actual] as $head) {
            $state->loadAttestationCounts(collect([$head]));
            $this->assertSame(['support' => 1, 'refute' => 0], $head->attestationCounts->all());
            $this->assertSame(3, $state->touchCount($head)); // Includes the loading fixture’s Gate Phase.
            $this->assertSame([$attestation->id], $state->attestations(collect([$head]))->pluck('id')->all());
            $this->assertSame([$receipt->id], $state->receipts($head)->pluck('id')->all());
            $this->assertTrue(app(CommitmentAdmissibilityService::class)->grounding($head)['admissible']);
            $this->assertSame(1, app(ReinforcementService::class)->count($head));
        }
        $this->assertSame(1.0, app(ChiralityCalculator::class)->leanFor(collect([$actual->id])));
        $this->assertSame($counts, [Attestation::count(), Receipt::count(), Phase::count()]);
        $this->assertSame($gate->id, $attestation->fresh()->proposition_id);
        $this->assertSame($gate->id, $receipt->fresh()->receiptable_id);

        // A later Commit inherits history transitively, including its predecessor's own touches.
        $this->touch($actual);
        $next = $this->node('committal');
        $this->edge($actual, $next, 'commit');
        $this->assertSame(4, $state->touchCount($next));
        $this->assertCount(1, $state->receipts($next));
        $this->assertCount(1, $state->attestations(collect([$actual, $next])));
    }

    public function test_inheritance_respects_effective_attestations_completed_receipts_and_commit_edges_only(): void
    {
        $gate = $this->node('expressive');
        $this->touch($gate);
        $old = new Attestation;
        $old->forceFill(['phase_id' => $this->phase->id, 'proposition_id' => $gate->id, 'direction' => 'support'])->save();
        $touch = $this->touch($gate);
        $effective = new Attestation;
        $effective->forceFill(['phase_id' => $touch->id, 'proposition_id' => $gate->id,
            'supersedes_attestation_id' => $old->id, 'direction' => 'refute'])->save();
        $executions = app(InstrumentExecutionService::class);
        $execution = $executions->start($this->phase, $gate, 'hyperlink', 'https://example.test/attestation');
        $receipt = $executions->complete($execution, $effective);
        $pending = $executions->start($this->phase, $gate, 'hyperlink', 'https://example.test/pending');
        $pendingReceipt = new Receipt;
        $pendingReceipt->forceFill(['instrument_execution_id' => $pending->id, 'receiptable_type' => $gate->getMorphClass(),
            'receiptable_id' => $gate->id, 'type' => 'hyperlink', 'reference' => $pending->reference, 'signature' => 'pending'])->save();
        $commit = $this->node('committal');
        $this->edge($gate, $commit, 'commit');
        $variant = $this->node('expressive');
        $this->edge($gate, $variant, 'variant');
        $state = app(PropositionStateService::class);
        $state->loadAttestationCounts(collect([$gate, $commit, $variant]));
        $this->assertSame(['support' => 0, 'refute' => 1], $commit->attestationCounts->all());
        $this->assertSame(['support' => 0, 'refute' => 0], $variant->attestationCounts->all());
        $this->assertSame([$effective->id], $state->attestations(collect([$commit]))->pluck('id')->all());
        $this->assertSame([$receipt->id], $state->receipts($commit)->pluck('id')->all());
        $this->assertSame(-1.0, app(ChiralityCalculator::class)->leanFor(collect([$commit->id])));
        $this->assertSame(0, $state->touchCount($variant));
        $this->assertCount(0, $state->receipts($variant));
        $this->assertCount(0, $state->attestations(collect([$variant])));
    }

    public function test_new_phase_touches_dirty_the_region_but_completion_is_not_another_touch(): void
    {
        $node = $this->node('expressive');
        $measurements = app(\App\Services\PropositionMeasurementService::class);
        $measurement = $measurements->recalculate($node);
        $touch = $this->touch($node);
        $this->assertDatabaseHas('potential_dirty_horizons', [
            'context_horizon_id' => $this->phase->cycle->context_horizon_id, 'measurements_dirty' => true,
        ]);
        app(\App\Services\PotentialProjectionService::class)->refresh($this->phase->cycle->participant->substrate_id);
        $this->assertDatabaseCount('potential_dirty_horizons', 0);
        $touch->completed_at = now()->addHour();
        $touch->save();
        $this->assertSame(\App\Services\Observables\LineageMeasurementService::VERSION,
            $measurement->fresh()->calculator_version);
        $this->assertSame(1, app(PropositionStateService::class)->touchCount($node));
    }

    public static function historyResets(): array
    {
        return [
            'Variant' => ['variant'],
            'Revise' => ['revise'],
            'Reclassify' => ['reclassify'],
            'Reconcile' => ['reconcile'],
        ];
    }

    #[DataProvider('historyResets')]
    public function test_commit_cannot_recover_history_from_before_a_non_commit_reset(string $type): void
    {
        $source = $this->node('expressive');
        $this->touch($source);
        $old = new Attestation;
        $old->forceFill(['phase_id' => $this->phase->id, 'proposition_id' => $source->id, 'direction' => 'refute'])->save();
        app(OperationalInstrumentService::class)->execute($this->phase, $source,
            ['kind' => 'hyperlink', 'reference' => 'https://example.test/before-reset']);
        $priorCommit = $this->node('committal');
        $this->edge($source, $priorCommit, 'commit');
        $fresh = $this->node('expressive');
        $this->edge($priorCommit, $fresh, $type);
        $state = app(PropositionStateService::class);
        $this->assertSame(1, $state->touchCount($priorCommit));
        $this->assertCount(1, $state->attestations(collect([$priorCommit])));
        $this->assertCount(1, $state->receipts($priorCommit));
        $this->assertSame(0, $state->touchCount($fresh));
        $this->assertCount(0, $state->attestations(collect([$fresh])));
        $this->assertCount(0, $state->receipts($fresh));

        $touch = $this->touch($fresh);
        $earned = new Attestation;
        $earned->forceFill(['phase_id' => $touch->id, 'proposition_id' => $fresh->id, 'direction' => 'support'])->save();
        $receipt = app(OperationalInstrumentService::class)->execute($touch, $fresh,
            ['kind' => 'hyperlink', 'reference' => 'https://example.test/after-reset']);
        $comparison = app(ContextLineageComparisonService::class)->prospectiveCommit($fresh, $this->phase);
        $preview = $comparison->current->propositions->last();
        $actual = $this->node('committal');
        $this->edge($fresh, $actual, 'commit');
        foreach ([$preview, $actual] as $head) {
            $this->assertSame([$head->id, $fresh->id], $state->sourceIds($head)->all());
            $this->assertSame(1, $state->touchCount($head));
            $this->assertSame([$earned->id], $state->attestations(collect([$head]))->pluck('id')->all());
            $this->assertSame([$receipt->id], $state->receipts($head)->pluck('id')->all());
        }
    }

    public function test_preview_structural_parents_do_not_implicitly_grant_commit_history(): void
    {
        $source = $this->node('expressive');
        $this->touch($source);
        $comparison = app(ContextLineageComparisonService::class)->prospectiveCommit($source, $this->phase);
        $candidate = $comparison->current->propositions->last();
        $state = app(PropositionStateService::class);
        $this->assertSame(1, $state->touchCount($candidate));

        // Structural ancestry remains, but there is no declared Commit transition.
        $candidate->unsetRelation('commitSources');
        $this->assertSame([$source->id], $candidate->parents->pluck('id')->all());
        $this->assertSame([$candidate->id], $state->sourceIds($candidate)->all());
        $this->assertSame(0, $state->touchCount($candidate));
    }

    private function touch(Proposition $gate): Phase
    {
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $this->phase->cycle_id, 'kind' => PhaseKind::Persist,
            'write_register' => 'frontier', 'gate_proposition_id' => $gate->id, 'started_at' => now(), 'completed_at' => now()])->save();
        return $phase;
    }

    private function node(string $tier): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $this->phase->id, 'register' => 'canon', 'tier' => $tier, 'statement' => 'Inherited.'])->save();
        app(LedgerService::class)->record($node);
        return $node;
    }

    private function edge(Proposition $source, Proposition $target, string $type): void
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $this->phase->id, 'source_proposition_id' => $source->id,
            'target_proposition_id' => $target->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
    }
}
