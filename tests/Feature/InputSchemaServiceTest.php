<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\InputPlanService;
use App\Services\InputSchemaService;
use App\Services\InstrumentExecutionService;
use App\Services\LedgerService;
use App\Services\ParticipantPromptCore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use Tests\TestCase;

class InputSchemaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('schemas.cache.store', 'array');
        Cache::store('array')->clear();
    }

    public function test_mediate_does_not_reuse_the_previous_cached_gate_correspondence_contract(): void
    {
        $actions = config('phases.5.actions');
        $key = 'commons:input-schema:v12:phase:5:'.sha1(implode('|', $actions));
        Cache::store('array')->forever($key, ['stale_gate_correspondence_contract' => true]);
        $schema = app(InputSchemaService::class)->forPhase(PhaseKind::Mediate);

        $this->assertSame(['immediate-child-of-correspondence-read'],
            $schema['x-commons']['constraints']['proposition.reconcile']);
        $this->assertArrayNotHasKey('stale_gate_correspondence_contract', $schema);
    }

    public function test_mediate_retires_cached_regional_guidance_for_dominant_homologous_correspondence(): void
    {
        $actions = config('phases.5.actions');
        $key = 'commons:input-schema:v21:phase:5:'.sha1(implode('|', $actions));
        Cache::store('array')->forever($key, ['stale_regional_guidance' => true]);
        $schema = app(InputSchemaService::class)->forPhase(PhaseKind::Mediate);
        $this->assertArrayNotHasKey('stale_regional_guidance', $schema);
        $this->assertStringContainsString('ordinary local Reconcile path', $schema['x-commons']['description']);
        $this->assertStringContainsString('Dominant Correspondence across homologous Reads', $schema['x-commons']['description']);
        $this->assertStringContainsString('other Regions or Horizons', $schema['x-commons']['description']);

        $this->assertStringNotContainsString('Dominant Correspondence', ParticipantPromptCore::TEXT);

    }

    public function test_bias_retires_the_local_only_cached_guidance(): void
    {
        $actions = config('phases.6.actions');
        $key = 'commons:input-schema:v22:phase:6:'.sha1(implode('|', $actions));
        Cache::store('array')->forever($key, ['stale_local_only_bias' => true]);
        $schema = app(InputSchemaService::class)->forPhase(PhaseKind::Bias);
        $this->assertArrayNotHasKey('stale_local_only_bias', $schema);
        $this->assertStringContainsString('Dominant homologous Chirality', $schema['x-commons']['description']);
        $this->assertStringContainsString('Cycle-entry Receipt catalogue', $schema['x-commons']['description']);
    }

    public function test_bias_retires_binary_reconciliation_schema_and_exposes_unary_selection(): void
    {
        $oldActions = ['proposition.reconcile', 'instrument.execute', 'attestation.create'];
        $key = 'commons:input-schema:v13:phase:6:'.sha1(implode('|', $oldActions));
        Cache::store('array')->forever($key, ['stale_binary_bias' => true]);
        $schema = app(InputSchemaService::class)->forPhase(PhaseKind::Bias);
        $this->assertArrayNotHasKey('stale_binary_bias', $schema);
        $this->assertArrayNotHasKey('proposition.reconcile', $schema['$defs']['actions']);
        $this->assertSame(['action', 'proposition_id', 'receipt_id'], $schema['$defs']['actions']['proposition.reclassify']['required']);
        $this->assertSame(['local-lineage-or-dominant-homologous-chirality', 'unary-selection-fresh-history', 'completed-receipt-and-effective-attestation'],
            $schema['x-commons']['constraints']['proposition.reclassify']);
    }

    public function test_order_retires_implicit_gate_commit_schema(): void
    {
        $actions = config('phases.7.actions');
        $key = 'commons:input-schema:v14:phase:7:'.sha1(implode('|', $actions));
        Cache::store('array')->forever($key, ['stale_implicit_gate' => true]);
        $schema = app(InputSchemaService::class)->forPhase(PhaseKind::Order);
        $this->assertArrayNotHasKey('stale_implicit_gate', $schema);
        $this->assertSame(['action', 'proposition_id'], $schema['$defs']['actions']['proposition.commit']['required']);
    }

    public function test_instrument_guidance_retires_the_previous_cached_receipt_description(): void
    {
        foreach ([PhaseKind::Constrain, PhaseKind::Disperse, PhaseKind::Bias, PhaseKind::Order] as $kind) {
            $actions = config("phases.{$kind->value}.actions");
            $key = "commons:input-schema:v15:phase:{$kind->value}:".sha1(implode('|', $actions));
            Cache::store('array')->forever($key, ['$defs' => ['instrument' => [
                'description' => 'Hyperlink: an HTTP(S) URL reference only; no page fetch or content verification. Both complete synchronously with an unsigned Receipt.',
            ]]]);

            $schema = app(InputSchemaService::class)->forPhase($kind);
            $description = $schema['$defs']['instrument']['description'];
            $this->assertStringContainsString('Creating a Cite Receipt does not itself grant Reinforcement.', $description);
            $this->assertStringContainsString('hyperlink retrieves a public URL', $description);
            $this->assertStringNotContainsString('no page fetch or content verification', $description);
        }
    }

    public function test_each_phase_exposes_its_static_single_move_contract(): void
    {
        $expected = [
            PhaseKind::Distinguish->value => [
                'proposition.variant',
                'instrument.execute',
                'attestation.create',
                'proposition.traverse',
            ],
            PhaseKind::Persist->value => [
                'proposition.variant',
                'proposition.revise',
                'instrument.execute',
                'attestation.create',
                'proposition.traverse',
            ],
            PhaseKind::Constrain->value => [
                'proposition.revise',
                'proposition.reclassify',
                'instrument.execute',
                'attestation.create',
                'proposition.traverse',
            ],
            PhaseKind::Disperse->value => [
                'proposition.variant',
                'proposition.revise',
                'instrument.execute',
                'attestation.create',
                'proposition.traverse',
            ],
            PhaseKind::Mediate->value => [
                'proposition.reconcile',
                'instrument.execute',
                'attestation.create',
            ],
            PhaseKind::Bias->value => [
                'proposition.reclassify',
                'instrument.execute',
                'attestation.create',
                'proposition.traverse',
            ],
            PhaseKind::Order->value => [
                'proposition.commit',
                'proposition.traverse',
            ],
        ];

        foreach (PhaseKind::cases() as $kind) {
            $schema = app(InputSchemaService::class)->forPhase($kind);

            $this->assertSame(
                $expected[$kind->value],
                array_keys($schema['$defs']['actions']),
            );
            $this->assertSame(
                strtolower($kind->name),
                $schema['x-commons']['kind'],
            );
            $this->assertNotEmpty(
                $schema['x-commons']['description'],
            );
            $expectsTraverse = in_array($kind, [PhaseKind::Distinguish, PhaseKind::Persist, PhaseKind::Constrain, PhaseKind::Disperse, PhaseKind::Bias, PhaseKind::Order], true);
            $this->assertSame(
                $expectsTraverse,
                isset($schema['$defs']['actions']['proposition.traverse']),
            );
            if (! $expectsTraverse) {
                $this->assertStringNotContainsString(
                    'traverse',
                    strtolower($schema['x-commons']['description']),
                );
            }
        }
    }

    public function test_prompt_and_documentation_describe_the_traverse_phase_boundary(): void
    {
        $schema = app(InputSchemaService::class)->forPhase(PhaseKind::Distinguish);
        $prompt = ParticipantPromptCore::TEXT;
        $documentation = file_get_contents(base_path('docs/participation.md'))
            .file_get_contents(base_path('docs/operational-commitment.md'));

        $this->assertStringContainsString('Move to an available continuation.', $schema['$defs']['actions']['proposition.traverse']['description']);
        $this->assertStringNotContainsString('proposition.traverse', $prompt);
        $this->assertStringNotContainsString('Current Phase: 5 Mediate', $prompt);
        $this->assertStringNotContainsString('Current Phase: 7 Order', $prompt);
        foreach ([
            'or traverse an existing local Expressive Proposition',
            'or use an admissible local Expressive continuation',
            'At Order it opens another Order occurrence',
        ] as $staleWording) {
            $this->assertStringNotContainsString($staleWording, $documentation);
            $this->assertStringNotContainsString($staleWording, $prompt);
        }
    }

    public function test_constrain_reclassification_rejects_instrument_coupling(): void
    {
        $schema = app(InputSchemaService::class)
            ->forPhase(PhaseKind::Constrain);

        $this->assertValid($schema, [
            'action' => 'proposition.reclassify',
            'proposition_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'receipt_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
        ]);
        $this->assertInvalid($schema, [
            'action' => 'proposition.reclassify',
            'instrument' => [
                'kind' => 'citation',
                'reference' => 'https://example.test/evidence',
            ],
        ]);
        $this->assertValid($schema, [
            'action' => 'attestation.create',
            'proposition_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'direction' => 'support',
        ]);
    }

    public function test_write_descendants_do_not_become_reconciliation_gate_options(): void
    {
        $state = $this->seedPhase(
            PhaseKind::Mediate,
            'frontier',
        );
        $seed = Proposition::findOrFail($state['gate_id']);
        app(LedgerService::class)->record($seed);
        $this->freezeRegionFixture($seed);
        $bootstrap = Phase::findOrFail($state['phase_id']);
        $governed = $this->governedPhaseFixture($bootstrap, $seed, $seed);
        $bootstrap->forceFill(['completed_at' => now()])->save();
        $bootstrap->cycle->forceFill(['completed_at' => now()])->save();
        $state['phase_id'] = $governed->id;
        $candidateId = '01ARZ3NDEKTSV4RRFFQ69G5FAQ';

        DB::table('propositions')->insert([
            'id' => $candidateId,
            'phase_id' => $state['phase_id'],
            'register' => 'frontier',
            'tier' => 'expressive',
            'statement' => 'An admissible active head.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Proposition::query()->findOrFail($state['gate_id'])->children()->attach(
            Proposition::query()->where('phase_id', $state['phase_id'])
                ->whereKeyNot($state['gate_id'])->pluck('id')->mapWithKeys(
                    fn (string $id): array => [$id => [
                        'id' => (string) str()->ulid(), 'phase_id' => $state['phase_id'], 'type' => 'variant',
                    ]],
                )->all(),
        );

        $this->receiptFixture(Proposition::findOrFail($candidateId));
        $plan = app(InputPlanService::class)->forParticipant(
            $state['participant'],
        );

        $this->assertSame('mediate', $plan['phase']['kind']);
        $this->assertSame('frontier', $plan['phase']['write_register']);
        $this->assertNotEmpty($plan['phase']['description']);
        $this->assertSame(
            [$candidateId],
            array_column($plan['write_options'], 'id'),
        );
        $this->assertSame(
            [],
            array_column($plan['gate_options'], 'id'),
        );
        $this->assertSame(
            [$candidateId],
            $plan['schema']['$defs']['actions']['attestation.create']['properties']['proposition_id']['enum'],
        );
        $this->assertArrayNotHasKey('proposition.reconcile', $plan['schema']['$defs']['actions']);
    }

    public function test_lowercase_database_ulids_are_valid_contribution_ids(): void
    {
        $persistSchema = app(InputSchemaService::class)
            ->forPhase(PhaseKind::Persist);

        $this->assertValid($persistSchema, [
            'action' => 'attestation.create',
            'proposition_id' => '01m1xab7yakfnhy6tf52a9qg7z',
            'direction' => 'support',
        ]);
        $this->assertValid($persistSchema, [
            'action' => 'proposition.revise',
            'proposition_id' => '01m1xab7yakfnhy6tf52a9qg7z',
            'statement' => 'A valid lowercase target.',
        ]);
        $this->assertValid(
            app(InputSchemaService::class)->forPhase(PhaseKind::Mediate),
            [
                'action' => 'proposition.reconcile',
                'other_proposition_id' => '01m1xab7yakfnhy6tf52a9qg7z',
            ],
        );
    }

    public function test_write_frontier_does_not_supply_gate_siblings(): void
    {
        config()->set('schemas.options.limit', 10);

        $state = $this->seedPhase(
            PhaseKind::Bias,
            'concord',
        );

        foreach (range(1, 12) as $sequence) {
            DB::table('propositions')->insert([
                'id' => sprintf(
                    '01ARZ3NDEKTSV4RRFFQ69G%04d',
                    $sequence,
                ),
                'phase_id' => $state['phase_id'],
                'register' => 'concord',
                'tier' => 'expressive',
                'statement' => "Candidate {$sequence}.",
                'created_at' => now()->addSeconds($sequence),
                'updated_at' => now()->addSeconds($sequence),
            ]);
        }

        Proposition::query()->findOrFail($state['gate_id'])->children()->attach(
            Proposition::query()->where('phase_id', $state['phase_id'])
                ->whereKeyNot($state['gate_id'])->pluck('id')->mapWithKeys(
                    fn (string $id): array => [$id => [
                        'id' => (string) str()->ulid(), 'phase_id' => $state['phase_id'], 'type' => 'variant',
                    ]],
                )->all(),
        );

        $plan = app(InputPlanService::class)->forParticipant(
            $state['participant'],
        );

        $this->assertCount(12, $plan['write_options']);
        $this->assertSame([], $plan['gate_options']);
    }

    public function test_non_owner_schema_hides_revise_and_reclassify(): void
    {
        $owner = $this->seedPhase(
            PhaseKind::Persist,
            'frontier',
            'owner',
            '01ARZ3NDEKTSV4RRFFQ69G5FAR',
            '01ARZ3NDEKTSV4RRFFQ69G5FAS',
            '01ARZ3NDEKTSV4RRFFQ69G5FAT',
            '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        );
        $participant = $this->seedPhase(
            PhaseKind::Persist,
            'frontier',
            'participant',
            '01ARZ3NDEKTSV4RRFFQ69G5FAW',
            '01ARZ3NDEKTSV4RRFFQ69G5FAX',
            '01ARZ3NDEKTSV4RRFFQ69G5FAY',
            '01ARZ3NDEKTSV4RRFFQ69G5FAZ',
        );

        DB::table('phases')
            ->where('id', $participant['phase_id'])
            ->update(['gate_proposition_id' => $owner['gate_id']]);

        $schema = app(InputSchemaService::class)->forParticipant(
            $participant['participant'],
            [],
            [],
        );

        $this->assertSame(
            ['proposition.variant'],
            array_keys($schema['$defs']['actions']),
        );
    }

    public function test_receipts_record_generation_phase_with_optional_execution_for_tragedy(): void
    {
        $this->assertTrue(Schema::hasTable('instrument_executions'));
        $this->assertTrue(
            Schema::hasColumns('instrument_executions', [
                'phase_id',
                'proposition_id',
                'kind',
                'reference',
                'status',
                'input',
                'metadata',
                'started_at',
                'completed_at',
                'failed_at',
                'failure_reason',
            ]),
        );
        $this->assertTrue(
            Schema::hasColumn('receipts', 'instrument_execution_id'),
        );
        $this->assertTrue(Schema::hasColumn('receipts', 'phase_id'));
        $this->assertTrue(Schema::hasTable('tragedies'));
        $this->assertTrue(Schema::hasTable('receipt_consumptions'));
    }

    public function test_instrument_completion_creates_a_persistent_unconsumed_receipt(): void
    {
        $state = $this->seedPhase(
            PhaseKind::Disperse,
            'activity',
        );
        $proposition = Proposition::query()->findOrFail($state['gate_id']);
        $service = app(InstrumentExecutionService::class);

        $execution = $service->start(
            $proposition->phase,
            $proposition,
            'citation',
            'https://example.test/evidence',
        );

        $this->assertSame('pending', $execution->status);
        $this->assertNull($execution->receipt);

        $receipt = $service->complete(
            $execution,
            $proposition,
            null,
            ['observed' => true],
        );

        $this->assertSame(
            $execution->getKey(),
            $receipt->instrument_execution_id,
        );
        $this->assertSame($proposition->getKey(), $receipt->receiptable_id);
        $this->assertNull($receipt->signature);
        $this->assertSame(
            'completed',
            $execution->fresh()->status,
        );
    }

    /**
     * @return array{
     *     participant: Participant,
     *     phase_id: string,
     *     gate_id: string
     * }
     */
    private function seedPhase(
        PhaseKind $kind,
        ?string $writeRegister,
        string $callsign = 'participant',
        string $participantId = '01ARZ3NDEKTSV4RRFFQ69G5FAA',
        string $cycleId = '01ARZ3NDEKTSV4RRFFQ69G5FAB',
        string $phaseId = '01ARZ3NDEKTSV4RRFFQ69G5FAC',
        string $gateId = '01ARZ3NDEKTSV4RRFFQ69G5FAD',
    ): array {
        $now = now();

        DB::table('substrates')->insertOrIgnore([
            'id' => '01ARZ3NDEKTSV4RRFFQ69G5000',
            'slug' => 'schema-tests',
            'title' => 'Schema tests',
            'visibility' => 'private',
            'configuration' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('participants')->insert([
            'id' => $participantId,
            'substrate_id' => '01ARZ3NDEKTSV4RRFFQ69G5000',
            'callsign' => $callsign,
            'max_read_count' => 25,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $horizon = $this->horizonForRegisterFixture(Participant::findOrFail($participantId), $writeRegister);
        DB::table('cycles')->insert([
            'id' => $cycleId,
            'context_horizon_id' => $horizon->id,
            'participant_id' => $participantId,
            'sequence' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('phases')->insert([
            'id' => $phaseId,
            'cycle_id' => $cycleId,
            'kind' => $kind->value,
            'write_register' => $writeRegister,
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sourcePhase = $kind === PhaseKind::Persist
            ? $this->phaseAtPositionFixture(Phase::findOrFail($phaseId), PhaseKind::Distinguish)
            : Phase::findOrFail($phaseId);
        DB::table('propositions')->insert([
            'id' => $gateId,
            'phase_id' => $sourcePhase->id,
            'register' => $kind === PhaseKind::Persist ? $horizon->domain->registerFor(PhaseKind::Distinguish) : $horizon->domain->registerFor(PhaseKind::Order),
            'tier' => $kind === PhaseKind::Persist ? 'expressive' : 'committal',
            'statement' => 'Current gate.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('phases')
            ->where('id', $phaseId)
            ->update(['gate_proposition_id' => $gateId]);

        return [
            'participant' => Participant::query()->findOrFail($participantId),
            'phase_id' => $phaseId,
            'gate_id' => $gateId,
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $input
     */
    private function assertValid(array $schema, array $input): void
    {
        $validator = new Validator;

        $this->assertTrue(
            $validator->validate(
                Helper::toJSON($input),
                Helper::toJSON($schema),
            )->isValid(),
        );
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $input
     */
    private function assertInvalid(array $schema, array $input): void
    {
        $validator = new Validator;

        $this->assertFalse(
            $validator->validate(
                Helper::toJSON($input),
                Helper::toJSON($schema),
            )->isValid(),
        );
    }
}
