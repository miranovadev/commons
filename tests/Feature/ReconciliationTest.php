<?php

namespace Tests\Feature;

use App\Domain\Observables\CorrespondenceValues;
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
use App\Services\LedgerService;
use App\Services\MediationReadService;
use App\Services\Observables\ReinforcementService;
use App\Services\OpenAIResponseSchema;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipationService;
use App\Services\PhaseProgressionService;
use App\Services\PropositionMeasurementService;
use App\Services\PropositionStateService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\Fixtures\PropositionBirth;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[Group('component-contract')]
class ReconciliationTest extends TestCase
{
    use IsolatesClosureOutput;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolateClosureOutput();
    }

    private Substrate $substrate;

    private Phase $history;

    private Participant $viewer;

    private Phase $phase;

    private Proposition $parent;

    private Proposition $gate;

    private function fixture(float $correspondence = -0.8): void
    {
        $this->substrate = new Substrate;
        $this->substrate->forceFill(['slug' => 'reconcile', 'title' => 'Reconcile', 'visibility' => 'public', 'configuration' => []])->save();
        $this->history = $this->newPhase($this->participant('author'), PhaseKind::Distinguish, 'activity', true);
        $root = $this->node('Shared provenance', 'frontier', tier: 'committal', position: PhaseKind::Mediate);
        $this->freezeRegionFixture($root);
        $this->history = $this->governedPhaseFixture($this->history, $root);
        $this->parent = $this->node('Parent', 'canon', position: PhaseKind::Constrain, source: $root);
        $this->gate = $this->node("  Gate statement\nwith exact whitespace.  \n", 'activity', $correspondence, position: PhaseKind::Disperse, source: $this->parent);
        $this->viewer = $this->participant('viewer');
        $this->viewer->current_proposition_id = $this->gate->id;
        $this->viewer->save();
        $disperse = $this->newPhase($this->viewer, PhaseKind::Disperse, 'activity');
        $disperse->gate_proposition_id = $this->parent->id;
        $disperse->save();
        $this->phase = app(PhaseProgressionService::class)->advance($disperse, $this->gate);
        $this->phase->gate_proposition_id = $this->gate->id;
        $this->phase->save();
        // Phase creation invalidates derived views; install this test's controlled
        // structural comparison after recording the Gate observation.
        app(PropositionMeasurementService::class)->project($this->gate, -$correspondence ?: .8, -1, -1,
            correspondenceComponents: new CorrespondenceValues(-$correspondence ?: .8, -$correspondence ?: .8));
        app(PropositionMeasurementService::class)->project($this->gate, -$correspondence, -1, -1,
            correspondenceComponents: new CorrespondenceValues(-$correspondence, -$correspondence));
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
        [$neutral, $neutralGate] = $this->readSibling('Neutral', 0, 0);
        $local = $this->sibling('Anchor child with neutral Correspondence', 0);
        $plan = $this->plan();
        $this->assertSame($this->parent->id, $plan['read_options'][0]['id']);
        // The zero anchor retains its earlier negative value; the unestablished
        // zero peer inherits negative Correspondence from its lineage.
        $readIds = [$this->parent->id, ($direction > 0 ? $positive : $negative)->id];
        $gateIds = [$local->id, ($direction > 0 ? $positiveGate : $negativeGate)->id];
        if ($direction <= 0) {
            $readIds[] = $neutral->id;
            $gateIds[] = $neutralGate->id;
        }
        $this->assertEqualsCanonicalizing($readIds, app(MediationReadService::class)->readsFor($this->phase)->pluck('id')->all());
        $this->assertSame([$this->parent->id], array_column($plan['read_options'], 'id'));
        $this->assertEqualsCanonicalizing($gateIds, array_column($plan['gate_options'], 'id'));
    }

    public function test_mediate_gate_options_include_reusable_superseded_immediate_children(): void
    {
        $this->fixture();
        $valid = $this->sibling('Valid');
        $otherParent = $this->node('Other parent', 'canon', tier: 'committal', position: PhaseKind::Constrain);
        $unrelated = $this->node('Unrelated active head', 'activity', position: PhaseKind::Disperse, source: $otherParent);
        $child = $this->node('Write child', 'frontier', position: PhaseKind::Mediate, source: $this->gate);
        $grandchild = $this->node('Grandchild', 'activity', position: PhaseKind::Disperse, source: $child);
        $wrongRegister = $this->node('Wrong register sibling', 'frontier', position: PhaseKind::Mediate, source: $this->parent);
        $hard = $this->sibling('Hard superseded');
        $this->node('Replacement', 'frontier', position: PhaseKind::Mediate, source: $hard, type: 'reclassify');
        $soft = $this->sibling('Soft branch remains active');
        $this->node('Soft continuation', 'frontier', position: PhaseKind::Mediate, source: $soft);
        $plan = $this->plan();
        $this->assertEqualsCanonicalizing([$valid->id, $soft->id, $hard->id], array_column($plan['gate_options'], 'id'));
        $this->assertSame([$child->id], array_column($plan['write_options'], 'id'));
        foreach ([$this->gate, $this->parent, $unrelated, $child, $grandchild, $wrongRegister] as $invalid) {
            $this->assertInput($plan['schema'], ['action' => 'proposition.reconcile', 'other_proposition_id' => $invalid->id], false);
        }
    }

    public function test_horizon_membership_does_not_replace_region_scope_and_substrates_stay_isolated(): void
    {
        $this->fixture();
        $valid = $this->sibling('Valid');
        $wrongHorizon = $this->sibling('Other Horizon');
        $horizon = $this->laterHorizonFixture($wrongHorizon->contextHorizonMembership->horizon);
        $wrongHorizon->contextHorizonMembership->forceFill(['context_horizon_id' => $horizon->id])->save();
        $other = $this->substrate->replicate();
        $other->slug = 'foreign';
        $other->save();
        $outsider = $this->participant('outsider');
        $outsider->substrate_id = $other->id;
        $outsider->save();
        $foreignPhase = $this->newPhase($outsider, PhaseKind::Distinguish, 'activity', true);
        $foreign = new Proposition;
        $foreign->forceFill(['phase_id' => $foreignPhase->id, 'register' => 'activity',
            'tier' => 'expressive', 'statement' => 'Foreign substrate'])->save();
        app(LedgerService::class)->record($foreign);
        // Malformed historical edge for substrate-isolation reads; ownership itself stays valid.
        (new Supersession)->forceFill(['phase_id' => $foreignPhase->id, 'source_proposition_id' => $this->parent->id,
            'target_proposition_id' => $foreign->id, 'type' => 'variant'])->save();
        $this->assertSame([$valid->id, $wrongHorizon->id], array_column($this->plan()['gate_options'], 'id'));
    }

    public function test_reconcile_is_structural_selection_with_exact_gate_content(): void
    {
        // Isolate structural rules from a new Charge-boundary event.
        $this->resolveExpressiveFieldFixture(.8);
        $this->fixture();
        $sibling = $this->readSibling('Different Read parent', -0.5, 1)[1];
        $this->attest($sibling, 'support');
        $this->attest($this->gate, 'support');
        foreach ([$this->gate, $sibling] as $source) {
            app(OperationalInstrumentService::class)->execute($this->phase, $source,
                ['kind' => 'hyperlink', 'reference' => 'https://example.test/source/'.$source->id]);
        }
        $plan = $this->plan();
        $input = ['action' => 'proposition.reconcile', 'other_proposition_id' => $sibling->id];
        $this->assertSame([$sibling->id], array_column($plan['gate_options'], 'id'));
        $this->assertSame([], $plan['write_options']);
        $this->assertFalse($plan['rest_available']);
        $this->assertSame(['action', 'other_proposition_id'], $plan['schema']['$defs']['actions']['proposition.reconcile']['required']);
        $this->assertInput($plan['schema'], $input, true);
        $this->assertInput($plan['schema'], [...$input, 'statement' => 'Synthesis'], false);
        $this->publishPotentialFixture($this->viewer);
        $state = app(ParticipationService::class)->stateFor($this->viewer);
        $prompt = app(ParticipantPromptFactory::class)->make(new Connection, $state);
        $this->assertSame($plan['schema'], $prompt['schema']);
        $packet = json_decode($prompt['user'], true);
        $promptReads = $packet['read']['propositions'];
        $this->assertSame([...array_column($state['plan']['read_options'], 'id'), $sibling->id], array_column($promptReads, 'id'));
        $this->assertSame('counterpart', $promptReads[1]['relation']);
        $this->assertSame([$state['plan']['gate_id']], array_column($packet['gate']['propositions'], 'id'));
        $transport = app(OpenAIResponseSchema::class)->fromCommons($prompt['schema']);
        $union = $transport['properties']['contribution'];
        $properties = isset($union['anyOf'])
            ? collect($union['anyOf'])->first(fn ($variant) => $variant['properties']['action']['enum'] === ['proposition.reconcile']) : $union;
        $this->assertArrayNotHasKey('statement', $properties['properties']);
        $wire = array_replace(array_fill_keys($properties['required'], null), $input);
        $this->assertInput($transport, ['contribution' => $wire], true);
        $this->assertInput($transport, ['contribution' => [...$wire, 'statement' => 'Synthesis']], false);
        $count = Proposition::count();
        $ledger = $this->substrate->ledgerEntries()->count();
        $this->publishPotentialFixture($this->viewer);
        app(ParticipationService::class)->contribute($this->viewer, $this->phase->id, $input);
        $created = Proposition::findOrFail($this->viewer->fresh()->current_proposition_id);
        $this->assertSame($count + 1, Proposition::count());
        $this->assertSame($this->gate->statement, $created->statement);
        $this->assertSame('expressive', $created->tier);
        $this->assertSame($this->phase->write_register, $created->register);
        $history = app(PropositionStateService::class);
        $this->assertCount(0, $history->attestations(collect([$created])));
        $this->assertCount(0, $history->receipts($created));
        $this->assertSame([$created->id], $history->sourceIds($created)->all());
        $this->assertSame(1, $history->touchCount($created)); // New Bias observation only.
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
        $this->assertSame([$this->parent->id], array_column($plan['read_options'], 'id'));
        $this->assertEqualsCanonicalizing([$crossGate->id, $first->id, $second->id], array_column($plan['gate_options'], 'id'));
        $this->assertSame(array_column($plan['gate_options'], 'id'), $plan['schema']['$defs']['actions']['proposition.reconcile']['properties']['other_proposition_id']['enum']);
    }

    public function test_dispatcher_rejects_non_sibling_without_partial_writes(): void
    {
        $this->fixture();
        $unrelated = $this->node('Not a sibling', 'activity', position: PhaseKind::Disperse);
        $count = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($this->viewer, $this->phase, ['action' => 'proposition.reconcile', 'other_proposition_id' => $unrelated->id]);
            $this->fail('Invalid counterparts must not be dispatched.');
        } catch (DomainException) {
            $this->assertSame($count, Proposition::count());
        }
    }

    public function test_mediate_reads_cross_branches_and_depths_inside_the_same_region(): void
    {
        $this->fixture();
        [$validRead, $validGate] = $this->readSibling('Matching', -0.7, 1);
        [$opposite, $oppositeGate] = $this->readSibling('Opposite', 0.7, -1);
        [$wrongRegister, $wrongGate] = $this->readSibling('Wrong Read register', -0.7, -1);
        $wrongRegister->register = 'frontier';
        $wrongRegister->save();
        [$hardRead, $hardGate] = $this->readSibling('Replaced Read', -0.7, 1);
        $this->node('Read replacement', 'canon', position: PhaseKind::Constrain, source: $hardRead, type: 'reclassify');
        [$foreignHorizon, $foreignGate] = $this->readSibling('Other Horizon Read', -0.7, 1);
        $horizon = $this->laterHorizonFixture($foreignHorizon->contextHorizonMembership->horizon);
        $foreignHorizon->contextHorizonMembership->forceFill(['context_horizon_id' => $horizon->id])->save();
        $distantRead = $this->node('Read grandchild', 'canon', position: PhaseKind::Constrain, source: $validRead);
        $distantGate = $this->node('Distant Gate', 'activity', position: PhaseKind::Disperse, source: $distantRead);
        $originalHistory = $this->history;
        $unrelatedSeed = $this->node('Unrelated region', 'canon', tier: 'committal');
        $this->freezeRegionFixture($unrelatedSeed);
        $this->history = $this->governedPhaseFixture($this->history, $unrelatedSeed);
        $unrelatedRead = $this->node('Unrelated matching Read', 'canon', position: PhaseKind::Constrain);
        $unrelatedGate = $this->node('Unrelated Gate', 'activity', position: PhaseKind::Disperse, source: $unrelatedRead);
        $this->history = $originalHistory;
        $plan = $this->plan();
        $this->assertEqualsCanonicalizing([$this->parent->id, $validRead->id, $foreignHorizon->id, $distantRead->id, $hardRead->id, $hardRead->children()->where('register', 'canon')->firstOrFail()->id], app(MediationReadService::class)->readsFor($this->phase)->pluck('id')->all());
        $this->assertSame([$this->parent->id], array_column($plan['read_options'], 'id'));
        $this->assertEqualsCanonicalizing([$validGate->id, $foreignGate->id, $distantGate->id, $hardGate->id], array_column($plan['gate_options'], 'id'));
        foreach ([$oppositeGate, $wrongGate, $unrelatedGate] as $invalid) {
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

    public function test_mediate_requires_its_own_disperse_anchor_but_does_not_consume_current_gate(): void
    {
        $this->fixture();
        $this->sibling('Available counterpart');
        $this->assertNotEmpty($this->plan()['gate_options']);
        $this->node('Hard replacement', 'frontier', position: PhaseKind::Mediate, source: $this->gate, type: 'reclassify');
        $this->assertNotEmpty($this->plan()['gate_options']);
        $this->phase->cycle->phases()->where('kind', PhaseKind::Disperse->value)->update(['completed_at' => null]);
        $this->assertCount(0, app(MediationReadService::class)->readsFor($this->phase));
    }

    private function readSibling(string $statement, float $correspondence, float $gateCorrespondence): array
    {
        $read = $this->node($statement, $this->parent->register, $correspondence, position: PhaseKind::Constrain, source: $this->parent->parents()->firstOrFail());
        $gate = $this->node($statement.' child', $this->gate->register, $gateCorrespondence, position: PhaseKind::Disperse, source: $read);

        return [$read, $gate];
    }

    public function test_directed_reconciliation_is_unique_across_participants_but_inverse_and_distinct_pairs_remain_valid(): void
    {
        // Isolate structural rules from a new Charge-boundary event.
        $this->resolveExpressiveFieldFixture(.8);
        $this->fixture();
        $b = $this->sibling('B');
        $c = $this->sibling('C');
        $input = ['action' => 'proposition.reconcile', 'other_proposition_id' => $b->id];
        $this->publishPotentialFixture($this->viewer);
        app(ParticipationService::class)->contribute($this->viewer, $this->phase->id, $input);
        $x = Proposition::findOrFail($this->viewer->fresh()->current_proposition_id);
        $this->receiptFixture($x);
        $this->assertSame(1, app(ReinforcementService::class)->count($x));

        $competitor = $this->participant('competitor');
        $disperse = $this->newPhase($competitor, PhaseKind::Disperse, 'activity');
        $disperse->forceFill(['gate_proposition_id' => $this->parent->id])->save();
        $phase = app(PhaseProgressionService::class)->advance($disperse, $this->gate);
        $plan = app(InputPlanService::class)->forParticipant($competitor);
        $this->assertInput($plan['schema'], $input, false);
        $this->assertSame([$c->id], array_column($plan['gate_options'], 'id'));
        $this->assertContains($x->id, array_column($plan['write_options'], 'id'));
        $this->assertInput($plan['schema'], ['action' => 'attestation.create', 'proposition_id' => $x->id, 'direction' => 'support'], true);
        $count = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($competitor, $phase, $input);
            $this->fail('The same directed pair cannot be regenerated.');
        } catch (DomainException) {
            $this->assertSame($count, Proposition::count());
        }
        // A + C is distinct even though A has already reconciled B.
        $z = app(ContributionDispatcher::class)->dispatch($competitor, $phase,
            ['action' => 'proposition.reconcile', 'other_proposition_id' => $c->id]);
        $this->assertSame($this->gate->statement, $z->statement);

        // Reverse dominance is a distinct directed relationship, including for
        // a Participant who has already authored the forward relationship.
        $this->phase->forceFill(['completed_at' => now()])->save();
        $this->phase->cycle->forceFill(['completed_at' => now()])->save();
        $disperse = $this->newPhase($this->viewer, PhaseKind::Disperse, 'activity');
        $disperse->forceFill(['gate_proposition_id' => $this->parent->id])->save();
        $inverse = app(PhaseProgressionService::class)->advance($disperse, $b);
        $reverse = ['action' => 'proposition.reconcile', 'other_proposition_id' => $this->gate->id];
        $this->assertInput($this->plan()['schema'], $reverse, true);
        $y = app(ContributionDispatcher::class)->dispatch($this->viewer, $inverse, $reverse);
        $this->assertSame($b->statement, $y->statement);
        $this->assertNotSame($x->id, $y->id);
        $this->assertEqualsCanonicalizing($x->parents->pluck('id')->all(), $y->parents->pluck('id')->all());
        $this->assertInput($this->plan()['schema'], $reverse, false);
        $this->loadedReceiptFixture($x);
        $this->assertSame([2, 0, 0], array_map(fn ($node) => app(ReinforcementService::class)->count($node), [$x, $y, $z]));
    }

    private function assertInput(array $schema, array $input, bool $valid): void
    {
        $result = app(Validator::class)->validate(Helper::toJSON($input), Helper::toJSON($schema));
        $this->assertSame($valid, $result->isValid(), json_encode($input));
    }

    private function plan(): array
    {
        return app(InputPlanService::class)->forParticipant($this->viewer);
    }

    private function sibling(string $statement, ?float $correspondence = null): Proposition
    {
        $node = $this->node($statement, $this->gate->register, $correspondence, position: PhaseKind::Disperse, source: $this->parent);

        return $node;
    }

    private function node(string $statement, string $register, ?float $correspondence = null, string $tier = 'expressive', PhaseKind $position = PhaseKind::Disperse, ?Proposition $source = null, string $type = 'variant'): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $this->phaseAtPositionFixture($this->history, $position)->id, 'register' => $register, 'tier' => $tier, 'statement' => $statement])->save();
        app(LedgerService::class)->record($node, $source);
        if ($source !== null) {
            $this->edge($source, $node, $type);
        }
        if ($tier === 'expressive') {
            PropositionBirth::complete($node);
        }
        // Scope tests use compatible default Gates; explicit nonzero scores remain authored.
        $gateDirection = isset($this->gate)
            ? app(PropositionMeasurementService::class)->correspondenceDirectionFor($this->gate)->value : 'positive';
        $gateValue = $gateDirection === 'positive' ? .8 : -.8;
        $correspondence ??= $position === PhaseKind::Disperse ? $gateValue : -.5;
        if ($position === PhaseKind::Disperse && $correspondence === 0.0) {
            app(PropositionMeasurementService::class)->project($node, $gateValue, -1, -1,
                correspondenceComponents: new CorrespondenceValues($gateValue, $gateValue));
        }
        app(PropositionMeasurementService::class)->project($node, $correspondence, -1, -1,
            correspondenceComponents: new CorrespondenceValues($correspondence, $correspondence));

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
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => (int) $participant->cycles()->max('sequence') + 1, 'completed_at' => $completed ? now() : null,
            'entry_committal_id' => isset($this->history) && $participant->substrate_id === $this->substrate->id
                ? $this->history->cycle->entry_committal_id : null])->save();
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
