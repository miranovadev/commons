<?php

namespace Tests\Feature;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Phase;
use App\Models\Receipt;
use App\Models\Supersession;
use App\Services\EvidenceExecutionService;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantExplorationService;
use App\Services\ParticipationService;
use App\Services\PropositionOptionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ParticipantExplorationFixture;
use Tests\Support\RefreshEvidenceDatabase;
use Tests\TestCase;

class PersonalJudgmentLifecycleTest extends TestCase
{
    use ParticipantExplorationFixture, RefreshEvidenceDatabase;

    private function observe(Phase $phase, string $tag): Receipt
    {
        return DB::transaction(function () use ($phase, $tag) {
            $url = 'https://example.test/'.$tag;
            $service = app(EvidenceExecutionService::class);
            $execution = $service->start($phase, $phase->gate, 'hyperlink', $url, ['kind' => 'hyperlink', 'reference' => $url]);

            return $service->complete($execution, $phase->gate, verification: new VerifiedHyperlink(
                $url, hash('sha256', $tag), CarbonImmutable::now(), contentExcerpt: $tag));
        });
    }

    private function catalogue(Phase $phase, array $tags): array
    {
        $actor = $phase->cycle->participant;
        $plan = app(InputPlanService::class)->forPhase($actor, $phase);
        $serializer = app(ParticipantContextSerializer::class);
        $state = ['participant' => $actor->only(['id', 'callsign', 'designation']), 'plan' => $serializer->plan($plan)];
        $actual = $serializer->inference($state)['receipts'];
        self::assertSame($actual, $serializer->receiptCognition($state)['receipts']);
        $byId = array_flip(array_map(fn ($r) => $r->id, $tags));

        return collect($actual)->pluck('id')->filter(fn ($id) => isset($byId[$id]))
            ->map(fn ($id) => $byId[$id])->values()->all();
    }

    public function test_three_regions_carry_only_the_previous_committed_line_and_rank_by_distance(): void
    {
        $this->initializeExhaustionFixture(true);
        $a = $this->phase($this->origin, PhaseKind::Distinguish);
        $tags['A governor'] = $this->observe($a, 'a-governor');
        $child = $this->node($a, $this->origin);
        $sibling = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $tags['A sibling'] = $this->observe($this->phase($sibling, PhaseKind::Persist), 'a-sibling');
        $tags['A branch'] = $this->observe($this->phase($child, PhaseKind::Persist), 'a-branch');
        $next = $this->commitFrom($child);
        $b = $this->phase($next, PhaseKind::Distinguish);
        $tags['B governor'] = $this->observe($b, 'b-governor');
        $branch = $this->node($b, $next);
        $atBranch = $this->phase($branch, PhaseKind::Persist);
        $tags['B branch'] = $this->observe($atBranch, 'b-branch');
        $third = $this->commitFrom($branch);
        $c = $this->phase($third, PhaseKind::Distinguish);
        $tags['C governor'] = $this->observe($c, 'c-governor');
        self::assertSame([$this->origin->id, $next->id, $third->id], [$a->governance()->entry_committal_id,
            $b->governance()->entry_committal_id, $c->governance()->entry_committal_id]);
        foreach ([
            'B entry' => [$b, ['B governor', 'A branch', 'A governor']],
            'B Persist' => [$atBranch, ['B branch', 'B governor', 'A branch', 'A governor']],
            'C entry' => [$c, ['C governor', 'B branch', 'B governor']],
        ] as $label => [$phase, $expected]) {
            self::assertSame($expected, $this->catalogue($phase, $tags), $label);
        }
    }

    private function contribute(Phase $phase, array $input): Phase
    {
        $this->publishPotentialFixture($this->substrate);
        $state = app(ParticipationService::class)->contribute($phase->cycle->participant, $phase->id, $input);

        return Phase::findOrFail($state['plan']['phase']['id']);
    }

    private function returnToWrite(Phase $terminal, array $route): Phase
    {
        $this->publishPotentialFixture($this->substrate);
        $phase = $this->returnAfterRest($terminal);
        foreach ($route as $node) {
            $phase = $this->contribute($phase, ['action' => 'proposition.traverse', 'proposition_id' => $node->id]);
        }

        return $phase;
    }

