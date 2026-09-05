<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Participant;
use DomainException;
use Illuminate\Support\Facades\Cache;

final readonly class InputSchemaService
{
    public function __construct(
        private PhaseService $phases,
        private GateService $gates,
        private PropositionOwnershipService $ownership,
    ) {}

    /**
     * @param  list<string>  $writeOptionIds
     * @param  list<string>  $gateOptionIds
     * @return array<string, mixed>
     */
    public function forParticipant(
        Participant $participant,
        array $writeOptionIds,
        array $gateOptionIds,
    ): array {
        $occurrence = $this->phases->currentFor($participant);
        $kind = $occurrence->kind;
        $plan = $this->phases->plan($kind);
        $gate = $this->gates->forPhase($occurrence);
        $ownsGate = $this->ownership->ownedBy($gate, $participant);

        $actions = array_values(array_filter(
            $plan['actions'],
            static function (string $action) use ($plan, $ownsGate): bool {
                $constraints = $plan['constraints'][$action] ?? [];

                return $ownsGate || ! in_array(
                    'gate-owner',
                    $constraints,
                    true,
                );
            },
        ));

        return $this->specialize(
            $this->forPhase($kind, $actions),
            $writeOptionIds,
            $gateOptionIds,
        );
    }

    /**
     * @param  list<string>|null  $actions
     * @return array<string, mixed>
     */
    public function forPhase(
        PhaseKind $kind,
        ?array $actions = null,
    ): array {
        $plan = $this->phases->plan($kind);
        $actionNames = $actions ?? $plan['actions'];
        $version = (int) config('schemas.version', 1);
        $variant = sha1(implode('|', $actionNames));
        $key = "commons:input-schema:v{$version}:phase:{$kind->value}:{$variant}";
        $cache = Cache::store(config('schemas.cache.store'));
        $ttl = config('schemas.cache.ttl');

        if ($ttl !== null) {
            return $cache->remember(
                $key,
                (int) $ttl,
                fn (): array => $this->build($kind, $actionNames),
            );
        }

        return $cache->rememberForever(
            $key,
            fn (): array => $this->build($kind, $actionNames),
        );
    }

    /**
     * @param  list<string>  $actionNames
     * @return array<string, mixed>
     */
    private function build(PhaseKind $kind, array $actionNames): array
    {
        $plan = $this->phases->plan($kind);
        $available = config('schemas.actions', []);
        $actions = [];

        foreach ($actionNames as $name) {
            if (! isset($available[$name]) || ! is_array($available[$name])) {
                throw new DomainException(
                    "The {$name} action schema is not defined."
                );
            }

            $actions[$name] = $available[$name];
        }

        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$id' => "https://miranova.dev/commons/schemas/phases/{$plan['name']}.json",
            'title' => ucfirst($plan['name']).' phase response',
            'description' => 'Exactly one action available during this phase.',
            'oneOf' => array_map(
                static fn (string $name): array => [
                    '$ref' => "#/\$defs/actions/{$name}",
                ],
                $actionNames,
            ),
            '$defs' => [
                ...config('schemas.$defs', []),
                'actions' => $actions,
            ],
            'x-commons' => [
                'kind' => $plan['name'],
                'description' => $plan['description'],
                'one_action' => true,
                'gate' => $plan['gate'],
                'constraints' => array_intersect_key(
                    $plan['constraints'] ?? [],
                    $actions,
                ),
                'server_governed' => [
                    'gate_proposition_id',
                    'domain',
                    'write_register',
                    'tier',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $writeOptionIds
     * @param  list<string>  $gateOptionIds
     * @return array<string, mixed>
     */
    private function specialize(
        array $schema,
        array $writeOptionIds,
        array $gateOptionIds,
    ): array {
        $bindings = [
            'proposition.select' => [
                'property' => 'proposition_id',
                'values' => $writeOptionIds,
            ],
            'attestation.create' => [
                'property' => 'proposition_id',
                'values' => $writeOptionIds,
            ],
            'instrument.execute' => [
                'property' => 'proposition_id',
                'values' => $writeOptionIds,
            ],
            'proposition.reconcile' => [
                'property' => 'other_proposition_id',
                'values' => $gateOptionIds,
            ],
        ];

        foreach ($bindings as $action => $binding) {
            if (! isset($schema['$defs']['actions'][$action])) {
                continue;
            }

            if ($binding['values'] === []) {
                unset($schema['$defs']['actions'][$action]);

                continue;
            }

            $schema['$defs']['actions'][$action]['properties']
                [$binding['property']]['enum'] = $binding['values'];
        }

        $actions = $schema['$defs']['actions'];
        $schema['oneOf'] = array_values(array_filter(
            $schema['oneOf'],
            static function (array $reference) use ($actions): bool {
                $name = basename($reference['$ref']);

                return isset($actions[$name]);
            },
        ));
        $schema['x-commons']['constraints'] = array_intersect_key(
            $schema['x-commons']['constraints'],
            $actions,
        );

        return $schema;
    }
}
