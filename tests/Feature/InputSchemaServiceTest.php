<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Participant;
use App\Models\Proposition;
use App\Services\InputPlanService;
use App\Services\InstrumentExecutionService;
use App\Services\InputSchemaService;
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

    public function test_each_phase_exposes_its_static_single_move_contract(): void
    {
        $expected = [
            PhaseKind::Distinguish->value => [
                'proposition.variant',
                'proposition.select',
            ],
            PhaseKind::Persist->value => [
                'proposition.variant',
                'proposition.revise',
                'proposition.reclassify',
                'attestation.create',
                'proposition.select',
            ],
            PhaseKind::Constrain->value => [
                'proposition.revise',
                'proposition.reclassify_with_instrument',
                'attestation.create',
                'proposition.select',
            ],
            PhaseKind::Disperse->value => [
                'proposition.variant',
                'instrument.execute',
                'proposition.select',
            ],
            PhaseKind::Mediate->value => [
                'proposition.reconcile',
                'proposition.select',
            ],
            PhaseKind::Bias->value => [
                'proposition.reconcile',
                'instrument.execute',
                'proposition.select',
            ],
            PhaseKind::Order->value => [
                'proposition.commit',
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
        }
    }

    public function test_constrain_reclassification_executes_an_instrument(): void
    {
        $schema = app(InputSchemaService::class)
            ->forPhase(PhaseKind::Constrain);

        $this->assertInvalid($schema, [
            'action' => 'proposition.reclassify',
            'statement' => 'Move this into the committal register.',
        ]);
        $this->assertValid($schema, [
            'action' => 'proposition.reclassify',
            'statement' => 'Move this into the committal register.',
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

    public function test_input_plan_specializes_selection_and_gate_options(): void
    {
        $state = $this->seedPhase(
            PhaseKind::Mediate,
            'frontier',
        );
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
            [$candidateId],
            array_column($plan['gate_options'], 'id'),
        );
        $this->assertSame(
            [$candidateId],
            $plan['schema']['$defs']['actions']['proposition.select']
                ['properties']['proposition_id']['enum'],
        );
        $this->assertSame(
            [$candidateId],
            $plan['schema']['$defs']['actions']['proposition.reconcile']
                ['properties']['other_proposition_id']['enum'],
        );
    }

    public function test_write_and_gate_options_are_limited_to_ten(): void
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

        $plan = app(InputPlanService::class)->forParticipant(
            $state['participant'],
        );

        $this->assertCount(10, $plan['write_options']);
        $this->assertCount(10, $plan['gate_options']);
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

    public function test_receipts_require_an_instrument_execution(): void
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
        $this->assertFalse(Schema::hasColumn('receipts', 'phase_id'));
    }

    public function test_instrument_completion_can_create_an_unsigned_attached_receipt(): void
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
            'sha256:test-signature',
            ['observed' => true],
        );

        $this->assertSame(
            $execution->getKey(),
            $receipt->instrument_execution_id,
        );
        $this->assertSame($proposition->getKey(), $receipt->receiptable_id);
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
    ): array
    {
        $now = now();

        DB::table('participants')->insert([
            'id' => $participantId,
            'callsign' => $callsign,
            'max_read_count' => 25,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('cycles')->insert([
            'id' => $cycleId,
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
        DB::table('propositions')->insert([
            'id' => $gateId,
            'phase_id' => $phaseId,
            'register' => 'canon',
            'tier' => 'committal',
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
