<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Connection;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\LedgerService;
use App\Services\OpenAIResponseSchema;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TragedyTest extends TestCase
{
    use RefreshDatabase;

    public static function phaseActions(): array
    {
        return [
            'Distinguish Variant without traversal' => [PhaseKind::Distinguish, 'proposition.variant'],
            'Persist Variant without traversal' => [PhaseKind::Persist, 'proposition.variant'],
            'Disperse unconditional Variant' => [PhaseKind::Disperse, 'proposition.variant'],
            'Constrain owned Gate Reclassify' => [PhaseKind::Constrain, 'proposition.reclassify'],
            'Constrain owned descendant Revise' => [PhaseKind::Constrain, 'proposition.revise'],
            'Constrain untouched descendant Attestation' => [PhaseKind::Constrain, 'attestation.create'],
            'Constrain attested descendant Traverse' => [PhaseKind::Constrain, 'proposition.traverse'],
            'Mediate counterpart outside Write' => [PhaseKind::Mediate, 'proposition.reconcile'],
            'Bias Instrument without traversal' => [PhaseKind::Bias, 'instrument.execute'],
            'Constrain exhausted non-owner' => [PhaseKind::Constrain, null],
            'Mediate no counterpart or continuation' => [PhaseKind::Mediate, null],
            'Bias no counterpart instrument or continuation' => [PhaseKind::Bias, null],
            'Order inadmissible Commit with no continuation' => [PhaseKind::Order, null],
        ];
    }

    #[DataProvider('phaseActions')]
    public function test_rest_is_exclusive_to_complete_action_exhaustion(PhaseKind $kind, ?string $action): void
    {
        [$participant, $phase] = $this->fixture($kind, $action);
        $service = app(ParticipationService::class);
        $state = $service->stateFor($participant);
        $plan = $state['plan'];
        $schema = $plan['schema'];
        $actions = array_keys($schema['$defs']['actions']);
        $this->assertSame($action === null, $plan['tragedy']);
        $this->assertSame($actions, array_map(fn ($ref) => basename($ref['$ref']), $schema['oneOf']));
        if ($action === null) {
            $this->assertSame(['participant.rest'], $actions);
        } else {
            $this->assertContains($action, $actions);
            $this->assertNotContains('participant.rest', $actions);
            $this->assertStringNotContainsString('participant.rest', json_encode($schema));
        }
        if ($action !== 'proposition.traverse') {
            $this->assertSame([], $plan['traversal_options']);
        }

        // Autonomous inference receives this exact human plan and action set.
        $prompt = app(ParticipantPromptFactory::class)->make(new Connection, $state);
        $this->assertSame($schema, $prompt['schema']);
        $this->assertSame($plan['tragedy'], json_decode($prompt['user'], true)['plan']['tragedy']);
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
            $after = $service->contribute($participant, $phase->id, $rest);
            $this->assertSame('waiting', $after['status']);
            $this->assertNotNull($phase->fresh()->completed_at);
            $this->assertNotNull($phase->cycle->fresh()->rested_at);
        } else {
            try {
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
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'tragedy', 'title' => 'Tragedy', 'visibility' => 'public', 'configuration' => []])->save();
        $author = $this->participant($substrate, 'author');
        $viewer = $this->participant($substrate, 'viewer');
        $authorPhase = $this->phase($author, PhaseKind::Distinguish, 'activity', true);
        $history = $this->phase($viewer, PhaseKind::Distinguish, 'activity', true);
        $writeRegister = match ($kind) {
            PhaseKind::Distinguish, PhaseKind::Disperse => 'activity',
            PhaseKind::Persist, PhaseKind::Mediate => 'frontier',
            default => 'canon',
        };
        $gate = $this->proposition(
            $action === 'proposition.reclassify' ? $history : $authorPhase,
            $action === 'instrument.execute' ? $writeRegister : 'activity',
        );
        $viewer->current_proposition_id = $gate->id;
        $viewer->save();
        $phase = $this->phase($viewer, $kind, $writeRegister);
        $phase->gate_proposition_id = $gate->id;
        $phase->save();
        if (in_array($action, ['proposition.revise', 'attestation.create', 'proposition.traverse', 'proposition.reconcile'], true)) {
            $target = $this->proposition(
                $action === 'proposition.revise' ? $history : $authorPhase,
                $action === 'proposition.reconcile' ? 'activity' : $writeRegister,
            );
            $edge = new Supersession;
            $edge->forceFill(['phase_id' => $authorPhase->id, 'source_proposition_id' => $gate->id, 'target_proposition_id' => $target->id, 'type' => 'variant'])->save();
            if ($action === 'proposition.traverse') {
                $attestation = new Attestation;
                $attestation->forceFill(['phase_id' => $history->id, 'proposition_id' => $target->id, 'direction' => 'support'])->save();
            }
        }
        return [$viewer, $phase];
    }

    private function participant(Substrate $substrate, string $callsign): Participant
    {
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => $callsign, 'max_read_count' => 25])->save();
        return $participant;
    }

    private function phase(Participant $participant, PhaseKind $kind, string $register, bool $completed = false): Phase
    {
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => (int) $participant->cycles()->max('sequence') + 1, 'completed_at' => $completed ? now() : null])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'write_register' => $register, 'started_at' => now(), 'completed_at' => $completed ? now() : null])->save();
        return $phase;
    }

    private function proposition(Phase $phase, string $register): Proposition
    {
        $proposition = new Proposition;
        $proposition->forceFill(['phase_id' => $phase->id, 'register' => $register, 'tier' => 'expressive', 'statement' => 'Local proposition'])->save();
        app(LedgerService::class)->record($proposition);
        return $proposition;
    }
}
