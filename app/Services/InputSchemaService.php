<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Participant;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Facades\Cache;

final readonly class InputSchemaService
{
    public function __construct(
        private PhaseService $phases,
        private GateService $gates,
        private PropositionOwnershipService $ownership,
        private ClosureOptionService $closure,
        private PropositionOptionService $options,
        private PhaseGenerationService $generation,
        private ReceiptContextService $receipts,
    ) {}

    /**
     * @param  list<string>  $writeOptionIds
     * @param  list<string>  $gateOptionIds
     * @param  list<string>  $revisionOptionIds
     * @param  list<string>|null  $traversalOptionIds
     * @param  list<string>|null  $instrumentOptionIds
     * @param  list<string>|null  $attestationOptionIds
     * @return array<string, mixed>
     */
    public function forParticipant(
        Participant $participant,
        array $writeOptionIds,
        array $gateOptionIds,
        array $revisionOptionIds = [],
        ?array $attestationOptionIds = null,
        ?array $traversalOptionIds = null,
        ?array $instrumentOptionIds = null,
        ?ParticipationContext $context = null,
    ): array {
        $occurrence = $context?->phase ?? $this->phases->currentFor($participant);
        $kind = $occurrence->kind;
        $plan = $this->phases->plan($kind);
        $gate = $context?->propositions->get($occurrence->gate_proposition_id) ?? $this->gates->forPhase($occurrence);
        $ownsGate = $this->ownership->ownedBy($gate, $participant);

        $actions = array_values(array_filter(
            array_diff($plan['actions'], ['participant.rest']),
            function (string $action) use ($plan, $ownsGate, $occurrence, $context): bool {
                $constraints = $plan['constraints'][$action] ?? [];

                return $this->generation->available($occurrence, $action, $context) && ($ownsGate || ! in_array(
                    'gate-owner',
                    $constraints,
                    true,
                ));
            },
        ));

        $commitOptionIds = $kind === PhaseKind::Order
            ? $this->closure->committableFor($occurrence, $context)->pluck('id')->all() : [];

        $traversalOptionIds ??= $this->options->traversalOptionsFor($occurrence, $context)->pluck('id')->all();

        $schema = $this->specialize(
            $this->forPhase($kind, $actions),
            $gateOptionIds,
            $revisionOptionIds,
            $attestationOptionIds ?? $writeOptionIds,
            $traversalOptionIds,
            $instrumentOptionIds ?? $writeOptionIds,
            $commitOptionIds,
            $this->receipts->forPhase($occurrence, $context)->where('status', 'available')->pluck('id')->all(),
        );

        if (isset($schema['$defs']['actions']['attestation.create'])) {
            $ids = $attestationOptionIds ?? $writeOptionIds;
            $effective = Attestation::query()->effective()
                ->whereIn('proposition_id', $ids)
                ->whereHas('phase.cycle', fn ($query) => $query->whereBelongsTo($participant))
                ->pluck('direction', 'proposition_id');
            $choices = [];
            foreach (['refute', 'support'] as $direction) {
                $targets = array_values(array_filter($ids, fn (string $id): bool => $effective->get($id) !== $direction));
                if ($targets !== []) {
                    $choices[] = ['properties' => [
                        'proposition_id' => ['enum' => $targets],
                        'direction' => ['enum' => [$direction]],
                    ]];
                }
            }
            $schema['$defs']['actions']['attestation.create']['anyOf'] = $choices;
        }

        // Rest is the sole fallback after every non-Rest action is specialized.
        // It must never compete with a phase-native or target-bound action.
        return $schema['$defs']['actions'] === []
            ? $this->forPhase($kind, ['participant.rest'])
            : $schema;
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
            'description' => 'Choose exactly one action per response. Receipt Reads update context without advancing the Phase.',
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
     * @param  list<string>  $traversalOptionIds
     * @param  list<string>  $instrumentOptionIds
     * @param  list<string>  $gateOptionIds
     * @param  list<string>  $revisionOptionIds
     * @param  list<string>  $attestationOptionIds
     * @return array<string, mixed>
     */
    private function specialize(
        array $schema,
        array $gateOptionIds,
        array $revisionOptionIds,
        array $attestationOptionIds,
        array $traversalOptionIds,
        array $instrumentOptionIds,
        array $commitOptionIds,
        array $receiptOptionIds,
    ): array {
        $bindings = [
            'receipt.read' => ['property' => 'receipt_id', 'values' => $receiptOptionIds],
            'proposition.commit' => [
                'property' => 'proposition_id',
                'values' => $commitOptionIds,
            ],
            'proposition.traverse' => [
                'property' => 'proposition_id',
                'values' => $traversalOptionIds,
            ],
            'proposition.revise' => [
                'property' => 'proposition_id',
                'values' => $revisionOptionIds,
            ],
            'attestation.create' => [
                'property' => 'proposition_id',
                'values' => $attestationOptionIds,
            ],
            'instrument.execute' => [
                'property' => 'proposition_id',
                'values' => $instrumentOptionIds,
            ],
            'proposition.bias' => [
                'property' => 'proposition_id',
                'values' => $gateOptionIds,
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

            $schema['$defs']['actions'][$action]['properties'][$binding['property']]['enum'] = $binding['values'];
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
