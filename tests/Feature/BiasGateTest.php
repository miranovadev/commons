<?php

namespace Tests\Feature;

use App\Enums\ObservableDirection;
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
use App\Services\BiasGateService;
use App\Services\ContributionDispatcher;
use App\Services\EvidenceExecutionService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\Observables\ChiralityCalculator;
use App\Services\OpenAIResponseSchema;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipationService;
use App\Services\PropositionMeasurementService;
use App\Services\PropositionStateService;
use App\Services\ReconciliationOptionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\IsolatesClosureOutput;
use Tests\TestCase;

/** Component/read-model contract; Closure output is explicitly isolated, not lifecycle evidence. */
#[Group('component-contract')]
class BiasGateTest extends TestCase
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

    private function fixture(string $register = 'stance', string $write = 'concord'): void
    {
        $this->substrate = new Substrate;
        $this->substrate->forceFill(['slug' => 'bias', 'title' => 'Bias', 'visibility' => 'public', 'configuration' => []])->save();
        $this->history = $this->newPhase($this->participant('author'), PhaseKind::Distinguish, $register === 'stance' ? 'signal' : 'activity', true);
        $this->parent = $this->node('Local provenance', $write, 'committal', position: PhaseKind::Order, freeze: false);
        if ($this->parent->phase->cycle->contextHorizon->opened_by_proposition_id !== null) {
            $this->commitAncestryFixture($this->parent, Proposition::findOrFail($this->parent->phase->cycle->contextHorizon->opened_by_proposition_id));
        }
        $this->freezeCommittedRegionFixture($this->parent);
        $this->history = $this->governedPhaseFixture($this->history, $this->parent);
        $this->attest($this->parent, 'support');
        $this->gate = $this->node('Current Gate', $register, position: PhaseKind::Mediate);
        $this->edge($this->parent, $this->gate);
        $this->viewer = $this->participant('viewer');
        $this->phase = $this->newPhase($this->viewer, PhaseKind::Bias, $write);
        $this->phase->forceFill(['gate_proposition_id' => $this->gate->id])->save();
        $this->viewer->forceFill(['current_proposition_id' => $this->gate->id])->save();
    }

    public function test_zero_attestation_descendants_retain_direction_without_becoming_classification_options(): void
    {
        $this->fixture();
        $chirality = app(ChiralityCalculator::class);

        $this->assertSame(ObservableDirection::Positive, $chirality->directionFor($this->parent));
        $measurement = app(PropositionMeasurementService::class)->project($this->gate, 0, 0, 0);
        $this->assertSame(0.0, (float) $measurement->chirality);
        $this->assertSame(0.0, $chirality->leanFor(collect([$this->gate->id])));
        $this->assertSame(ObservableDirection::Positive, $chirality->directionFor($this->gate));
        $plan = $this->plan();
        $this->assertTrue($plan['rest_available']);
        $this->assertArrayNotHasKey('proposition.reclassify', $plan['schema']['$defs']['actions']);
        $this->assertSame([], array_column($plan['gate_options'], 'id'));

        $descendant = $this->node('Neutral descendant', $this->gate->register, position: PhaseKind::Mediate);
        $this->edge($this->gate, $descendant);
        $this->assertSame(0.0, $chirality->leanFor(collect([$descendant->id])));
        $this->assertSame(ObservableDirection::Positive, $chirality->directionFor($descendant));
        $this->assertSame([], $this->optionIds());

        $flipped = $this->node('Legitimate opposite direction', $this->gate->register, position: PhaseKind::Mediate);
        $this->edge($descendant, $flipped);
        $this->attest($flipped, 'refute');
        $afterFlip = $this->node('Neutral after the flip', $this->gate->register, position: PhaseKind::Mediate);
        $this->edge($flipped, $afterFlip);

        $this->assertSame(ObservableDirection::Negative, $chirality->directionFor($flipped));
        $this->assertSame(0.0, $chirality->leanFor(collect([$afterFlip->id])));
        $this->assertSame(ObservableDirection::Positive, $chirality->directionFor($afterFlip));
        $this->assertSame([], $this->optionIds());
    }

    public function test_receipt_loads_leave_population_lean_and_each_heads_lineage_unchanged(): void
    {
        $this->fixture();
        $support = $this->sibling('Supported B');
        $refute = $this->sibling('Refuted C');
        $this->attest($this->gate, 'support');
        $this->attest($support, 'support');
        $negative = $this->attest($refute, 'refute');
        $service = app(BiasGateService::class);
        $this->assertEqualsWithDelta(1 / 3, $service->leanFor($this->phase), 0.00001);
        $this->assertEqualsCanonicalizing([$this->gate->id, $support->id], $service->forPhase($this->phase)->pluck('id')->all());
        $this->assertSame([$this->gate->id], $this->optionIds());
        // The Settlement projection is distinct from the raw population lean.
        $this->assertNotEquals($service->leanFor($this->phase), (float) $this->gate->fresh()->measurement->chirality);
        $instruments = app(EvidenceExecutionService::class);
        foreach (range(1, 4) as $i) {
            $this->loadedReceiptFixture($refute, $negative);
        }
        $this->assertEqualsWithDelta(1 / 3, $service->leanFor($this->phase), 0.00001); // Receipt uptake does not weight Attestations.
        $this->assertEqualsCanonicalizing([$this->gate->id, $support->id], $service->forPhase($this->phase)->pluck('id')->all()); // Unrelated loads do not set this lineage.
        $replacement = new Attestation;
        $later = $this->newPhase($negative->phase->cycle->participant, PhaseKind::Persist, 'stance', true);
        $replacement->forceFill(['phase_id' => $later->id, 'proposition_id' => $refute->id,
            'direction' => 'support', 'supersedes_attestation_id' => $negative->id])->save();
        app(LedgerService::class)->record($replacement);
        $this->assertEqualsCanonicalizing([$this->gate->id, $support->id, $refute->id], $service->forPhase($this->phase)->pluck('id')->all());
    }

    public static function domains(): array
    {
        return [['stance', 'concord'], ['frontier', 'canon']];
    }

    #[DataProvider('domains')]
    public function test_participant_selects_one_gate_creating_one_fresh_target_and_reducing_the_lean(string $register, string $write): void
    {
        // Isolate structural rules from a new Charge-boundary event.
        $this->resolveExpressiveFieldFixture(.8);
        $this->fixture($register, $write);
        $this->closeVariantMarginFixture($this->phase);
        $selected = $this->sibling("  Selected statement\nexactly as written.  \n");
        $opposite = $this->sibling('Opposite');
        foreach ([$this->gate, $selected] as $source) {
            $this->attest($source, 'support');
        }
        $this->attest($opposite, 'refute');
        $this->observationFixture($this->history, $selected,
            ['kind' => 'hyperlink', 'reference' => 'https://example.test/selected']);
        foreach (range(1, 3) as $i) {
            $touch = $this->newPhase($this->history->cycle->participant, PhaseKind::Persist, $register, true);
            $touch->forceFill(['gate_proposition_id' => $selected->id])->save();
        }
        // Begin on this Gate; a regional calculation does not select it for us.
        $this->phase->forceFill(['gate_proposition_id' => $selected->id])->save();
        $this->phase->unsetRelation('gate');
        $this->viewer->forceFill(['current_proposition_id' => $selected->id])->save();
        $plan = $this->plan();
        $input = $this->reclassificationInputFixture($this->phase, $selected);
        $this->assertSame(['action', 'proposition_id', 'receipt_id'], $plan['schema']['$defs']['actions']['proposition.reclassify']['required']);
        $this->assertSame($this->optionIds(), $plan['schema']['$defs']['actions']['proposition.reclassify']['properties']['proposition_id']['enum']);
        $this->assertArrayNotHasKey('proposition.reconcile', $plan['schema']['$defs']['actions']);
        $this->assertCount(0, app(ReconciliationOptionService::class)->forPhase($this->phase));
        $this->assertInput($plan['schema'], $input, true);
        foreach ([['statement' => 'Replacement'], ['other_proposition_id' => $this->gate->id]] as $extra) {
            $this->assertInput($plan['schema'], [...$input, ...$extra], false);
        }
        $this->assertInput($plan['schema'], ['action' => 'proposition.reconcile', 'other_proposition_id' => $selected->id], false);
        $this->publishPotentialFixture($this->viewer);
        $prompt = app(ParticipantPromptFactory::class)->make(new Connection, app(ParticipationService::class)->stateFor($this->viewer));
        $this->assertSame($plan['schema'], $prompt['schema']);
        $this->assertSame($this->optionIds(), array_column(json_decode($prompt['user'], true)['gate']['propositions'], 'id'));
        $transport = app(OpenAIResponseSchema::class)->fromCommons($prompt['schema']);
        $union = $transport['properties']['contribution'];
        $properties = isset($union['anyOf'])
            ? collect($union['anyOf'])->first(fn ($variant) => $variant['properties']['action']['enum'] === ['proposition.reclassify']) : $union;
        $this->assertArrayNotHasKey('other_proposition_id', $properties['properties']);
        $this->assertArrayNotHasKey('statement', $properties['properties']);
        $wire = array_replace(array_fill_keys($properties['required'], null), $input);
        $this->assertInput($transport, ['contribution' => $wire], true);

        $count = Proposition::count();
        $ledger = $this->substrate->ledgerEntries()->count();
        $this->publishPotentialFixture($this->viewer);
        $state = app(ParticipationService::class)->contribute($this->viewer, $this->phase->id, $input);
        $target = Proposition::findOrFail($this->viewer->fresh()->current_proposition_id);
        $this->assertSame($count + 1, Proposition::count());
        $this->assertSame('order', $state['plan']['phase']['kind']);
        $this->assertSame($selected->statement, $target->statement);
        $this->assertSame($write, $target->register);
        $this->assertSame('expressive', $target->tier);
        $edges = Supersession::where('target_proposition_id', $target->id)->get();
        $this->assertCount(1, $edges);
        $this->assertSame($selected->id, $edges[0]->source_proposition_id);
        $this->assertSame('reclassify', $edges[0]->type);
        $this->assertSame(SupersessionStrength::Hard, SupersessionStrength::for($edges[0]->type));
        $this->assertSame($ledger + 2, $this->substrate->ledgerEntries()->count());
        $history = app(PropositionStateService::class);
        $this->assertSame([$target->id], $history->sourceIds($target)->all());
        $this->assertCount(0, $history->attestations(collect([$target])));
        $this->assertSame([$input['receipt_id']], $history->receipts($target)->pluck('id')->all());
        $this->assertSame(1, $history->touchCount($target)); // Only the newly opened Order Phase.
        $this->assertSame(4, $history->touchCount($selected));
        $this->assertCount(2, $history->receipts($selected));
        $this->assertFalse($state['plan']['commitment']['requirements']['attestation']);
        $this->assertTrue($state['plan']['commitment']['requirements']['receipt']);
        $bias = app(BiasGateService::class);
        $this->assertEqualsCanonicalizing([$this->gate->id, $opposite->id], $bias->populationFor($this->phase)->pluck('id')->all());
        $this->assertSame(0.0, $bias->leanFor($this->phase));
        $this->assertSame([$this->gate->id], $bias->forPhase($this->phase)->pluck('id')->all());
    }

    public function test_inference_keeps_bias_gate_ids_separate_from_write_attestation_ids_and_rejects_instruments(): void
    {
        $this->fixture();
        $this->attest($this->gate, 'support');
        $concord = $this->node('Existing Write Concord', 'concord', position: PhaseKind::Bias);
        $this->edge($this->gate, $concord);
        $this->receiptFixture($concord);
        $schema = $this->plan()['schema'];
        $this->assertArrayNotHasKey('instrument.execute', $schema['$defs']['actions']);
        $this->assertArrayHasKey('attestation.create', $schema['$defs']['actions']);
        $transport = app(OpenAIResponseSchema::class)->fromCommons($schema);
        $variant = $transport['properties']['contribution']['anyOf'][0];
        $empty = array_fill_keys($variant['required'], null);
        foreach ([$this->gate, $concord] as $target) {
            $input = $this->reclassificationInputFixture($this->phase, $target);
            $this->assertInput($schema, $input, $target->is($this->gate));
            $this->assertInput($transport, ['contribution' => array_replace($empty, $input)], $target->is($this->gate));
            $input = ['action' => 'instrument.execute', 'proposition_id' => $target->id,
                'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/concord']];
            $this->assertInput($transport, ['contribution' => array_replace($empty, $input)], false);
            $input = ['action' => 'attestation.create', 'proposition_id' => $target->id, 'direction' => 'support'];
            $this->assertInput($transport, ['contribution' => array_replace($empty, $input)], $target->is($concord));
        }
    }

    public function test_a_single_supported_gate_needs_no_sibling_or_instrument_to_progress(): void
    {
        $this->fixture();
        $this->attest($this->gate, 'support');
        $plan = $this->plan();
        $this->assertSame([$this->gate->id], $this->optionIds());
        $this->assertSame(['proposition.reclassify'], array_keys($plan['schema']['$defs']['actions']));
        $this->publishPotentialFixture($this->viewer);
        $state = app(ParticipationService::class)->contribute($this->viewer, $this->phase->id,
            $this->reclassificationInputFixture($this->phase, $this->gate));
        $this->assertSame('order', $state['plan']['phase']['kind']);
    }

    public function test_zero_aggregate_retains_population_history_for_zero_vote_candidates(): void
    {
        $this->fixture();
        $this->assertSame([], $this->optionIds());
        $positive = $this->sibling('Positive');
        $negative = $this->sibling('Negative');
        $this->attest($positive, 'support');
        $this->attest($negative, 'refute');
        $this->assertContains($positive->id, app(BiasGateService::class)->forPhase($this->phase)->pluck('id'));
        $this->assertSame([], $this->optionIds());
        $this->edge($negative, $this->node('Fresh Concord', 'concord', position: PhaseKind::Bias), 'reclassify');
        $this->assertContains($positive->id, app(BiasGateService::class)->forPhase($this->phase)->pluck('id'));
        $this->assertSame([], $this->optionIds());
    }

    public function test_population_has_deeper_stances_but_excludes_other_registers_and_stops_at_boundaries(): void
    {
        $this->fixture();
        $bridge = $this->node('Bridge', 'signal', position: PhaseKind::Disperse);
        $this->edge($this->parent, $bridge);
        $deep = $this->node('Non-sibling Stance', 'stance', position: PhaseKind::Mediate);
        $this->edge($bridge, $deep);
        $this->attest($deep, 'support');
        $wrongRegister = $this->node('Supported Concord must not enter the Stance field', 'concord', position: PhaseKind::Bias);
        $this->edge($this->parent, $wrongRegister);
        $this->attest($wrongRegister, 'refute');
        $hard = $this->sibling('Hard superseded');
        $this->attest($hard, 'refute');
        $this->edge($hard, $this->node('Hard successor', 'concord', position: PhaseKind::Bias), 'reclassify');
        $boundary = $this->node('Committal boundary', 'concord', 'committal', position: PhaseKind::Bias);
        $this->edge($deep, $boundary); // Soft edge leaves deep active.
        $beyond = $this->node('Beyond boundary', 'stance', position: PhaseKind::Mediate, governor: $boundary);
        $this->edge($boundary, $beyond);
        $this->attest($beyond, 'refute');
        $unrelatedSeed = $this->node('Unrelated region', 'concord', 'committal', position: PhaseKind::Order, freeze: false);
        // A sibling Region shares genuine ancestry without belonging to this Region.
        $this->commitAncestryFixture($unrelatedSeed, $this->parent);
        $this->freezeCommittedRegionFixture($unrelatedSeed);
        $unrelated = $this->node('Unrelated Stance', 'stance', position: PhaseKind::Mediate, governor: $unrelatedSeed);
        $this->edge($unrelatedSeed, $unrelated);
        $this->attest($unrelated, 'refute');
        config()->set('schemas.options.limit', 1);
        $this->viewer->forceFill(['max_read_count' => 1])->save();
        $other = $this->sibling('Another supported Stance');
        $this->attest($other, 'support');
        $this->assertContains($deep->id, app(BiasGateService::class)->forPhase($this->phase)->pluck('id'));
        $this->assertContains($other->id, app(BiasGateService::class)->forPhase($this->phase)->pluck('id'));
        $this->assertSame([], $this->optionIds());
        $this->assertSame(1.0, app(BiasGateService::class)->leanFor($this->phase));
        $this->phase->forceFill(['gate_proposition_id' => $deep->id])->save();
        $this->phase->unsetRelation('gate');
        $target = app(ContributionDispatcher::class)->dispatch($this->viewer, $this->phase,
            $this->reclassificationInputFixture($this->phase, $deep));
        $this->assertSame([$deep->id], $target->parents()->pluck('propositions.id')->all());
    }

    public function test_overlapping_parent_families_are_deduplicated_before_one_aggregate_lean(): void
    {
        $this->fixture();
        $positive = $this->sibling('Shared positive');
        $this->attest($positive, 'support');
        $second = $this->node('Second parent', 'signal', position: PhaseKind::Disperse);
        $this->edge($this->parent, $second);
        $this->edge($second, $this->gate);
        $this->edge($second, $positive);
        $negative = $this->node('Second family negative', 'stance', position: PhaseKind::Mediate);
        $this->edge($second, $negative);
        $this->attest($negative, 'refute');
        $this->assertSame(0.0, app(BiasGateService::class)->leanFor($this->phase));
        $this->assertContains($positive->id, app(BiasGateService::class)->forPhase($this->phase)->pluck('id'));
        $this->assertSame([], $this->optionIds());
        $this->attest($this->gate, 'support');
        $this->assertEqualsCanonicalizing([$positive->id, $this->gate->id], app(BiasGateService::class)->forPhase($this->phase)->pluck('id')->all());
        $this->assertSame([$this->gate->id], $this->optionIds());
    }

    public function test_region_votes_ignore_horizon_labels_but_preserve_substrate_isolation(): void
    {
        $this->fixture();
        $this->attest($this->gate, 'support');
        $wrongHorizon = $this->sibling('Wrong Horizon');
        $this->attest($wrongHorizon, 'refute');
        $horizon = $this->laterHorizonFixture($this->phase->cycle->contextHorizon);
        $wrongHorizon->contextHorizonMembership->forceFill(['context_horizon_id' => $horizon->id])->save();
        $other = $this->substrate->replicate();
        $other->slug = 'other';
        $other->save();
        $outsider = $this->participant('outsider');
        $outsider->forceFill(['substrate_id' => $other->id])->save();
        $foreign = new Proposition;
        $foreign->forceFill(['phase_id' => $this->newPhase($outsider, PhaseKind::Persist, 'stance', true)->id,
            'register' => 'stance', 'tier' => 'expressive', 'statement' => 'Foreign'])->save();
        app(LedgerService::class)->record($foreign);
        // Preserve a malformed legacy edge solely to exercise read-side substrate exclusion.
        (new Supersession)->forceFill(['phase_id' => $foreign->phase_id,
            'source_proposition_id' => $this->parent->id, 'target_proposition_id' => $foreign->id, 'type' => 'variant'])->save();
        $vote = new Attestation;
        $vote->forceFill(['phase_id' => $foreign->phase_id, 'proposition_id' => $foreign->id, 'direction' => 'refute'])->save();
        app(LedgerService::class)->record($vote);
        $this->assertSame([$this->gate->id], $this->optionIds());
        $this->assertSame(0.0, app(BiasGateService::class)->leanFor($this->phase));
    }

    public function test_stale_direction_hard_supersession_and_binary_inputs_are_rejected_before_writes(): void
    {
        $this->fixture();
        $this->attest($this->gate, 'support');
        $input = $this->reclassificationInputFixture($this->phase, $this->gate);
        $this->assertInput($this->plan()['schema'], $input, true);
        $negative = $this->sibling('Negative');
        $this->attest($negative, 'refute');
        $secondNegative = $this->sibling('Second negative');
        $this->attest($secondNegative, 'refute');
        $this->attest($this->gate, 'refute');
        $this->attest($this->gate, 'refute');
        $this->rejectDispatch($input); // The candidate itself changed direction after preview.
        $this->attest($this->gate, 'support');
        $this->rejectDispatch([...$input, 'other_proposition_id' => $negative->id]);
        $this->rejectDispatch([...$input, 'statement' => 'Overwritten']);
        $this->rejectDispatch(['action' => 'proposition.reconcile', 'other_proposition_id' => $negative->id]);
        $this->edge($this->gate, $this->node('Replacement', 'concord', position: PhaseKind::Bias), 'reclassify');
        $this->rejectDispatch($input);
    }

    private function rejectDispatch(array $input): void
    {
        $counts = [Proposition::count(), Supersession::count(), $this->substrate->ledgerEntries()->count()];
        try {
            app(ContributionDispatcher::class)->dispatch($this->viewer, $this->phase, $input);
            $this->fail('Inadmissible Bias must be rejected.');
        } catch (DomainException) {
            $this->assertSame($counts, [Proposition::count(), Supersession::count(), $this->substrate->ledgerEntries()->count()]);
        }
    }

    private function plan(): array
    {
        $this->publishPotentialFixture($this->viewer);

        return app(InputPlanService::class)->forParticipant($this->viewer);
    }

    private function optionIds(): array
    {
        return array_column($this->plan()['gate_options'], 'id');
    }

    private function assertInput(array $schema, array $input, bool $valid): void
    {
        $this->assertSame($valid, (new Validator)->validate(Helper::toJSON($input), Helper::toJSON($schema))->isValid());
    }

    private function sibling(string $statement): Proposition
    {
        $node = $this->node($statement, $this->gate->register, position: PhaseKind::Mediate);
        $this->edge($this->parent, $node);

        return $node;
    }

    private function node(string $statement, string $register, string $tier = 'expressive', PhaseKind $position = PhaseKind::Mediate, ?Proposition $governor = null, bool $freeze = true): Proposition
    {
        $phase = $this->phaseAtPositionFixture($this->history, $position);
        if ($governor !== null) {
            $phase = $this->governedPhaseFixture($phase, $governor);
        }
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'register' => $register, 'tier' => $tier, 'statement' => $statement])->save();
        app(LedgerService::class)->record($node);
        if ($tier === 'committal' && $freeze) {
            $this->freezeRegionFixture($node);
        }

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
        $horizon = $this->horizonForRegisterFixture($participant, $register);
        $cycle->context_horizon_id = $horizon->id;
        $cycle->entry_committal_id = isset($this->parent) && $this->parent->phase->cycle->participant->substrate_id === $participant->substrate_id
            && $this->parent->phase->cycle->context_horizon_id === $horizon->id
                ? $this->parent->id : $horizon->opened_by_proposition_id;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => (int) $participant->cycles()->max('sequence') + 1, 'completed_at' => $completed ? now() : null])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'write_register' => $register, 'started_at' => now(), 'completed_at' => $completed ? now() : null])->save();

        return $phase;
    }

    private function attest(Proposition $node, string $direction): Attestation
    {
        $this->receiptFixture($node);
        $phase = $this->newPhase($this->participant('witness-'.Attestation::count()), PhaseKind::Persist, $node->register, true);
        $attestation = new Attestation;
        $attestation->forceFill(['phase_id' => $phase->id, 'proposition_id' => $node->id, 'direction' => $direction])->save();
        app(LedgerService::class)->record($attestation);
        if ($node->tier === 'expressive') {
            $this->coherentChiralityFixture($node);
        }

        return $attestation;
    }
}
