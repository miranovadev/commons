<?php

namespace Tests\Feature;

use App\Enums\Phase;
use App\Services\InputSchemaService;
use DomainException;
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

    public function test_distinguish_exposes_only_variant_propositions(): void
    {
        $schema = app(InputSchemaService::class)->forPhase(Phase::Distinguish);

        $this->assertSame(
            [['$ref' => '#/$defs/actions/proposition.variant']],
            $schema['oneOf'],
        );
        $this->assertSame(
            ['proposition.variant'],
            array_keys($schema['$defs']['actions']),
        );

        $validator = new Validator;

        $variant = $validator->validate(
            Helper::toJSON([
                'action' => 'proposition.variant',
                'source_proposition_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                'statement' => 'What if the distinction is relational?',
            ]),
            Helper::toJSON($schema),
        );

        $attestation = $validator->validate(
            Helper::toJSON([
                'action' => 'attestation.create',
                'proposition_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                'direction' => 'support',
            ]),
            Helper::toJSON($schema),
        );

        $this->assertTrue($variant->isValid());
        $this->assertFalse($attestation->isValid());
    }

    public function test_unconfigured_phases_do_not_expose_a_permissive_schema(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Inputs for the persist phase have not been configured.'
        );

        app(InputSchemaService::class)->forPhase(Phase::Persist);
    }
}
