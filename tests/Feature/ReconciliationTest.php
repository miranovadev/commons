<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Enums\SupersessionStrength;
use App\Models\Attestation;
use App\Models\Connection;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContributionDispatcher;
use App\Services\InputPlanService;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\OpenAIResponseSchema;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipationService;
use App\Services\PropositionMeasurementService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Substrate $substrate;
    private Phase $history;
    private Participant $viewer;
    private Phase $phase;
    private Proposition $parent;
    private Proposition $gate;

    private function fixture(PhaseKind $kind = PhaseKind::Mediate, float $correspondence = -0.8): void
    {
        $this->substrate = new Substrate;
        $this->substrate->forceFill(['slug' => 'reconcile', 'title' => 'Reconcile', 'visibility' => 'public', 'configuration' => []])->save();
        $this->history = $this->newPhase($this->participant('author'), PhaseKind::Distinguish, 'activity', true);
        $this->parent = $this->node('Parent', 'canon', tier: $kind === PhaseKind::Mediate ? 'expressive' : 'committal');
        if ($kind === PhaseKind::Mediate) {
            $root = $this->node('Shared provenance', 'frontier', tier: 'committal');
            $this->edge($root, $this->parent);
        }
        $register = $kind === PhaseKind::Mediate ? 'activity' : 'frontier';
        $this->gate = $this->node("  Gate statement\nwith exact whitespace.  \n", $register, $correspondence);
        $this->edge($this->parent, $this->gate);
        $this->viewer = $this->participant('viewer');
        $this->viewer->current_proposition_id = $this->gate->id;
        $this->viewer->save();
        if ($kind === PhaseKind::Mediate) {
            $disperse = $this->newPhase($this->viewer, PhaseKind::Disperse, 'activity');
            $disperse->gate_proposition_id = $this->parent->id;
            $disperse->save();
            $this->phase = app(\App\Services\PhaseProgressionService::class)->advance($disperse, $this->gate);
        } else {
            $this->phase = $this->newPhase($this->viewer, $kind, 'canon');
        }
        $this->phase->gate_proposition_id = $this->gate->id;
        $this->phase->save();
        // Phase creation invalidates derived views; install this test's controlled
        // structural comparison after recording the Gate observation.
        app(PropositionMeasurementService::class)->project($this->gate, -$correspondence, -1, -1);
        app(PropositionMeasurementService::class)->project($this->parent, $correspondence, -1, -1);
    }

    public static function correspondenceDirections(): array
    {
        return ['consonant' => [0.8], 'dissonant' => [-0.8], 'neutral' => [0.0]];
    }

    #[DataProvider('correspondenceDirections')]
    public function test_mediate_filters_read_siblings_using_the_previous_disperse_gate(float $direction): void
    {
        $this->fixture(correspondence: $direction);
        [$positive, $positiveGate] = $this->readSibling('Consonant', 0.5, -1);
        [$negative, $negativeGate] = $this->readSibling('Dissonant', -0.5, 1);
        $this->readSibling('Neutral', 0, 0);
        $local = $this->sibling('Anchor child with neutral Correspondence', 0);
        $plan = $this->plan();
        $this->assertSame($this->parent->id, $plan['read_options'][0]['id']);
        $this->assertEqualsCanonicalizing($direction === 0.0 ? [$this->parent->id] :
            [$this->parent->id, ($direction > 0 ? $positive : $negative)->id], array_column($plan['read_options'], 'id'));
        $this->assertEqualsCanonicalizing($direction === 0.0 ? [$local->id] :
            [$local->id, ($direction > 0 ? $positiveGate : $negativeGate)->id], array_column($plan['gate_options'], 'id'));
    }

    public function test_mediate_gate_options_are_active_immediate_children_of_exposed_reads(): void
    {
        $this->fixture();
        $valid = $this->sibling('Valid');
        $unrelated = $this->node('Unrelated active head', 'activity');
        $otherParent = $this->node('Other parent', 'canon', tier: 'committal');
        $this->edge($otherParent, $unrelated);
        $child = $this->node('Write child', 'frontier');
        $this->edge($this->gate, $child);
        $grandchild = $this->node('Grandchild', 'activity');
        $this->edge($child, $grandchild);
        $wrongRegister = $this->node('Wrong register sibling', 'frontier');
        $this->edge($this->parent, $wrongRegister);
        $hard = $this->sibling('Hard superseded');
        $this->edge($hard, $this->node('Replacement', 'frontier'), 'reclassify');
        $soft = $this->sibling('Soft branch remains active');
        $this->edge($soft, $this->node('Soft continuation', 'frontier'));
        $plan = $this->plan();
        $this->assertEqualsCanonicalizing([$valid->id, $soft->id], array_column($plan['gate_options'], 'id'));
        $this->assertSame([$child->id], array_column($plan['write_options'], 'id'));
        foreach ([$this->gate, $this->parent, $unrelated, $child, $grandchild, $wrongRegister, $hard] as $invalid) {
            $this->assertInput($plan['schema'], ['action' => 'proposition.reconcile', 'other_proposition_id' => $invalid->id], false);
        }
    }

    public function test_horizon_and_substrate_boundaries_cannot_supply_siblings(): void
    {
        $this->fixture();
        $valid = $this->sibling('Valid');
        $wrongHorizon = $this->sibling('Other Horizon');
        $horizon = $wrongHorizon->contextHorizonMembership->horizon->replicate();
        $horizon->sequence = 2;
        $horizon->closed_at = now();
        $horizon->save();
        $wrongHorizon->contextHorizonMembership->forceFill(['context_horizon_id' => $horizon->id])->save();
        $foreign = $this->sibling('Foreign substrate');
        $other = $this->substrate->replicate();
        $other->slug = 'foreign';
        $other->save();
        $outsider = $this->participant('outsider');
        $outsider->substrate_id = $other->id;
        $outsider->save();
        $foreignPhase = $this->newPhase($outsider, PhaseKind::Distinguish, 'activity', true);
        $foreign->phase_id = $foreignPhase->id;
        $foreign->save();
        $this->assertSame([$valid->id], array_column($this->plan()['gate_options'], 'id'));
    }

    public function test_bias_uses_weighted_effective_lean_not_projected_chirality_sign(): void
    {
        $this->fixture(PhaseKind::Bias);
        $support = $this->sibling('Supported');
        $refute = $this->sibling('Refuted');
        $this->attest($this->gate, 'support');
        $this->attest($support, 'support');
        $negative = $this->attest($refute, 'refute');
        // Bootstrap Chirality is negative for every node; raw lean is positive.
        $this->assertSame([$support->id], array_column($this->plan()['gate_options'], 'id'));
        $instruments = app(InstrumentExecutionService::class);
        foreach (range(1, 4) as $i) {
            $execution = $instruments->start($negative->phase, $refute, 'hyperlink', 'https://example.test/'.$i);
            $instruments->complete($execution, $negative, 'unique-'.$i);
        }
        $this->assertSame([$refute->id], array_column($this->plan()['gate_options'], 'id'));
        $replacement = new Attestation;
        $later = $this->newPhase($negative->phase->cycle->participant, PhaseKind::Persist, 'frontier', true);
        $replacement->forceFill(['phase_id' => $later->id, 'proposition_id' => $refute->id, 'direction' => 'support', 'supersedes_attestation_id' => $negative->id])->save();
        $this->assertEqualsCanonicalizing([$support->id, $refute->id], array_column($this->plan()['gate_options'], 'id'));
    }

    public function test_bias_neutral_ties_and_hard_superseded_votes_do_not_create_a_direction(): void
    {
        $this->fixture(PhaseKind::Bias);
        $support = $this->sibling('Support');
        $refute = $this->sibling('Refute');
        $this->attest($support, 'support');
        $this->attest($refute, 'refute');
        $this->assertSame([], $this->plan()['gate_options']);
        $continuation = $this->node('Hard replacement', 'canon');
        $this->edge($refute, $continuation, 'reclassify');
        $this->assertSame([$support->id], array_column($this->plan()['gate_options'], 'id'));
        $softHead = $this->node('Soft head', 'canon');
        $this->edge($support, $softHead);
        $this->attest($softHead, 'refute');
        $this->assertSame([], $this->plan()['gate_options']);
        $this->attest($this->node('Unrelated positive', 'frontier'), 'support');
        $this->assertSame([], $this->plan()['gate_options']);
    }

    public function test_bias_evaluates_multi_parent_contexts_separately_and_deduplicates_candidates(): void
    {
        $this->fixture(PhaseKind::Bias);
        $support = $this->sibling('Positive family');
        $this->attest($support, 'support');
        $second = $this->node('Second parent', 'canon', tier: 'committal');
        $this->edge($second, $this->gate);
        $refute = $this->node('Negative family', 'frontier');
        $this->edge($second, $refute);
        $this->attest($refute, 'refute');
        $this->assertEqualsCanonicalizing([$support->id, $refute->id], array_column($this->plan()['gate_options'], 'id'));
        $this->edge($second, $support);
        // The first parent's positive direction still supplies Support once.
        $this->assertSame([$support->id], array_column($this->plan()['gate_options'], 'id'));
    }

    public static function reconciliationPhases(): array
    {
        return [[PhaseKind::Mediate], [PhaseKind::Bias]];
    }

    #[DataProvider('reconciliationPhases')]
    public function test_reconcile_is_structural_selection_with_exact_gate_content(PhaseKind $kind): void
    {
        $this->fixture($kind);
        $sibling = $kind === PhaseKind::Mediate
            ? $this->readSibling('Different Read parent', -0.5, 1)[1]
            : $this->sibling('Different statement from B');
        $this->attest($sibling, 'support');
        $this->attest($this->gate, 'support');
        foreach ([$this->gate, $sibling] as $source) {
            app(\App\Services\OperationalInstrumentService::class)->execute($this->phase, $source,
                ['kind' => 'hyperlink', 'reference' => 'https://example.test/source/'.$source->id]);
        }
        $plan = $this->plan();
        $input = ['action' => 'proposition.reconcile', 'other_proposition_id' => $sibling->id];
        $this->assertSame([$sibling->id], array_column($plan['gate_options'], 'id'));
        $this->assertSame([], $plan['write_options']);
        $this->assertFalse($plan['tragedy']);
        $this->assertSame(['action', 'other_proposition_id'], $plan['schema']['$defs']['actions']['proposition.reconcile']['required']);
        $this->assertInput($plan['schema'], $input, true);
        $this->assertInput($plan['schema'], [...$input, 'statement' => 'Synthesis'], false);
        $state = app(ParticipationService::class)->stateFor($this->viewer);
        $prompt = app(ParticipantPromptFactory::class)->make(new Connection, $state);
        $this->assertSame($plan['schema'], $prompt['schema']);
        $this->assertSame(json_decode(json_encode($plan['read_options']), true), json_decode($prompt['user'], true)['plan']['read_options']);
        $transport = app(OpenAIResponseSchema::class)->fromCommons($prompt['schema']);
        $properties = $transport['properties']['contribution'];
        $this->assertArrayNotHasKey('statement', $properties['properties']);
        $wire = array_replace(array_fill_keys($properties['required'], null), $input);
        $this->assertInput($transport, ['contribution' => $wire], true);
        $this->assertInput($transport, ['contribution' => [...$wire, 'statement' => 'Synthesis']], false);
        $count = Proposition::count();
        $ledger = $this->substrate->ledgerEntries()->count();
        app(ParticipationService::class)->contribute($this->viewer, $this->phase->id, $input);
        $created = Proposition::findOrFail($this->viewer->fresh()->current_proposition_id);
        $this->assertSame($count + 1, Proposition::count());
        $this->assertSame($this->gate->statement, $created->statement);
        $this->assertSame('expressive', $created->tier);
        $this->assertSame($this->phase->write_register, $created->register);
        $history = app(\App\Services\PropositionStateService::class);
        $this->assertCount(0, $history->attestations(collect([$created])));
        $this->assertCount(0, $history->receipts($created));
        $this->assertSame([$created->id], $history->sourceIds($created)->all());
        $this->assertSame(1, $history->touchCount($created)); // New Bias/Order observation only.
        $this->assertCount(2, $history->attestations(collect([$this->gate, $sibling])));

        $edges = Supersession::where('target_proposition_id', $created->id)->get();
        $this->assertCount(2, $edges);
        $this->assertEqualsCanonicalizing([$this->gate->id, $sibling->id], $edges->pluck('source_proposition_id')->all());
        foreach ($edges as $edge) {
            $this->assertSame('reconcile', $edge->type);
            $this->assertSame(SupersessionStrength::Hard, SupersessionStrength::for($edge->type));
        }
        $this->assertSame($ledger + 3, $this->substrate->ledgerEntries()->count());
        $this->assertNotNull($created->measurement);
    }

    public function test_mediate_exposes_all_gate_children_even_with_small_display_limits(): void
    {
        $this->fixture(correspondence: 0.8);
        config()->set('schemas.options.limit', 1);
        $this->viewer->max_read_count = 1;
        $this->viewer->save();
        [$read, $crossGate] = $this->readSibling('Matching Read', 0.5, -1);
        $first = $this->sibling('Negative Gate', -0.9);
        $second = $this->sibling('Neutral Gate', 0);
        $plan = $this->plan();
        $this->assertSame([$this->parent->id, $read->id], array_column($plan['read_options'], 'id'));
        $this->assertEqualsCanonicalizing([$crossGate->id, $first->id, $second->id], array_column($plan['gate_options'], 'id'));
        $this->assertSame(array_column($plan['gate_options'], 'id'), $plan['schema']['$defs']['actions']['proposition.reconcile']['properties']['other_proposition_id']['enum']);
    }

    public function test_bias_does_not_borrow_votes_beyond_a_committal_boundary(): void
    {
        $this->fixture(PhaseKind::Bias);
        $support = $this->sibling('Support');
        $this->attest($support, 'support');
        $boundary = $this->node('Boundary', 'canon', tier: 'committal');
        $this->edge($support, $boundary); // Soft connection keeps Support active.
        $beyond = $this->node('Beyond boundary', 'frontier');
        $this->edge($boundary, $beyond);
        $this->attest($beyond, 'refute');
        $this->assertSame([$support->id], array_column($this->plan()['gate_options'], 'id'));
    }

    public function test_dispatcher_rejects_non_sibling_without_partial_writes(): void
    {
        $this->fixture();
        $unrelated = $this->node('Not a sibling', 'activity');
        $count = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($this->viewer, $this->phase, ['action' => 'proposition.reconcile', 'other_proposition_id' => $unrelated->id]);
            $this->fail('Invalid counterparts must not be dispatched.');
        } catch (DomainException) {
            $this->assertSame($count, Proposition::count());
        }
    }

    public function test_mediate_read_expansion_is_one_step_in_the_same_register_and_context(): void
    {
        $this->fixture();
        [$validRead, $validGate] = $this->readSibling('Matching', -0.7, 1);
        [$opposite, $oppositeGate] = $this->readSibling('Opposite', 0.7, -1);
        [$wrongRegister, $wrongGate] = $this->readSibling('Wrong Read register', -0.7, -1);
        $wrongRegister->register = 'frontier';
        $wrongRegister->save();
        [$hardRead, $hardGate] = $this->readSibling('Replaced Read', -0.7, -1);
        $this->edge($hardRead, $this->node('Read replacement', 'canon'), 'reclassify');
        [$foreignHorizon, $foreignGate] = $this->readSibling('Other Horizon Read', -0.7, -1);
        $horizon = $foreignHorizon->contextHorizonMembership->horizon->replicate();
        $horizon->sequence = 2;
        $horizon->closed_at = now();
        $horizon->save();
        $foreignHorizon->contextHorizonMembership->forceFill(['context_horizon_id' => $horizon->id])->save();
        $distantRead = $this->node('Read grandchild', 'canon');
        $this->edge($validRead, $distantRead);
        $distantGate = $this->node('Distant Gate', 'activity');
        $this->edge($distantRead, $distantGate);
        $unrelatedRead = $this->node('Unrelated matching Read', 'canon');
        $unrelatedGate = $this->node('Unrelated Gate', 'activity');
        $this->edge($unrelatedRead, $unrelatedGate);
        $plan = $this->plan();
        $this->assertSame([$this->parent->id, $validRead->id], array_column($plan['read_options'], 'id'));
        $this->assertSame([$validGate->id], array_column($plan['gate_options'], 'id'));
        foreach ([$oppositeGate, $wrongGate, $hardGate, $foreignGate, $distantGate, $unrelatedGate] as $invalid) {
            $this->assertInput($plan['schema'], ['action' => 'proposition.reconcile', 'other_proposition_id' => $invalid->id], false);
        }
    }

    public function test_mediate_rechecks_read_direction_before_writing_and_does_not_filter_gate_votes(): void
    {
        $this->fixture();
        [$read, $candidate] = $this->readSibling('Matching Read', -0.5, 1);
        $this->attest($this->gate, 'support');
        $this->attest($candidate, 'refute');
        // Restore controlled Read directions after recording new Phase observations.
        app(PropositionMeasurementService::class)->project($this->parent, -0.8, -1, -1);
        app(PropositionMeasurementService::class)->project($read, -0.5, -1, -1);
        $input = ['action' => 'proposition.reconcile', 'other_proposition_id' => $candidate->id];
        $this->assertInput($this->plan()['schema'], $input, true);
        app(PropositionMeasurementService::class)->project($read, 0.5, -1, -1);
        $before = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($this->viewer, $this->phase, $input);
            $this->fail('A stale Read direction must not authorize reconciliation.');
        } catch (DomainException) {
            $this->assertSame($before, Proposition::count());
        }
    }

    public function test_mediate_requires_its_own_previous_disperse_phase_and_an_active_current_gate(): void
    {
        $this->fixture();
        $this->sibling('Available counterpart');
        $this->assertNotEmpty($this->plan()['gate_options']);
        $this->edge($this->gate, $this->node('Hard replacement', 'frontier'), 'reclassify');
        $this->assertSame([], $this->plan()['gate_options']);
        $this->phase->cycle->phases()->where('kind', PhaseKind::Disperse->value)->update(['completed_at' => null]);
        $this->assertCount(0, app(\App\Services\MediationReadService::class)->readsFor($this->phase));
    }

    private function readSibling(string $statement, float $correspondence, float $gateCorrespondence): array
    {
        $read = $this->node($statement, $this->parent->register, $correspondence);
        $this->edge($this->parent->parents()->firstOrFail(), $read);
        $gate = $this->node($statement.' child', $this->gate->register, $gateCorrespondence);
        $this->edge($read, $gate);
        return [$read, $gate];
    }

    private function assertInput(array $schema, array $input, bool $valid): void
    {
        $result = app(Validator::class)->validate(Helper::toJSON($input), Helper::toJSON($schema));
        $this->assertSame($valid, $result->isValid(), json_encode($input));
    }

    private function plan(): array { return app(InputPlanService::class)->forParticipant($this->viewer); }
    private function sibling(string $statement, float $correspondence = -0.5): Proposition
    {
        $node = $this->node($statement, $this->gate->register, $correspondence);
        $this->edge($this->parent, $node);
        return $node;
    }
    private function node(string $statement, string $register, float $correspondence = -0.5, string $tier = 'expressive'): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $this->history->id, 'register' => $register, 'tier' => $tier, 'statement' => $statement])->save();
        app(LedgerService::class)->record($node);
        app(PropositionMeasurementService::class)->project($node, $correspondence, -1, -1);
        return $node;
    }
    private function edge(Proposition $source, Proposition $target, string $type = 'variant'): void
    {
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $this->history->id, 'source_proposition_id' => $source->id, 'target_proposition_id' => $target->id, 'type' => $type])->save();
        app(LedgerService::class)->record($edge);
    }
    private function participant(string $callsign): Participant
    {
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $this->substrate->id, 'callsign' => $callsign, 'max_read_count' => 25])->save();
        return $participant;
    }
    private function newPhase(Participant $participant, PhaseKind $kind, string $register, bool $completed = false): Phase
    {
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => (int) $participant->cycles()->max('sequence') + 1, 'completed_at' => $completed ? now() : null])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'write_register' => $register, 'started_at' => now(), 'completed_at' => $completed ? now() : null])->save();
        return $phase;
    }
    private function attest(Proposition $node, string $direction): Attestation
    {
        $phase = $this->newPhase($this->participant('witness-'.Attestation::count()), PhaseKind::Persist, $node->register, true);
        $attestation = new Attestation;
        $attestation->forceFill(['phase_id' => $phase->id, 'proposition_id' => $node->id, 'direction' => $direction])->save();
        app(LedgerService::class)->record($attestation);
        return $attestation;
    }
}
