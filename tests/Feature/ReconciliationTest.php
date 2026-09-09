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
        $this->parent = $this->node('Parent', 'canon', tier: 'committal');
        $register = $kind === PhaseKind::Mediate ? 'activity' : 'frontier';
        $this->gate = $this->node("  Gate statement\nwith exact whitespace.  \n", $register, $correspondence);
        $this->edge($this->parent, $this->gate);
        $this->viewer = $this->participant('viewer');
        $this->viewer->current_proposition_id = $this->gate->id;
        $this->viewer->save();
        $this->phase = $this->newPhase($this->viewer, $kind, $kind === PhaseKind::Mediate ? 'frontier' : 'canon');
        $this->phase->gate_proposition_id = $this->gate->id;
        $this->phase->save();
    }

    public static function correspondenceDirections(): array
    {
        return ['consonant' => [0.8], 'dissonant' => [-0.8], 'neutral' => [0.0]];
    }

    #[DataProvider('correspondenceDirections')]
    public function test_mediate_uses_the_gate_lineages_correspondence_direction(float $direction): void
    {
        $this->fixture(correspondence: $direction);
        $positive = $this->sibling('Consonant', 0.5);
        $negative = $this->sibling('Dissonant', -0.5);
        $this->sibling('Neutral', 0);
        $plan = $this->plan();
        $this->assertSame($direction === 0.0 ? [] : [($direction > 0 ? $positive : $negative)->id], array_column($plan['gate_options'], 'id'));
    }

    public function test_only_same_parent_active_siblings_in_gate_context_are_candidates(): void
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
        $sibling = $this->sibling('Different statement from B');
        $this->attest($sibling, 'support');
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

    public function test_gate_limit_is_applied_after_structural_filtering(): void
    {
        $this->fixture(correspondence: 0.8);
        config()->set('schemas.options.limit', 2);
        $this->sibling('Wrong direction first', -0.9);
        $first = $this->sibling('Matching first', 0.5);
        $second = $this->sibling('Matching second', 0.3);
        $this->sibling('Matching beyond limit', 0.1);
        $plan = $this->plan();
        $this->assertSame([$first->id, $second->id], array_column($plan['gate_options'], 'id'));
        $this->assertSame([$first->id, $second->id], $plan['schema']['$defs']['actions']['proposition.reconcile']['properties']['other_proposition_id']['enum']);
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