    public function test_sibling_evidence_origin_evidence_and_later_revision_after_consumption(): void
    {
        [$first, $second, $parent, $target, $terminal] = $this->personalJourney();
        $route = [$first, $second, $parent];
        $phase = $this->returnToWrite($terminal, $route);
        $sibling = $this->node($this->phase($parent, PhaseKind::Disperse), $parent);
        $observer = $this->phase($parent, PhaseKind::Disperse);
        $observerArrival = $this->contribute($observer, ['action' => 'attestation.create', 'proposition_id' => $target->id, 'direction' => 'support']);
        self::assertSame($target->id, $observerArrival->gate_proposition_id);
        $luna = $phase->cycle->participant;
        $effective = fn () => Attestation::effective()->where('proposition_id', $target->id)->whereHas('phase.cycle',
            fn ($q) => $q->where('participant_id', $luna->id))->sole();
        $before = $effective();
        $options = app(PropositionOptionService::class);
        $offered = fn () => $options->attestationOptionsFor($phase)->contains('id', $target->id);
        $unchanged = $offered();
        $sib = $this->observe($this->phase($sibling, PhaseKind::Mediate), 'sibling');
        $afterSibling = $offered();
        $root = $this->observe($this->phase($this->origin, PhaseKind::Distinguish), 'origin');
        $afterRoot = $offered();
        self::assertSame($before->id, $effective()->id);
        self::assertTrue($root->ledgerEntry->sequence > $sib->ledgerEntry->sequence && $sib->ledgerEntry->sequence > $before->ledgerEntry->sequence);
        self::assertSame([false, false, true], [$unchanged, $afterSibling, $afterRoot]);
        $arrival = $this->contribute($phase, ['action' => 'attestation.create', 'proposition_id' => $target->id, 'direction' => 'refute']);
        self::assertSame('refute', $effective()->direction);
        self::assertEqualsCanonicalizing(['refute', 'support'], $target->effectiveAttestations()->pluck('direction')->all());
        $choices = app(InputPlanService::class)->forPhase($luna, $arrival)['schema']['$defs']['actions'];
        self::assertArrayHasKey('proposition.reconcile', $choices);
        $arrival = $this->contribute($arrival, ['action' => 'proposition.reconcile', 'other_proposition_id' => $sibling->id,
            'statement' => 'These two expressions develop the same parent in different directions.']);
        $later = $this->returnToWrite($arrival, $route);
        self::assertGreaterThan($phase->cycle->sequence, $later->cycle->sequence);
        $regional = app(HorizonReceiptService::class);
        $eligible = $regional->ordinaryAvailable($later);
        $beforeConsume = $options->revisionOptionsFor($later, $luna)->contains('id', $target->id);
        foreach ($eligible as $receipt) {
            DB::transaction(fn () => $regional->consume($parent->phase, $second, $parent, $receipt->id));
        }
        self::assertEmpty($regional->ordinaryAvailable($later));
        $afterConsume = $options->revisionOptionsFor($later, $luna)->contains('id', $target->id);
        $continuation = $arrival->gate;
        $otherNext = $this->contribute($observerArrival, ['action' => 'attestation.create', 'proposition_id' => $continuation->id, 'direction' => 'support']);
        self::assertSame($continuation->id, $otherNext->gate_proposition_id);
        self::assertTrue($beforeConsume);
        self::assertTrue($afterConsume);
        $born = $this->contribute($later, ['action' => 'proposition.revise', 'proposition_id' => $target->id,
            'statement' => 'Sibling from the refuted evidence.']);
        self::assertFalse($born->gate->effectiveAttestations()->exists());
    }

