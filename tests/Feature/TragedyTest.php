<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Connection;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContributionDispatcher;
use App\Services\HorizonReceiptService;
use App\Services\OpenAIResponseSchema;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipationService;
use App\Services\PhaseProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\GovernedExpressionFixture;
use Tests\TestCase;

class TragedyTest extends TestCase
{
    use RefreshDatabase;

    public static function phaseActions(): array
    {
        return [
            'Distinguish Variant without traversal' => [PhaseKind::Distinguish, 'proposition.variant'],
            'Persist Variant without traversal' => [PhaseKind::Persist, 'proposition.variant'],
            'Persist Instrument without traversal' => [PhaseKind::Persist, 'instrument.execute'],
            'Disperse first Variant' => [PhaseKind::Disperse, 'proposition.variant'],
            'Constrain owned Gate Reclassify' => [PhaseKind::Constrain, 'proposition.reclassify'],
        ];
    }

    #[DataProvider('phaseActions')]
    public function test_rest_is_exclusive_to_complete_action_exhaustion(PhaseKind $kind, ?string $action): void
    {
        [$participant, $phase] = $this->fixture($kind, $action);
        $service = app(ParticipationService::class);
        $this->publishPotentialFixture($participant);
        $state = $service->stateFor($participant);
        $plan = $state['plan'];
        $schema = $plan['schema'];
        $actions = array_keys($schema['$defs']['actions']);
        $this->assertSame($action === null, $plan['rest_available']);
        $this->assertSame($actions, array_map(fn ($ref) => basename($ref['$ref']), $schema['oneOf']));
        if ($action === null) {
            $this->assertSame(['participant.rest'], $actions);
        } else {
            $this->assertContains($action, $actions);
            $this->assertNotContains('participant.rest', $actions);
            $this->assertStringNotContainsString('participant.rest', json_encode($schema));
        }
        if (! in_array($action, ['proposition.traverse', 'proposition.revise'], true)) {
            $this->assertSame([], $plan['schema']['$defs']['actions']['proposition.traverse']['properties']['proposition_id']['enum'] ?? []);
        }

        // Autonomous inference receives the same action set through the autonomous context.
        $prompt = app(ParticipantPromptFactory::class)->make(new Connection, $state);
        $this->assertSame($schema, $prompt['schema']);
        $this->assertSame($plan['rest_available'], isset(json_decode($prompt['user'], true)['actions']['$defs']['actions']['participant.rest']));
        $transport = app(OpenAIResponseSchema::class)->fromCommons($prompt['schema']);
        $contribution = $transport['properties']['contribution'];
        $variants = $contribution['anyOf'] ?? [$contribution];
        $inferenceActions = array_values(array_unique(array_merge(...array_map(
            fn ($variant) => $variant['properties']['action']['enum'], $variants,
        ))));
        $this->assertEqualsCanonicalizing($actions, $inferenceActions);
        $rest = ['action' => 'participant.rest'];
        $validator = app(Validator::class);
        $this->assertSame($action === null, $validator->validate(Helper::toJSON($rest), Helper::toJSON($schema))->isValid());
        $transportRest = array_replace(array_fill_keys($variants[0]['required'], null), $rest);
        $this->assertSame($action === null, $validator->validate(
            Helper::toJSON(['contribution' => $transportRest]), Helper::toJSON($transport),
        )->isValid());

        $propositions = Proposition::count();
        $supersessions = Supersession::count();
        if ($action === null) {
            $this->publishPotentialFixture($participant);
            $after = $service->contribute($participant, $phase->id, $rest);
            $this->assertSame('waiting', $after['status']);
            $this->assertNotNull($phase->fresh()->completed_at);
            $this->assertNotNull($phase->cycle->fresh()->rested_at);
        } else {
            try {
                $this->publishPotentialFixture($participant);
                $service->contribute($participant, $phase->id, $rest);
                $this->fail('Rest cannot compete with an admissible action.');
            } catch (InvalidArgumentException) {
                $this->assertNull($phase->fresh()->completed_at);
                $this->assertNull($phase->cycle->fresh()->rested_at);
            }
        }
        $this->assertSame($phase->gate_proposition_id, $participant->fresh()->current_proposition_id);
        $this->assertSame($propositions, Proposition::count());
        $this->assertSame($supersessions, Supersession::count());
    }

    private function fixture(PhaseKind $kind, ?string $action): array
    {
        if ($kind === PhaseKind::Persist && $action === 'instrument.execute') {
            $fixture = new GovernedExpressionFixture;
            $fixture->phase = app(PhaseProgressionService::class)->advance($fixture->phase, $fixture->expression);

            return [$fixture->actor->fresh(), $fixture->phase];
        }
        if (($kind === PhaseKind::Constrain && $action === 'proposition.reclassify')
            || ($kind === PhaseKind::Disperse && $action === 'proposition.variant')) {
            $fixture = new GovernedExpressionFixture;
            $fixture->advanceToConstrain();
            if ($kind === PhaseKind::Disperse) {
                $receipt = app(HorizonReceiptService::class)->ordinaryAvailable($fixture->phase)->firstOrFail();
                $result = app(ContributionDispatcher::class)->dispatch($fixture->actor, $fixture->phase,
                    ['action' => 'proposition.reclassify', 'proposition_id' => $fixture->expression->id, 'receipt_id' => $receipt->id]);
                $fixture->phase = app(PhaseProgressionService::class)->advance($fixture->phase, $result);
                // This case isolates action exhaustion with shared ordinary evidence available.
                $this->instrumentGateFixture($fixture->phase);
            }

            return [$fixture->actor->fresh(), $fixture->phase];
        }
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'rest_available', 'title' => 'Tragedy', 'visibility' => 'public', 'configuration' => []])->save();
        $viewer = $this->participant($substrate, 'viewer');
        // These two action-exhaustion cases need only real bootstrap/development,
        // not an ordinary Committal manufactured by changing an expressive tier.
        if ($action === 'proposition.variant' && in_array($kind, [PhaseKind::Distinguish, PhaseKind::Persist], true)) {
            $service = app(ParticipationService::class);
            $state = $service->enter($viewer, ['statement' => 'Origin', 'attestation' => ['direction' => 'support']]);
            $state = $service->contribute($viewer, $state['plan']['phase']['id'],
                ['action' => 'instrument.execute', 'proposition_id' => $state['plan']['gate_id'],
                    'instrument' => ['kind' => 'proposition_search', 'limit' => 1]]);
            if ($kind === PhaseKind::Persist) {
                $state = $service->contribute($viewer, $state['plan']['phase']['id'],
                    ['action' => 'proposition.variant', 'statement' => 'First distinction']);
                $state = $service->contribute($viewer, $state['plan']['phase']['id'],
                    ['action' => 'instrument.execute', 'proposition_id' => $state['plan']['gate_id'],
                        'instrument' => ['kind' => 'citation', 'reference' => Phase::findOrFail($state['plan']['phase']['id'])->cycle->entry_committal_id]]);
            }

            return [$viewer->fresh(), Phase::findOrFail($state['plan']['phase']['id'])];
        }
        throw new \LogicException('No authorised scenario for this action.');
    }

    private function participant(Substrate $substrate, string $callsign): Participant
    {
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => $callsign, 'max_read_count' => 25])->save();

        return $participant;
    }
}
