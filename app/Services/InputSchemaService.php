<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Participant;
use App\Models\Phase;
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
        if (app(ExhaustionService::class)->unavailable($gate)) {
            return $this->withReceiptOptions($this->forPhase($kind, ['participant.rest']), $occurrence, $context);
        }
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

        $attestationOptionIds ??= $this->options->attestationOptionsFor($occurrence, $context)->pluck('id')->all();
        $traversalOptionIds ??= $this->options->traversalOptionsFor($occurrence, $context)->pluck('id')->all();

        $schema = $this->specialize(
            $this->forPhase($kind, $actions),
            $gateOptionIds,
            $revisionOptionIds,
            $attestationOptionIds ?? $writeOptionIds,
            $traversalOptionIds,
            $instrumentOptionIds ?? $writeOptionIds,
            $commitOptionIds,
        );

        if (isset($schema['$defs']['actions']['proposition.reclassify'])) {
            $schema['$defs']['actions']['proposition.reclassify']['properties']['proposition_id']['enum'] =
                $kind === PhaseKind::Bias ? $gateOptionIds : [$gate->id];
            $schema['$defs']['actions']['proposition.reclassify']['properties']['receipt_id']['enum'] =
                app(HorizonReceiptService::class)->ordinaryAvailable($occurrence, $context)->pluck('id')->all();
            if ($kind === PhaseKind::Bias) {
                $context ??= new ParticipationContext($occurrence);
                $authority = app(BiasReceiptAuthorityService::class);
                $branches = [];
                $receiptIds = [];
                foreach ($context->nodes($gateOptionIds) as $source) {
                    $ids = $authority->receiptsFor($occurrence, $source, $context)->pluck('id')->all();
                    if ($ids === []) {
                        continue;
                    }
                    $escape = $authority->authority($occurrence, $source, $context) === 'tragedy';
                    $branches[] = ['properties' => [
                        'proposition_id' => ['const' => $source->id],
                        'receipt_id' => $escape
                            ? ['const' => $ids[0], 'description' => 'This current-Region Tragedy Receipt authorizes boundary-crossing Reclassification through this eligible homologous Bias source.']
                            : ['enum' => $ids, 'description' => 'Ordinary evidence from the acting Commit Region; Tragedy Receipts cannot authorize local or mediated Bias.'],
                    ]];
                    $receiptIds = array_merge($receiptIds, $ids);
                }
                $schema['$defs']['actions']['proposition.reclassify']['anyOf'] = $branches;
                $schema['$defs']['actions']['proposition.reclassify']['properties']['receipt_id']['enum'] = array_values(array_unique($receiptIds));
            }
        }

        if (isset($schema['$defs']['actions']['instrument.execute'])) {
            $targets = $this->options->instrumentOptionsFor($occurrence, $context);
            $excluded = $targets->mapWithKeys(fn ($target) => [
                $target->id => app(RelevantInstrumentService::class)->exclusions($target, $context)->all(),
            ])->filter(fn ($items) => $items !== [])->all();
            if ($excluded !== []) {
                $schema['x-commons']['instrument_exclusions'] = $excluded;
                $denied = [];
                foreach ($excluded as $targetId => $items) {
                    foreach ($items as $item) {
                        $invocation = $item['invocation'];
                        $field = $invocation['kind'] === 'web_search' ? 'query' : 'reference';
                        $denied[] = ['required' => ['proposition_id', 'instrument'], 'properties' => [
                            'proposition_id' => ['const' => $targetId],
                            'instrument' => ['required' => ['kind', $field], 'properties' => [
                                'kind' => ['const' => $invocation['kind']],
                                $field => ['enum' => [$invocation[$field]]],
                            ]],
                        ]];
                    }
                }
                $schema['$defs']['actions']['instrument.execute']['not'] = ['anyOf' => $denied];
                $schema['$defs']['actions']['instrument.execute']['properties']['instrument']['description'] =
                    $schema['$defs']['instrument']['description'].' Invocations denied by this action’s not clause are already relevant. Choose a different resource or use an existing Receipt.';
            }
        }

        if (isset($schema['$defs']['actions']['instrument.execute'])) {
            $replays = $this->receipts->forPhase($occurrence, $context)->where('can_replay', true)->groupBy('proposition_id');
            if ($replays->isNotEmpty()) {
                $fresh = $schema['$defs']['instrument'];
                $form = fn (array $ids): array => ['type' => 'object', 'additionalProperties' => false,
                    'required' => ['replay_receipt_id'], 'properties' => ['replay_receipt_id' => ['type' => 'string', 'enum' => $ids]]];
                $action = &$schema['$defs']['actions']['instrument.execute'];
                $action['properties']['instrument'] = ['anyOf' => [...$fresh['anyOf'], $form($replays->flatten(1)->pluck('id')->all())]];
                $action['anyOf'] = [['properties' => ['instrument' => $fresh]]];
                foreach ($replays as $target => $receipts) {
                    $action['anyOf'][] = ['properties' => ['proposition_id' => ['enum' => [$target]],
                        'instrument' => ['anyOf' => [$form($receipts->pluck('id')->all())]]]];
                }
                unset($action);
            }
        }

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

        if ($schema['$defs']['actions'] === []) {
            return $this->withReceiptOptions($this->forPhase($kind, ['participant.rest']), $occurrence, $context);
        }

        return $this->withReceiptOptions($schema, $occurrence, $context);
    }

    private function withReceiptOptions(array $schema, Phase $phase, ?ParticipationContext $context): array
    {
        $ids = $this->receipts->loadOptions($phase, $context)->pluck('id')->sort()->values()->all();
        if ($ids === []) {
            return $schema;
        }
        $selection = ['type' => 'array', 'uniqueItems' => true, 'items' => ['type' => 'string', 'enum' => $ids]];
        foreach ($schema['$defs']['actions'] as $name => &$action) {
            if ($name === 'participant.rest') {
                continue;
            }
            $action['properties']['receipt_options'] = ['type' => 'object', 'additionalProperties' => false,
                'properties' => ['load' => $selection, 'unload' => $selection],
                'description' => 'Optional desired state for current Gate evidence or scoped cognitive memory. Tragedy and Proposition Search loading remember only: no evidence or Reinforcement; memory alone grants no movement, grounding or escape authority. Load and unload must be disjoint; already satisfied states are no-ops. Applied atomically with this action, visible next Phase.'];
        }
        unset($action);

        return $schema;
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
            'description' => 'Choose exactly one action per response. Optional Gate Receipt desired states accompany successful forward contributions.',
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
    ): array {
        $bindings = [
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
