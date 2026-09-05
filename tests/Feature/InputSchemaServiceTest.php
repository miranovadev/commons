<?php

namespace Tests\Feature;

use App\Enums\Phase;
use App\Services\InputSchemaService;
use Illuminate\Support\Facades\Cache;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use Tests\TestCase;

class InputSchemaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('schemas.cache.store', 'array');
        Cache::store('array')->clear();
    }

    public function test_each_phase_exposes_its_single_move_contract(): void
    {
        $expected = [
            Phase::Distinguish->value => [
                'proposition.variant',
                'phase.rest',
            ],
            Phase::Persist->value => [
                'proposition.variant',
                'proposition.revise',
                'proposition.reclassify',
                'attestation.create',
                'phase.rest',
            ],
            Phase::Constrain->value => [
                'proposition.revise',
                'proposition.reclassify_with_receipt',
                'phase.rest',
            ],
            Phase::Disperse->value => [
                'proposition.variant',
                'receipt.create',
                'phase.rest',
            ],
            Phase::Mediate->value => [
                'proposition.reconcile',
                'phase.rest',
            ],
            Phase::Bias->value => [
                'proposition.reconcile',
                'receipt.create',
                'phase.rest',
            ],
            Phase::Order->value => [
                'proposition.commit',
            ],
        ];

        foreach (Phase::cases() as $phase) {
            $schema = app(InputSchemaService::class)->forPhase($phase);

            $this->assertSame(
                $expected[$phase->value],
                array_keys($schema['$defs']['actions']),
            );
            $this->assertTrue($schema['x-commons']['one_action']);
        }
    }

    public function test_distinguish_accepts_a_variant_or_rest_but_not_a_register_choice(): void
    {
        $schema = app(InputSchemaService::class)->forPhase(Phase::Distinguish);

        $this->assertValid($schema, [
            'action' => 'proposition.variant',
            'statement' => 'What if the distinction is relational?',
        ]);
        $this->assertValid($schema, [
            'action' => 'phase.rest',
            'proposition_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        ]);
        $this->assertInvalid($schema, [
            'action' => 'proposition.variant',
            'statement' => 'I should not be able to select the register.',
            'register' => 'canon',
        ]);
    }

    public function test_constrain_reclassification_requires_an_atomic_receipt(): void
    {
        $schema = app(InputSchemaService::class)->forPhase(Phase::Constrain);

        $this->assertInvalid($schema, [
            'action' => 'proposition.reclassify',
            'statement' => 'Move this into the governed committal register.',
        ]);
        $this->assertValid($schema, [
            'action' => 'proposition.reclassify',
            'statement' => 'Move this into the governed committal register.',
            'receipt' => [
                'type' => 'citation',
                'reference' => 'https://example.test/evidence',
            ],
        ]);
    }

    public function test_reconciliation_only_accepts_the_participant_selected_side(): void
    {
        $schema = app(InputSchemaService::class)->forPhase(Phase::Mediate);

        $this->assertValid($schema, [
            'action' => 'proposition.reconcile',
            'other_proposition_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'statement' => 'A mediated expression.',
        ]);
        $this->assertInvalid($schema, [
            'action' => 'proposition.reconcile',
            'source_proposition_ids' => [
                '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                '01ARZ3NDEKTSV4RRFFQ69G5FAW',
            ],
            'statement' => 'The client cannot replace the governed gate.',
        ]);
    }

    public function test_order_commit_operates_on_the_current_gate(): void
    {
        $schema = app(InputSchemaService::class)->forPhase(Phase::Order);

        $this->assertValid($schema, [
            'action' => 'proposition.commit',
        ]);
        $this->assertInvalid($schema, [
            'action' => 'proposition.commit',
            'source_proposition_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        ]);
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