    public function test_evidence_reopens_reaffirmation_and_reversal_only_at_the_personal_cutoff(): void
    {
        [$first, $second, $parent, $target, $terminal] = $this->personalJourney();
        $phase = $this->returnToWrite($terminal, [$first, $second, $parent]);
        $actor = $phase->cycle->participant;
        $sibling = $this->node($this->phase($parent, PhaseKind::Disperse), $parent);
        $directions = function () use ($actor, $phase, $target): array {
            $this->publishPotentialFixture($this->substrate);
            $choices = app(InputPlanService::class)->forPhase($actor, $phase)['schema']['$defs']['actions']['attestation.create']['anyOf'] ?? [];

            return collect($choices)->filter(fn ($c) => in_array($target->id, $c['properties']['proposition_id']['enum'], true))
                ->flatMap(fn ($c) => $c['properties']['direction']['enum'])->sort()->values()->all();
        };
        $before = $directions();
        $this->observe($this->phase($sibling, PhaseKind::Mediate), 'new-on-other-write');
        $afterSibling = $directions();
        $this->observe($this->phase($this->origin, PhaseKind::Distinguish), 'new-at-governor');
        $afterGovernor = $directions();
        self::assertSame('support', $target->effectiveAttestations()->sole()->direction);
        self::assertSame([[], [], ['refute', 'support']], [$before, $afterSibling, $afterGovernor]);
        $original = $target->effectiveAttestations()->sole();
        $this->contribute($phase, ['action' => 'attestation.create', 'proposition_id' => $target->id, 'direction' => 'support']);
        $reaffirmed = $target->effectiveAttestations()->sole();
        self::assertSame($original->id, $reaffirmed->supersedes_attestation_id);
        self::assertSame('support', $reaffirmed->direction);
        self::assertSame(2, $target->attestations()->count());
        self::assertTrue(app(ParticipantExplorationService::class)->explored($target, $phase));
    }

    public function test_prior_refute_can_revise_a_distinguish_variant_at_its_committal(): void
    {
        [$first, , , , $terminal] = $this->personalJourney(omitJudgment: 1);
        $this->personalVote($first->phase, $first, 'refute');
        $entry = $this->returnToWrite($terminal, []);
        self::assertSame(PhaseKind::Distinguish, $entry->kind);
        $arrival = $this->contribute($entry, ['action' => 'proposition.revise', 'proposition_id' => $first->id,
            'statement' => 'A new Distinguish expression.']);
        $revision = $arrival->gate;
        self::assertSame(PhaseKind::Distinguish, $revision->phase->kind);
        self::assertEqualsCanonicalizing([$first->id, $this->origin->id], $revision->parents()->pluck('propositions.id')->all());
        self::assertFalse(app(ParticipantExplorationService::class)->explored($revision, $arrival));
    }

    public function test_revision_requires_a_personal_refute_of_each_new_expression(): void
    {
        [$a, $b, $parent, $original, $terminal] = $this->personalJourney(headDirection: 'refute');
        $route = [$a, $b, $parent];
        $write = $this->returnToWrite($terminal, $route);
        $arrival = $this->contribute($write, ['action' => 'proposition.revise', 'proposition_id' => $original->id,
            'statement' => 'First alternative expression.']);
        $sibling = $arrival->gate;
        $arrival = $this->contribute($arrival, ['action' => 'proposition.reconcile', 'other_proposition_id' => $original->id,
            'statement' => 'The sibling revisits the original assumption.']);
        $relationship = $arrival->gate;
        $write = $this->returnToWrite($arrival, $route);
        $actor = $write->cycle->participant;
        $options = app(PropositionOptionService::class);
        self::assertFalse($options->revisionOptionsFor($write, $actor)->contains('id', $sibling->id));
        self::assertFalse(app(\App\Services\AcquisitionAttemptService::class)->allows($write, 'instrument.execute'));
        $arrival = $this->contribute($write, ['action' => 'attestation.create', 'proposition_id' => $sibling->id, 'direction' => 'refute']);
        self::assertFalse($options->revisionOptionsFor($write, $actor)->contains('id', $sibling->id));
        $arrival = $this->contribute($arrival, ['action' => 'attestation.create', 'proposition_id' => $relationship->id, 'direction' => 'support']);
        $arrival = $this->contribute($arrival, $this->reclassificationInputFixture($arrival));
        $write = $this->returnToWrite($arrival, $route);
        $next = $this->contribute($write, ['action' => 'proposition.revise', 'proposition_id' => $sibling->id,
            'statement' => 'An alternative to the second expression.'])->gate;
        self::assertFalse($next->effectiveAttestations()->exists());
        self::assertEqualsCanonicalizing([$parent->id, $sibling->id], $next->parents()->pluck('propositions.id')->all());
        self::assertDatabaseHas('phase_profile_observations', ['proposition_id' => $next->id,
            'temporal_predecessor_proposition_id' => $parent->id]);
    }

