<?php

namespace Tests\Feature;

use App\Enums\ObservableDirection;
use App\Enums\PhaseKind;
use App\Enums\SupersessionStrength;
use App\Models\{Attestation, Connection, Cycle, Participant, Phase, Proposition, Substrate, Supersession};
use App\Services\{BiasGateService, ContributionDispatcher, InputPlanService, InstrumentExecutionService, LedgerService, OpenAIResponseSchema, OperationalInstrumentService, ParticipantPromptFactory, ParticipationService, PropositionMeasurementService, PropositionStateService, ReconciliationOptionService};
use App\Services\Observables\ChiralityCalculator;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Opis\JsonSchema\{Helper, Validator};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BiasGateTest extends TestCase
{
    use RefreshDatabase;

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
        $this->history = $this->newPhase($this->participant('author'), PhaseKind::Distinguish, 'signal', true);
        $this->parent = $this->node('Local provenance', $write, 'committal');
        $this->attest($this->parent, 'support');
        $this->gate = $this->node('Current Gate', $register);
        $this->edge($this->parent, $this->gate);
        $this->viewer = $this->participant('viewer');
        $this->phase = $this->newPhase($this->viewer, PhaseKind::Bias, $write);
        $this->phase->forceFill(['gate_proposition_id' => $this->gate->id])->save();
        $this->viewer->forceFill(['current_proposition_id' => $this->gate->id])->save();
    }

    public function test_zero_attestation_descendants_retain_direction_and_remain_available_to_bias(): void
    {
        $this->fixture();
        $chirality = app(ChiralityCalculator::class);

        $this->assertSame(ObservableDirection::Positive, $chirality->directionFor($this->parent));
        $measurement = app(PropositionMeasurementService::class)->project($this->gate, 0, 0, 0);
        $this->assertSame(0.0, (float) $measurement->chirality);
        $this->assertSame(0.0, $chirality->leanFor(collect([$this->gate->id]), $this->substrate));
        $this->assertSame(ObservableDirection::Positive, $chirality->directionFor($this->gate));
        $plan = $this->plan();
        $this->assertFalse($plan['tragedy']);
        $this->assertArrayHasKey('proposition.bias', $plan['schema']['$defs']['actions']);
        $this->assertSame([$this->gate->id], array_column($plan['gate_options'], 'id'));

        $descendant = $this->node('Neutral descendant', $this->gate->register);
        $this->edge($this->gate, $descendant);
        $this->assertSame(0.0, $chirality->leanFor(collect([$descendant->id]), $this->substrate));
        $this->assertSame(ObservableDirection::Positive, $chirality->directionFor($descendant));
        $this->assertEqualsCanonicalizing([$this->gate->id, $descendant->id], $this->optionIds());

        $flipped = $this->node('Legitimate opposite direction', $this->gate->register);
        $this->edge($descendant, $flipped);
        $this->attest($flipped, 'refute');
        $afterFlip = $this->node('Neutral after the flip', $this->gate->register);
        $this->edge($flipped, $afterFlip);

        $this->assertSame(ObservableDirection::Negative, $chirality->directionFor($flipped));
        $this->assertSame(0.0, $chirality->leanFor(collect([$afterFlip->id]), $this->substrate));
        $this->assertSame(ObservableDirection::Positive, $chirality->directionFor($afterFlip));
        $this->assertSame([$flipped->id], $this->optionIds());
    }

    public function test_receipt_weighted_effective_lean_sets_one_direction_including_the_current_gate(): void
    {
        $this->fixture();
        $support = $this->sibling('Supported B');
        $refute = $this->sibling('Refuted C');
        $this->attest($this->gate, 'support');
        $this->attest($support, 'support');
        $negative = $this->attest($refute, 'refute');
        $service = app(BiasGateService::class);
        $this->assertEqualsWithDelta(1 / 3, $service->leanFor($this->phase), 0.00001);
        $this->assertEqualsCanonicalizing([$this->gate->id, $support->id], $this->optionIds());
        // Bootstrap projected Chirality is negative, but the raw lean is positive.
        $this->assertLessThan(0, (float) $this->gate->fresh()->measurement->chirality);
        $instruments = app(InstrumentExecutionService::class);
        foreach (range(1, 4) as $i) {
            $this->verifiedReceiptFixture($refute, $negative);
        }
        $this->assertLessThan(0, $service->leanFor($this->phase));
        $this->assertSame([$refute->id], $this->optionIds());
        $replacement = new Attestation;
        $later = $this->newPhase($negative->phase->cycle->participant, PhaseKind::Persist, 'stance', true);
        $replacement->forceFill(['phase_id' => $later->id, 'proposition_id' => $refute->id,
            'direction' => 'support', 'supersedes_attestation_id' => $negative->id])->save();
        app(LedgerService::class)->record($replacement);
        $this->assertEqualsCanonicalizing([$this->gate->id, $support->id, $refute->id], $this->optionIds());
    }

    public static function domains(): array { return [['stance', 'concord'], ['frontier', 'canon']]; }

    #[DataProvider('domains')]
    public function test_participant_selects_one_gate_creating_one_fresh_target_and_reducing_the_lean(string $register, string $write): void
    {
        $this->fixture($register, $write);
        $selected = $this->sibling("  Selected statement\nexactly as written.  \n");
        $opposite = $this->sibling('Opposite');
        foreach ([$this->gate, $selected] as $source) {
            $this->attest($source, 'support');
        }
        $this->attest($opposite, 'refute');
        app(OperationalInstrumentService::class)->execute($this->history, $selected,
            ['kind' => 'hyperlink', 'reference' => 'https://example.test/selected']);
        foreach (range(1, 3) as $i) {
            $touch = $this->newPhase($this->history->cycle->participant, PhaseKind::Persist, $register, true);
            $touch->forceFill(['gate_proposition_id' => $selected->id])->save();
        }
        $plan = $this->plan();
        $input = ['action' => 'proposition.bias', 'proposition_id' => $selected->id];
        $this->assertSame(['action', 'proposition_id'], $plan['schema']['$defs']['actions']['proposition.bias']['required']);
        $this->assertSame($this->optionIds(), $plan['schema']['$defs']['actions']['proposition.bias']['properties']['proposition_id']['enum']);
        $this->assertArrayNotHasKey('proposition.reconcile', $plan['schema']['$defs']['actions']);
        $this->assertCount(0, app(ReconciliationOptionService::class)->forPhase($this->phase));
        $this->assertInput($plan['schema'], $input, true);
        foreach ([['statement' => 'Replacement'], ['other_proposition_id' => $this->gate->id]] as $extra) {
            $this->assertInput($plan['schema'], [...$input, ...$extra], false);
        }
        $this->assertInput($plan['schema'], ['action' => 'proposition.reconcile', 'other_proposition_id' => $selected->id], false);
        $prompt = app(ParticipantPromptFactory::class)->make(new Connection, app(ParticipationService::class)->stateFor($this->viewer));
        $this->assertSame($plan['schema'], $prompt['schema']);
        $this->assertSame($this->optionIds(), array_column(json_decode($prompt['user'], true)['plan']['gate_options'], 'id'));
        $transport = app(OpenAIResponseSchema::class)->fromCommons($prompt['schema']);
        $properties = $transport['properties']['contribution'];
        $this->assertArrayNotHasKey('other_proposition_id', $properties['properties']);
        $this->assertArrayNotHasKey('statement', $properties['properties']);
        $wire = array_replace(array_fill_keys($properties['required'], null), $input);
        $this->assertInput($transport, ['contribution' => $wire], true);

        $count = Proposition::count();
        $ledger = $this->substrate->ledgerEntries()->count();
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
        $this->assertCount(0, $history->receipts($target));
        $this->assertSame(1, $history->touchCount($target)); // Only the newly opened Order Phase.
        $this->assertSame(3, $history->touchCount($selected));
        $this->assertCount(1, $history->receipts($selected));
        $this->assertFalse($state['plan']['commitment']['requirements']['attestation']);
        $this->assertFalse($state['plan']['commitment']['requirements']['receipt']);
        $bias = app(BiasGateService::class);
        $this->assertEqualsCanonicalizing([$this->gate->id, $opposite->id], $bias->populationFor($this->phase)->pluck('id')->all());
        $this->assertSame(0.0, $bias->leanFor($this->phase));
        $this->assertSame([$this->gate->id], $bias->forPhase($this->phase)->pluck('id')->all());
    }

    public function test_inference_keeps_bias_gate_ids_separate_from_write_instrument_and_attestation_ids(): void
    {
        $this->fixture();
        $this->attest($this->gate, 'support');
        $concord = $this->node('Existing Write Concord', 'concord');
        $this->edge($this->gate, $concord);
        $schema = $this->plan()['schema'];
        $this->assertArrayHasKey('instrument.execute', $schema['$defs']['actions']);
        $this->assertArrayHasKey('attestation.create', $schema['$defs']['actions']);
        $transport = app(OpenAIResponseSchema::class)->fromCommons($schema);
        $variant = $transport['properties']['contribution']['anyOf'][0];
        $empty = array_fill_keys($variant['required'], null);
        foreach ([$this->gate, $concord] as $target) {
            $input = ['action' => 'proposition.bias', 'proposition_id' => $target->id];
            $this->assertInput($schema, $input, $target->is($this->gate));
            $this->assertInput($transport, ['contribution' => array_replace($empty, $input)], $target->is($this->gate));
            $input = ['action' => 'instrument.execute', 'proposition_id' => $target->id,
                'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/concord']];
            $this->assertInput($transport, ['contribution' => array_replace($empty, $input)], $target->is($concord));
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
        $this->assertSame(['proposition.bias'], array_keys($plan['schema']['$defs']['actions']));
        $state = app(ParticipationService::class)->contribute($this->viewer, $this->phase->id,
            ['action' => 'proposition.bias', 'proposition_id' => $this->gate->id]);
        $this->assertSame('order', $state['plan']['phase']['kind']);
    }

    public function test_zero_aggregate_retains_population_history_for_zero_vote_candidates(): void
    {
        $this->fixture();
        $this->assertSame([$this->gate->id], $this->optionIds());
        $positive = $this->sibling('Positive');
        $negative = $this->sibling('Negative');
        $this->attest($positive, 'support');
        $this->attest($negative, 'refute');
        $this->assertEqualsCanonicalizing([$this->gate->id, $positive->id], $this->optionIds());
        $this->edge($negative, $this->node('Fresh Concord', 'concord'), 'reclassify');
        $this->assertEqualsCanonicalizing([$this->gate->id, $positive->id], $this->optionIds());
    }

    public function test_population_has_deeper_stances_but_excludes_other_registers_and_stops_at_boundaries(): void
    {
        $this->fixture();
        $bridge = $this->node('Bridge', 'signal');
        $this->edge($this->parent, $bridge);
        $deep = $this->node('Non-sibling Stance', 'stance');
        $this->edge($bridge, $deep);
        $this->attest($deep, 'support');
        $wrongRegister = $this->node('Supported Concord must not enter the Stance field', 'concord');
        $this->edge($this->parent, $wrongRegister);
        $this->attest($wrongRegister, 'refute');
        $hard = $this->sibling('Hard superseded');
        $this->attest($hard, 'refute');
        $this->edge($hard, $this->node('Hard successor', 'concord'), 'reclassify');
        $boundary = $this->node('Committal boundary', 'concord', 'committal');
        $this->edge($deep, $boundary); // Soft edge leaves deep active.
        $beyond = $this->node('Beyond boundary', 'stance');
        $this->edge($boundary, $beyond);
        $this->attest($beyond, 'refute');
        $this->attest($this->node('Unrelated Stance', 'stance'), 'refute');
        config()->set('schemas.options.limit', 1);
        $this->viewer->forceFill(['max_read_count' => 1])->save();
        $other = $this->sibling('Another supported Stance');
        $this->attest($other, 'support');
        $this->assertEqualsCanonicalizing([$this->gate->id, $deep->id, $other->id], $this->optionIds());
        $this->assertSame(1.0, app(BiasGateService::class)->leanFor($this->phase));
        $target = app(ContributionDispatcher::class)->dispatch($this->viewer, $this->phase,
            ['action' => 'proposition.bias', 'proposition_id' => $deep->id]);
        $this->assertSame([$deep->id], $target->parents()->pluck('propositions.id')->all());
    }

    public function test_overlapping_parent_families_are_deduplicated_before_one_aggregate_lean(): void
    {
        $this->fixture();
        $positive = $this->sibling('Shared positive');
        $this->attest($positive, 'support');
        $second = $this->node('Second parent', 'signal');
        $this->edge($this->parent, $second);
        $this->edge($second, $this->gate);
        $this->edge($second, $positive);
        $negative = $this->node('Second family negative', 'stance');
        $this->edge($second, $negative);
        $this->attest($negative, 'refute');
        $this->assertSame(0.0, app(BiasGateService::class)->leanFor($this->phase));
        $this->assertEqualsCanonicalizing([$this->gate->id, $positive->id], $this->optionIds());
        $this->attest($this->gate, 'support');
        $this->assertEqualsCanonicalizing([$positive->id, $this->gate->id], $this->optionIds());
    }

    public function test_region_votes_ignore_horizon_labels_but_preserve_substrate_isolation(): void
    {
        $this->fixture();
        $this->attest($this->gate, 'support');
        $wrongHorizon = $this->sibling('Wrong Horizon');
        $this->attest($wrongHorizon, 'refute');
        $horizon = $this->laterHorizonFixture($wrongHorizon->contextHorizonMembership->horizon);
        $wrongHorizon->contextHorizonMembership->forceFill(['context_horizon_id' => $horizon->id])->save();
        $foreign = $this->sibling('Foreign');
        $this->attest($foreign, 'refute');
        $other = $this->substrate->replicate();
        $other->slug = 'other';
        $other->save();
        $outsider = $this->participant('outsider');
        $outsider->forceFill(['substrate_id' => $other->id])->save();
        $foreign->forceFill(['phase_id' => $this->newPhase($outsider, PhaseKind::Persist, 'stance', true)->id])->save();
        $this->assertSame([$this->gate->id], $this->optionIds());
        $this->assertSame(0.0, app(BiasGateService::class)->leanFor($this->phase));
    }

    public function test_stale_direction_hard_supersession_and_binary_inputs_are_rejected_before_writes(): void
    {
        $this->fixture();
        $this->attest($this->gate, 'support');
        $input = ['action' => 'proposition.bias', 'proposition_id' => $this->gate->id];
        $this->assertInput($this->plan()['schema'], $input, true);
        $negative = $this->sibling('Negative');
        $this->attest($negative, 'refute');
        $secondNegative = $this->sibling('Second negative');
        $this->attest($secondNegative, 'refute');
        $this->rejectDispatch($input); // Former preview no longer authorizes execution.
        $this->attest($this->gate, 'support');
        $this->rejectDispatch([...$input, 'other_proposition_id' => $negative->id]);
        $this->rejectDispatch([...$input, 'statement' => 'Overwritten']);
        $this->rejectDispatch(['action' => 'proposition.reconcile', 'other_proposition_id' => $negative->id]);
        $this->edge($this->gate, $this->node('Replacement', 'concord'), 'reclassify');
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

    private function plan(): array { return app(InputPlanService::class)->forParticipant($this->viewer); }
    private function optionIds(): array { return array_column($this->plan()['gate_options'], 'id'); }
    private function assertInput(array $schema, array $input, bool $valid): void
    {
        $this->assertSame($valid, (new Validator)->validate(Helper::toJSON($input), Helper::toJSON($schema))->isValid());
    }
    private function sibling(string $statement): Proposition
    {
        $node = $this->node($statement, $this->gate->register);
        $this->edge($this->parent, $node);
        return $node;
    }
    private function node(string $statement, string $register, string $tier = 'expressive'): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $this->history->id, 'register' => $register, 'tier' => $tier, 'statement' => $statement])->save();
        app(LedgerService::class)->record($node);
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
