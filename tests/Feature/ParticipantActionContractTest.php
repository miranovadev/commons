<?php

namespace Tests\Feature;

use App\Domain\Observables\ClosureValues;
use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Connection;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ClosureGateService;
use App\Services\CommitRegionPolarityService;
use App\Services\ContributionDispatcher;
use App\Services\ContributionService;
use App\Services\InputPlanService;
use App\Services\InputSchemaService;
use App\Services\LedgerService;
use App\Services\OpenAIResponseSchema;
use App\Services\OpenAIResponsesClient;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipationService;
use App\Services\PotentialProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class ParticipantActionContractTest extends TestCase
{
    use RefreshDatabase;

    public static function phases(): array
    {
        return array_map(fn (PhaseKind $kind): array => [$kind], PhaseKind::cases());
    }

    public static function executionPhases(): array
    {
        return [...self::phases(), 'Persist foreign Gate' => [PhaseKind::Persist, true]];
    }

    #[Group('integration')]
    #[DataProvider('executionPhases')]
    public function test_every_advertised_action_round_trips_through_inference_validation_and_execution(PhaseKind $kind, bool $foreignGate = false): void
    {
        [$participant, $phase, $seed] = $this->fixture($kind, foreignGate: $foreignGate);
        $state = app(ParticipationService::class)->stateFor($participant);
        $schema = $state['plan']['schema'];
        // Coverage must not silently shrink when an action disappears from a fixture.
        $expected = array_values(array_diff(config('phases.'.$kind->value.'.actions'), ['receipt.replay'])); // Effective observations suppress Replay.
        // The explicitly positioned owned Writes have already spent this
        // participant's generative opportunity. Fresh generation is covered by
        // FullEvidenceFlowTest and SharedReclassificationTest.
        $spent = match ($kind) {
            PhaseKind::Distinguish, PhaseKind::Disperse => ['proposition.variant', 'instrument.execute'],
            PhaseKind::Persist => ['proposition.variant', 'proposition.reclassify'],
            PhaseKind::Constrain, PhaseKind::Bias => [],
            default => [],
        };
        $expected = array_values(array_diff($expected, $spent));
        $this->assertEqualsCanonicalizing($expected, array_keys($schema['$defs']['actions']), json_encode($state['plan']));
        // A valid action in another phase/state must also be rejected here when
        // it is absent from this participant's published contract.
        $checked = [];
        foreach (PhaseKind::cases() as $otherKind) {
            $staticSchema = app(InputSchemaService::class)->forPhase($otherKind);
            foreach ($staticSchema['$defs']['actions'] as $action => $definition) {
                if (isset($schema['$defs']['actions'][$action]) || isset($checked[$action])) {
                    continue;
                }
                $checked[$action] = true;
                $input = $this->sample($definition, $staticSchema['$defs'], $seed->id);
                if ($action === 'instrument.execute') {
                    $input['instrument'] = ['kind' => 'hyperlink', 'reference' => 'https://example.test/forbidden'];
                }
                $this->assertValid($staticSchema, $input);
                $this->assertValid($schema, $input, false);
                $before = $phase->fresh()->getAttributes();
                try {
                    app(ParticipationService::class)->contribute($participant, $phase->id, $input);
                    $this->fail('Backend accepted unavailable action '.$action.' in '.$kind->name);
                } catch (InvalidArgumentException) {
                    $this->addToAssertionCount(1);
                }
                $this->assertSame($before, $phase->fresh()->getAttributes(), $action.' changed the phase');
            }
        }
        $transport = app(OpenAIResponseSchema::class)->fromCommons($schema);
        $variants = $transport['properties']['contribution']['anyOf'] ?? [$transport['properties']['contribution']];
        $responses = Http::sequence();
        Http::fake(['api.openai.test/*' => $responses]);
        Http::preventStrayRequests();
        foreach ($variants as $variant) {
            $wire = $this->sample($variant, $schema['$defs'], $seed->id);
            $action = $wire['action'];
            if ($action === 'instrument.execute') {
                $wire['instrument'] = ['kind' => 'hyperlink', 'reference' => 'https://example.test/contract-new'];
            }
            $contract = $schema['$defs']['actions'][$action];
            $minimal = array_intersect_key($wire, array_flip($contract['required']));
            $this->assertValid($schema, $minimal);
            app(ContributionService::class)->validate($participant, $minimal);
            $this->assertValid($transport, ['contribution' => $wire]);
            $inputs = [$wire];
            if (isset($contract['properties']['rationale'])) {
                $inputs[] = [...$wire, 'rationale' => 'Reasoning for the contribution'];
            }
            if ($action === 'instrument.execute') {
                $inputs[] = [...$wire, 'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/contract']];
            }
            foreach ($inputs as $input) {
                DB::beginTransaction();
                try {
                    $this->assertValid($transport, ['contribution' => $input]);
                    $responses->push(['output' => [['content' => [[
                        'type' => 'output_text', 'text' => json_encode(['contribution' => $input], JSON_THROW_ON_ERROR),
                    ]]]]]);
                    config()->set('services.openai.base_url', 'https://api.openai.test/v1');
                    $connection = new Connection;
                    $connection->forceFill(['credentials' => ['api_key' => 'test-key'], 'configuration' => ['model' => 'test-model']]);
                    $decoded = app(OpenAIResponsesClient::class)->contribute($connection, $state);
                    $this->assertSame(array_filter($input, fn ($value) => $value !== null), $decoded);
                    $this->assertValid($schema, $decoded);
                    app(ContributionService::class)->validate($participant, $decoded);
                    app(ParticipationService::class)->contribute($participant, $phase->id, $decoded);
                    if ($action === 'receipt.read') {
                        $this->assertNull($phase->fresh()->completed_at);
                    } else {
                        $this->assertNotNull($phase->fresh()->completed_at, $action);
                    }
                    if ($action === 'attestation.create') {
                        $attestation = Attestation::where('phase_id', $phase->id)->sole();
                        $this->assertSame($decoded['direction'], $attestation->direction);
                        $this->assertSame($decoded['rationale'] ?? null, $attestation->rationale);
                    }
                    if ($kind === PhaseKind::Order && $action !== 'receipt.read') {
                        $this->assertNotNull($phase->cycle->fresh()->completed_at);
                    }
                } finally {
                    DB::rollBack();
                }
            }
            foreach ($contract['required'] as $field) {
                $invalid = $minimal;
                unset($invalid[$field]);
                $this->assertRejected($participant, $invalid);
                $missing = $wire;
                unset($missing[$field]);
                $this->assertValid($transport, ['contribution' => $missing], false);
                $this->assertValid($transport, ['contribution' => [...$wire, $field => null]], false);
            }
            $this->assertRejected($participant, [...$minimal, 'unexpected' => 'not allowed']);
            $this->assertValid($transport, ['contribution' => [...$wire, 'unexpected' => 'not allowed']], false);
            if (! isset($contract['properties']['rationale'])) {
                $this->assertRejected($participant, [...$minimal, 'rationale' => 'Not an attestation']);
                $this->assertValid($transport, ['contribution' => [...$wire, 'rationale' => 'Not an attestation']], false);
            }
            if ($action === 'instrument.execute') {
                $bad = [...$minimal, 'instrument' => [...$minimal['instrument'], 'rationale' => 'Undeclared nested field']];
                $this->assertRejected($participant, $bad);
                $this->assertValid($transport, ['contribution' => [...$wire, 'instrument' => $bad['instrument']]], false);
            }
        }
    }

    public function test_traverse_with_rationale_reproduces_the_reported_union_error(): void
    {
        [$participant] = $this->fixture(PhaseKind::Distinguish);
        $schema = app(InputPlanService::class)->forParticipant($participant)['schema'];
        $id = $schema['$defs']['actions']['proposition.traverse']['properties']['proposition_id']['enum'][0];
        $input = ['action' => 'proposition.traverse', 'proposition_id' => $id, 'rationale' => 'Follow this path'];
        try {
            app(ContributionService::class)->validate($participant, $input);
            $this->fail('Traverse must reject rationale.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Additional object properties are not allowed: rationale', $exception->getMessage());
            $this->assertStringContainsString('The required properties (direction) are missing', $exception->getMessage());
        }
        $transport = app(OpenAIResponseSchema::class)->fromCommons($schema);
        $variant = $transport['properties']['contribution']['anyOf'][0];
        $wire = array_replace(array_fill_keys($variant['required'], null), $input);
        $this->assertValid($transport, ['contribution' => $wire], false);
    }

    #[DataProvider('phases')]
    public function test_static_phase_transport_requires_attestation_direction(PhaseKind $kind): void
    {
        $schema = app(InputSchemaService::class)->forPhase($kind);
        $transport = app(OpenAIResponseSchema::class)->fromCommons($schema);
        $variants = $transport['properties']['contribution']['anyOf'] ?? [$transport['properties']['contribution']];
        $variant = collect($variants)->first(fn ($branch) => in_array('attestation.create', $branch['properties']['action']['enum'], true));
        if ($kind === PhaseKind::Order) {
            $this->assertNull($variant);
            $this->assertValid($transport, ['contribution' => ['action' => 'attestation.create', 'proposition_id' => (string) str()->ulid(), 'direction' => 'support']], false);

            return;
        }
        $wire = array_replace(array_fill_keys($variant['required'], null),
            $this->sample($schema['$defs']['actions']['attestation.create'], $schema['$defs'], (string) str()->ulid()));
        $this->assertValid($transport, ['contribution' => $wire]);
        $this->assertValid($transport, ['contribution' => [...$wire, 'direction' => null, 'rationale' => 'Not a direction']], false);
    }

    #[Group('integration')]
    public function test_rest_round_trips_and_rejects_rationale(): void
    {
        [$participant, $phase] = $this->fixture(PhaseKind::Mediate, empty: true);
        $schema = app(InputPlanService::class)->forParticipant($participant)['schema'];
        $this->assertSame(['participant.rest'], array_keys($schema['$defs']['actions']));
        $transport = app(OpenAIResponseSchema::class)->fromCommons($schema);
        $input = $this->sample($transport['properties']['contribution'], [], '');
        $this->assertValid($transport, ['contribution' => $input]);
        $this->assertRejected($participant, [...$input, 'rationale' => 'No rationale for Rest']);
        $this->assertValid($transport, ['contribution' => [...$input, 'rationale' => 'No rationale for Rest']], false);
        app(ContributionService::class)->validate($participant, $input);
        app(PotentialProjectionService::class)->rebuild($participant->substrate_id, false);
        app(ParticipationService::class)->contribute($participant, $phase->id, $input);
        $this->assertNotNull($phase->cycle->fresh()->rested_at);
    }

    private function sample(array $schema, array $definitions, string $reference): mixed
    {
        if (isset($schema['$ref'])) {
            $schema = array_replace($definitions[basename($schema['$ref'])], $schema);
        }
        if (isset($schema['anyOf'])) {
            return $this->sample($schema['anyOf'][0], $definitions, $reference);
        }
        if (array_key_exists('const', $schema)) {
            return $schema['const'];
        }
        if (isset($schema['enum'])) {
            return $schema['enum'][0];
        }
        $type = $schema['type'];
        if ($type === 'null' || (is_array($type) && in_array('null', $type, true))) {
            return null;
        }
        if ($type === 'object') {
            $value = [];
            foreach ($schema['required'] as $field) {
                $value[$field] = $field === 'reference' ? $reference : $this->sample($schema['properties'][$field], $definitions, $reference);
            }

            return $value;
        }

        return isset($schema['pattern']) ? $reference : 'Schema contract contribution';
    }

    private function assertValid(array $schema, array $input, bool $expected = true): void
    {
        $this->assertSame($expected, (new Validator)->validate(Helper::toJSON($input), Helper::toJSON($schema))->isValid(), json_encode($input));
    }

    private function assertRejected(Participant $participant, array $input): void
    {
        try {
            app(ContributionService::class)->validate($participant, $input);
            $this->fail('Backend accepted '.json_encode($input));
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }

    private function fixture(PhaseKind $kind, bool $empty = false, bool $foreignGate = false): array
    {
        config()->set('schemas.cache.store', 'array');
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'contracts', 'title' => 'Contracts', 'visibility' => 'public', 'configuration' => []])->save();
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'contracts'])->save();
        $history = new Cycle;
        $history->forceFill(['participant_id' => $participant->id, 'sequence' => 1, 'completed_at' => now()])->save();
        $past = new Phase;
        $past->forceFill(['cycle_id' => $history->id, 'kind' => PhaseKind::Order, 'write_register' => 'activity', 'started_at' => now(), 'completed_at' => now()])->save();
        $node = function (string $register, ?Proposition $parent = null, PhaseKind $position = PhaseKind::Order, string $edgeType = 'variant') use ($past): Proposition {
            $node = new Proposition;
            $node->forceFill(['phase_id' => $this->phaseAtPositionFixture($past, $position)->id, 'register' => $register, 'tier' => $parent ? 'expressive' : 'committal', 'statement' => 'Contract fixture'])->save();
            app(LedgerService::class)->record($node);
            if ($parent) {
                $edge = new Supersession;
                $edge->forceFill(['phase_id' => $past->id, 'source_proposition_id' => $parent->id, 'target_proposition_id' => $node->id, 'type' => $edgeType])->save();
                app(LedgerService::class)->record($edge);
            }

            $this->receiptFixture($node);

            return $node;
        };
        $seed = $node('canon');
        app(CommitRegionPolarityService::class)->establishOrigin($seed, 'support');
        $registers = ['canon', 'activity', 'frontier', 'canon', 'activity', 'frontier', 'canon'];
        $writes = ['activity', 'frontier', 'canon', 'activity', 'frontier', 'canon', 'canon'];
        $anchor = $kind === PhaseKind::Mediate ? $node('canon', $seed, PhaseKind::Constrain) : $seed;
        $gate = $kind === PhaseKind::Distinguish ? $seed : $node($registers[$kind->value - 1], $anchor, PhaseKind::from($kind->value - 1));
        if ($foreignGate) {
            $owner = new Participant;
            $owner->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'gate-owner'])->save();
            $ownerCycle = $history->replicate();
            $ownerCycle->participant_id = $owner->id;
            $ownerCycle->save();
            $ownerPhase = $gate->phase->replicate();
            $ownerPhase->cycle_id = $ownerCycle->id;
            $ownerPhase->save();
            $gate = $gate->replicate();
            $gate->forceFill(['phase_id' => $ownerPhase->id])->save();
            app(LedgerService::class)->record($gate);
            $edge = new Supersession;
            $edge->forceFill(['phase_id' => $ownerPhase->id, 'source_proposition_id' => $anchor->id,
                'target_proposition_id' => $gate->id, 'type' => 'variant'])->save();
            app(LedgerService::class)->record($edge);
        }
        if (! $empty) {
            $this->classificationEvidenceFixture($gate);
            if ($kind === PhaseKind::Mediate) {
                $node('activity', $anchor, PhaseKind::Disperse);
            }
            $write = $node($writes[$kind->value - 1], $gate, $kind === PhaseKind::Order ? PhaseKind::Bias : $kind, $kind === PhaseKind::Order ? 'revise' : 'variant');
            if (in_array('proposition.revise', config('phases.'.$kind->value.'.actions'), true)) {
                // Keep the untouched Write for attestation coverage, and qualify
                // a separate revision target with an earlier effective self-Refute.
                $revision = $node($writes[$kind->value - 1], $gate, $kind === PhaseKind::Order ? PhaseKind::Bias : $kind);
                $refute = new Attestation;
                $refute->forceFill(['phase_id' => $past->id, 'proposition_id' => $revision->id, 'direction' => 'refute'])->save();
                app(LedgerService::class)->record($refute);
            }
            if (in_array($kind, [PhaseKind::Distinguish, PhaseKind::Persist, PhaseKind::Constrain, PhaseKind::Disperse, PhaseKind::Bias], true)) {
                $traverse = $node($writes[$kind->value - 1], $gate, $kind === PhaseKind::Order ? PhaseKind::Bias : $kind);
                $attestation = new Attestation;
                $attestation->forceFill(['phase_id' => $past->id, 'proposition_id' => $traverse->id, 'direction' => 'support'])->save();
                app(LedgerService::class)->record($attestation);
            }
            if (in_array('instrument.execute', config('phases.'.$kind->value.'.actions'), true)) {
                app(OperationalInstrumentService::class)->execute($past, $write, ['kind' => 'hyperlink', 'reference' => 'https://example.test/replay']);
            }
            if ($kind === PhaseKind::Order) {
                $this->groundFixture($write);
                // Contract coverage isolates numeric Closure eligibility; the real
                // reinforcement, region, validator, dispatcher and lifecycle run.
                $this->mock(ClosureGateService::class, function ($mock): void {
                    $values = [...(new ClosureValues(-1, -1))->resolve(), 'history_source' => 'negative-default', 'history_proposition_id' => null];
                    $mock->shouldReceive('preview')->andReturn($values);
                    $mock->shouldReceive('forCommitment')->andReturn($values);
                });
                $committedSource = $node('canon', $gate, PhaseKind::Bias, 'revise');
                $this->groundFixture($committedSource);
                $past->forceFill(['write_register' => 'canon', 'gate_proposition_id' => $committedSource->id])->save();
            }
        }
        if (! $empty) {
            $this->loadedReceiptFixture($gate);
        }
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => 2, 'context_horizon_id' => $history->context_horizon_id])->save();
        if ($kind === PhaseKind::Mediate) {
            $disperse = new Phase;
            $disperse->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Disperse, 'write_register' => 'activity', 'gate_proposition_id' => $anchor->id, 'started_at' => now(), 'completed_at' => now()])->save();
        }
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'write_register' => $writes[$kind->value - 1], 'gate_proposition_id' => $gate->id, 'started_at' => now()])->save();
        if ($kind === PhaseKind::Order && ! $empty) {
            app(ContributionDispatcher::class)->dispatch($participant, $past, ['action' => 'proposition.commit', 'proposition_id' => $committedSource->id]);
        }
        $participant->forceFill(['current_proposition_id' => $gate->id])->save();

        $this->publishPotentialFixture($participant);

        return [$participant, $phase, $seed];
    }
}
