<?php

namespace Tests\Feature;

use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContributionDispatcher;
use App\Services\ContributionService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\LineageService;
use App\Services\OpenAIResponseSchema;
use App\Services\PropositionMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InputPlanVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reclassified_proposition_remains_visible_in_its_original_register(): void
    {
        $substrate = $this->substrate();
        $author = $this->participant($substrate, 'author');
        $authorPhase = $this->phase($author, PhaseKind::Persist, 'frontier');

        $origin = $this->proposition(
            $authorPhase,
            'canon',
            'committal',
            'The Origin.',
            -1.0,
        );
        $source = $this->proposition(
            $authorPhase,
            'frontier',
            'expressive',
            'An initial distinction.',
            -0.5,
        );
        $this->supersession($authorPhase, $origin, $source, 'variant');

        $reclassified = $this->proposition(
            $authorPhase,
            'canon',
            'expressive',
            'The distinction, reclassified.',
            0.0,
        );
        $this->supersession(
            $authorPhase,
            $source,
            $reclassified,
            'reclassify',
        );

        $viewer = $this->participant($substrate, 'viewer');
        $this->phase(
            $viewer,
            PhaseKind::Persist,
            'frontier',
            $origin,
        );

        $plan = app(InputPlanService::class)->forWorkspace($viewer);
        $visibleFrontierIds = collect(
            $plan['workspace']['registers']['frontier'],
        )->pluck('id');
        $writeOptionIds = collect($plan['write_options'])->pluck('id');
        $attestationOptionIds = collect(
            $plan['schema']['$defs']['actions']['attestation.create']['properties']['proposition_id']['enum'],
        );

        $this->assertTrue($visibleFrontierIds->contains($source->getKey()));
        $this->assertTrue($writeOptionIds->contains($source->getKey()));
        $this->assertTrue($attestationOptionIds->contains($source->getKey()));

        app(ContributionService::class)->validate($viewer, [
            'action' => 'attestation.create',
            'proposition_id' => $source->getKey(),
            'direction' => 'support',
        ]);

        // A small display window must not remove an otherwise local target
        // from either the schema or the explicit human Attestation options.
        $viewer->max_read_count = 1;
        $viewer->save();
        $limited = app(InputPlanService::class)->forWorkspace($viewer);
        $this->assertContains($source->id, array_column($limited['attestation_options'], 'id'));
        $this->assertContains($source->id, $limited['schema']['$defs']['actions']['attestation.create']['properties']['proposition_id']['enum']);
    }

    public function test_write_is_only_the_immediate_forward_frontier_even_with_horizon_neighbours(): void
    {
        $substrate = $this->substrate();
        $author = $this->participant($substrate, 'author');
        $sourcePhase = $this->phase($author, PhaseKind::Persist, 'frontier');
        $parent = $this->proposition($sourcePhase, 'frontier', 'expressive', 'Parent', 0);
        $gate = $this->proposition($sourcePhase, 'activity', 'expressive', 'Gate', 0);
        $child = $this->proposition($sourcePhase, 'frontier', 'expressive', 'Child', 0);
        $sibling = $this->proposition($sourcePhase, 'frontier', 'expressive', 'Sibling branch', 0);
        $distant = $this->proposition($sourcePhase, 'frontier', 'expressive', 'Grandchild', 0);
        $unrelated = $this->proposition($sourcePhase, 'frontier', 'expressive', 'Unrelated', 0);
        $wrong = $this->proposition($sourcePhase, 'canon', 'expressive', 'Wrong register', 0);
        $committal = $this->proposition($sourcePhase, 'frontier', 'committal', 'Committal child', 0);
        foreach ([[$parent, $gate], [$parent, $sibling], [$gate, $child], [$child, $distant], [$gate, $wrong], [$gate, $committal]] as [$from, $to]) {
            $this->supersession($sourcePhase, $from, $to, 'variant');
        }
        $viewer = $this->participant($substrate, 'viewer');
        $this->phase($viewer, PhaseKind::Persist, 'frontier', $gate);
        $plan = app(InputPlanService::class)->forWorkspace($viewer);
        $this->assertContains($unrelated->id, array_column($plan['workspace']['registers']['frontier'], 'id'));
        $this->assertSame([$child->id], array_column($plan['write_options'], 'id'));
        $this->assertSame([$child->id], array_column($plan['attestation_options'], 'id'));
        $this->assertSame([], $plan['traversal_options']);
        $this->assertContains($parent->id, app(LineageService::class)->neighbourIds($gate));
        foreach ([$parent, $sibling, $distant, $unrelated, $wrong, $committal] as $excluded) {
            foreach (['support', 'refute'] as $direction) {
                $this->assertInput($plan['schema'], [
                    'action' => 'attestation.create', 'proposition_id' => $excluded->id, 'direction' => $direction,
                ], false);
            }
        }
    }

    public static function writeDirections(): array
    {
        $cases = [];
        foreach (PhaseKind::cases() as $kind) {
            foreach ([null, 'support', 'refute'] as $direction) {
                $cases[$kind->name.' '.($direction ?? 'untouched')] = [$kind, $direction];
            }
        }

        return $cases;
    }

    #[DataProvider('writeDirections')]
    public function test_personal_effective_attestation_controls_human_and_inference_choices(PhaseKind $kind, ?string $direction): void
    {
        $substrate = $this->substrate();
        $author = $this->participant($substrate, 'author');
        $writeKind = $kind === PhaseKind::Order ? PhaseKind::Bias : $kind;
        $register = ContextDomain::Emergence->registerFor($kind);
        $sourcePhase = $this->phase($author, $writeKind, $register);
        $gateKind = $kind === PhaseKind::Distinguish ? PhaseKind::Order : PhaseKind::from($kind->value - 1);
        $gatePhase = $this->phaseAtPositionFixture($sourcePhase, $gateKind);
        $seed = $this->proposition($gatePhase, $gatePhase->write_register, 'committal', 'Governing seed', 0);
        $this->freezeRegionFixture($seed);
        if ($kind === PhaseKind::Distinguish) {
            $gate = $seed;
        } else {
            $gatePhase = $this->governedPhaseFixture($gatePhase, $seed, $seed);
            $gate = $this->proposition($gatePhase, $gatePhase->write_register, 'expressive', 'Gate', 0);
            $this->supersession($gatePhase, $seed, $gate, 'variant');
        }
        $sourcePhase = $this->governedPhaseFixture($sourcePhase, $seed, $gate);
        $child = $this->proposition($sourcePhase, $register, 'expressive', 'Child', 0);
        $this->supersession($sourcePhase, $gate, $child, 'variant');
        $untouched = $this->proposition($sourcePhase, $register, 'expressive', 'Untouched neighbour', 0);
        $this->supersession($sourcePhase, $gate, $untouched, 'variant');
        $viewer = $this->participant($substrate, 'viewer');
        $phase = $this->phase($viewer, $kind, $register, $gate);

        // Another participant's evidence must never establish the viewer's path.
        $other = new Attestation;
        $other->forceFill(['phase_id' => $sourcePhase->id, 'proposition_id' => $child->id, 'direction' => 'support'])->save();
        if ($direction !== null) {
            $previousCycle = $phase->cycle->replicate();
            $previousCycle->forceFill(['sequence' => 0, 'completed_at' => now()])->save();
            $previousPhase = $phase->replicate();
            $previousPhase->forceFill(['cycle_id' => $previousCycle->id, 'gate_proposition_id' => null, 'completed_at' => now()])->save();
            $prior = new Attestation;
            $prior->forceFill(['phase_id' => $previousPhase->id, 'proposition_id' => $child->id, 'direction' => $direction])->save();
        }
        $plan = app(InputPlanService::class)->forParticipant($viewer);
        if ($kind === PhaseKind::Order) {
            $this->assertSame([$gate->id], array_column($plan['write_options'], 'id'));
            foreach ([$child, $untouched] as $target) {
                $this->assertInput($plan['schema'], ['action' => 'attestation.create', 'proposition_id' => $target->id, 'direction' => 'support'], false);
                $this->assertInput($plan['schema'], ['action' => 'proposition.traverse', 'proposition_id' => $target->id], false);
            }

            return;
        }
        $this->assertEqualsCanonicalizing([$child->id, $untouched->id], array_column($plan['write_options'], 'id'));
        $traversable = (in_array($kind, [PhaseKind::Distinguish, PhaseKind::Persist, PhaseKind::Constrain, PhaseKind::Disperse, PhaseKind::Bias], true)) && $direction !== null;
        $card = collect($plan['write_options'])->firstWhere('id', $child->id);
        $this->assertSame(['support' => 1 + (int) ($direction === 'support'), 'refute' => (int) ($direction === 'refute')], $card['attestation_counts']);
        $this->assertSame($traversable ? [$child->id] : [], array_column($plan['traversal_options'], 'id'));
        $transport = app(OpenAIResponseSchema::class)->fromCommons($plan['schema']);
        foreach (['refute', 'support'] as $choice) {
            $input = ['action' => 'attestation.create', 'proposition_id' => $child->id, 'direction' => $choice];
            $this->assertInput($plan['schema'], $input, $choice !== $direction);
            $variant = $transport['properties']['contribution']['anyOf'][0];
            $transportInput = array_fill_keys($variant['required'], null);
            $this->assertInput($transport, ['contribution' => array_replace($transportInput, $input)], $choice !== $direction);
        }
        $this->assertInput($plan['schema'], ['action' => 'proposition.traverse', 'proposition_id' => $child->id], $traversable);
        foreach ([$child, $untouched] as $target) {
            $input = ['action' => 'proposition.traverse', 'proposition_id' => $target->id];
            $valid = $target->id === $child->id && $traversable;
            $this->assertInput($plan['schema'], $input, $valid);
            $this->assertInput($transport, ['contribution' => array_replace($transportInput, $input, ['direction' => null])], $valid);
        }
        foreach (['refute', 'support'] as $choice) {
            $input = ['action' => 'attestation.create', 'proposition_id' => $untouched->id, 'direction' => $choice];
            $this->assertInput($plan['schema'], $input, true);
            $this->assertInput($transport, ['contribution' => array_replace($transportInput, $input)], true);
        }
        if ($direction !== null) {
            $count = Proposition::count();
            $edges = Supersession::count();
            app(ContributionDispatcher::class)->dispatch($viewer, $phase, [
                'action' => 'attestation.create', 'proposition_id' => $child->id,
                'direction' => $direction === 'support' ? 'refute' : 'support',
            ]);
            $this->assertSame($count, Proposition::count());
            $this->assertSame($edges, Supersession::count());
            $this->assertDatabaseHas('attestations', ['supersedes_attestation_id' => $prior->id]);
            $updated = app(InputPlanService::class)->forParticipant($viewer);
            $this->assertInput($updated['schema'], [
                'action' => 'attestation.create', 'proposition_id' => $child->id, 'direction' => $direction,
            ], true);
        }
    }

    private function assertInput(array $schema, array $input, bool $valid): void
    {
        $result = app(Validator::class)->validate(
            Helper::toJSON($input), Helper::toJSON($schema),
        );
        $this->assertSame($valid, $result->isValid(), json_encode($input));
    }

    private function substrate(): Substrate
    {
        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => 'visible-reclassified-propositions',
            'title' => 'Visible reclassified propositions',
            'visibility' => 'private',
            'configuration' => [],
        ]);
        $substrate->save();

        return $substrate;
    }

    private function participant(
        Substrate $substrate,
        string $callsign,
    ): Participant {
        $participant = new Participant;
        $participant->forceFill([
            'substrate_id' => $substrate->getKey(),
            'callsign' => $callsign,
            'max_read_count' => 25,
        ]);
        $participant->save();

        return $participant;
    }

    private function phase(
        Participant $participant,
        PhaseKind $kind,
        string $writeRegister,
        ?Proposition $gate = null,
    ): Phase {
        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $participant->getKey(),
            'context_horizon_id' => $gate?->phase->cycle->context_horizon_id
                ?? $this->horizonForRegisterFixture($participant, $writeRegister)->id,
            'sequence' => (int) $participant->cycles()->max('sequence') + 1,
            'governing_committal_id' => $gate?->tier === 'committal' ? $gate->id : $gate?->phase->cycle->governing_committal_id,
        ]);
        $cycle->save();

        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->getKey(),
            'gate_proposition_id' => $gate?->getKey(),
            'kind' => $kind,
            'write_register' => $writeRegister,
            'started_at' => now(),
        ]);
        $phase->save();

        return $phase;
    }

    private function proposition(
        Phase $phase,
        string $register,
        string $tier,
        string $statement,
        float $measurement,
    ): Proposition {
        $proposition = new Proposition;
        $proposition->forceFill([
            'phase_id' => $this->phaseForRegisterFixture($phase, $register)->getKey(),
            'register' => $register,
            'tier' => $tier,
            'statement' => $statement,
        ]);
        $proposition->save();

        app(LedgerService::class)->record($proposition);
        app(PropositionMeasurementService::class)->project(
            $proposition,
            $measurement,
            $measurement,
            $measurement,
        );

        $this->receiptFixture($proposition);

        return $proposition;
    }

    private function supersession(
        Phase $phase,
        Proposition $source,
        Proposition $target,
        string $type,
    ): void {
        $supersession = new Supersession;
        $supersession->forceFill([
            'phase_id' => $phase->getKey(),
            'source_proposition_id' => $source->getKey(),
            'target_proposition_id' => $target->getKey(),
            'type' => $type,
        ]);
        $supersession->save();
        app(LedgerService::class)->record($supersession);
    }
}