    public function test_loaded_observations_and_write_statements_across_an_actual_arrival(): void
    {
        [$first, $persist, , , $terminal] = $this->personalJourney();
        $observation = $this->observe($persist->phase, 'child-only-observation');
        self::assertDatabaseHas('participant_receipt_contexts', ['participant_id' => $terminal->cycle->participant_id,
            'receipt_id' => $observation->id, 'cleared_at' => null]);
        $entry = $this->returnToWrite($terminal, []);
        $atOrigin = app(InputPlanService::class)->forPhase($entry->cycle->participant, $entry);
        self::assertContains($first->id, array_column($atOrigin['write_options'], 'id'));
        self::assertSame([], $this->catalogue($entry, ['child' => $observation]));
        $next = $this->contribute($entry, ['action' => 'proposition.traverse', 'proposition_id' => $first->id]);
        self::assertSame(['child'], $this->catalogue($next, ['child' => $observation]));
    }

    public static function siblingBranches(): array
    {
        return ['revised' => ['revised'], 'original' => ['original']];
    }

    #[DataProvider('siblingBranches')]
    public function test_existing_sibling_revision_leaves_original_continuation_reachable(string $branch): void
    {
        [, , $parent, $original, $terminal] = $this->personalJourney();
        $continuation = $this->node($terminal, $original);
        $revisionPhase = $this->phase($parent, PhaseKind::Disperse);
        $revision = $this->node($revisionPhase, $parent);
        $edge = (new Supersession)->forceFill(['phase_id' => $revisionPhase->id,
            'source_proposition_id' => $original->id, 'target_proposition_id' => $revision->id, 'type' => 'revise']);
        $edge->save();
        app(LedgerService::class)->record($edge);
        // The agreed two-parent topology is fixture history. Both encounters below
        // use the real participation schema and transactional submission service.
        $write = $branch === 'original' ? $original : $revision;
        $encounter = $this->phase($parent, PhaseKind::Disperse);
        $options = app(PropositionOptionService::class)->attestationOptionsFor($encounter)->pluck('id');
        self::assertContains($write->id, $options);
        $actor = $encounter->cycle->participant;
        $submit = fn ($at, $node) => $this->contribute($at, ['action' => 'attestation.create', 'proposition_id' => $node->id, 'direction' => 'support']);
        $arrival = $submit($encounter, $write);
        self::assertSame($write->id, $arrival->gate_proposition_id);
        self::assertSame($write->id, app(ParticipationService::class)->stateFor($actor->fresh())['current_proposition_id']);
        if ($branch === 'original') {
            $next = $submit($arrival, $continuation);
            self::assertSame($continuation->id, $next->gate_proposition_id);
        }
    }

    public static function consumptionCases(): array
    {
        return ['unspent control' => [false], 'all consumed' => [true]];
    }

    #[DataProvider('consumptionCases')]
    public function test_revision_dispatch_at_an_otherwise_eligible_head(bool $consume): void
    {
        [$first, $second, $parent, $target, $terminal] = $this->personalJourney(headDirection: 'refute');
        $phase = $this->returnToWrite($terminal, [$first, $second, $parent]);
        $regional = app(HorizonReceiptService::class);
        $receipts = $regional->ordinaryAvailable($phase);
        self::assertNotEmpty($receipts);
        self::assertTrue(app(PropositionOptionService::class)->revisionOptionsFor($phase, $phase->cycle->participant)->contains('id', $target->id));
        if ($consume) {
            foreach ($receipts as $receipt) {
                DB::transaction(fn () => $regional->consume($parent->phase, $second, $parent, $receipt->id));
            }
            self::assertEmpty($regional->ordinaryAvailable($phase));
        }
        $arrival = $this->contribute($phase, ['action' => 'proposition.revise', 'proposition_id' => $target->id,
            'statement' => 'A separate expression grounded in accumulated evidence.']);
        $child = $arrival->gate;
        self::assertFalse($child->effectiveAttestations()->exists());
        $other = $this->phase($parent, PhaseKind::Disperse);
        $exploration = app(ParticipantExplorationService::class);
        $beforeOther = $exploration->explored($child, $other);
        $beforeAuthor = $exploration->explored($child, $arrival);
        $this->contribute($other, ['action' => 'attestation.create', 'proposition_id' => $child->id, 'direction' => 'support']);
        self::assertTrue($exploration->explored($child, $other));
        self::assertSame('refute', $target->effectiveAttestations()->sole()->direction);
        self::assertFalse($beforeOther);
        self::assertFalse($beforeAuthor);
    }
}
