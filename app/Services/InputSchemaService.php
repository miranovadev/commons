<?php

namespace App\Services;

use App\Enums\Phase;
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
     * @return array<string, mixed>
     */
    public function forParticipant(Participant $participant): array
    {
        $occurrence = $this->phases->currentFor($participant);
        $phase = $occurrence->position;
        $plan = $this->phases->plan($phase);
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

        return $this->forPhase($phase, $actions);
    }

    /**
     * @param  list<string>|null  $actions
     * @return array<string, mixed>
     */
    public function forPhase(Phase $phase, ?array $actions = null): array
    {
        $plan = $this->phases->plan($phase);
        $actionNames = $actions ?? $plan['actions'];
        $version = (int) config('schemas.version', 1);
        $variant = sha1(implode('|', $actionNames));
        $key = "commons:input-schema:v{$version}:phase:{$phase->value}:{$variant}";
        $cache = Cache::store(config('schemas.cache.store'));
        $ttl = config('schemas.cache.ttl');

        if ($ttl !== null) {
            return $cache->remember(
                $key,
                (int) $ttl,
                fn (): array => $this->build($phase, $actionNames),
            );
        }

        return $cache->rememberForever(
            $key,
            fn (): array => $this->build($phase, $actionNames),
        );
    }

    /**
     * @param  list<string>  $actionNames
     * @return array<string, mixed>
     */
    private function build(Phase $phase, array $actionNames): array
    {
        $plan = $this->phases->plan($phase);
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
                'phase' => $plan['name'],
                'one_action' => true,
                'gate' => $plan['gate'],
                'write_registers' => $plan['write_registers'],
                'constraints' => array_intersect_key(
                    $plan['constraints'] ?? [],
                    $actions,
                ),
                'server_governed' => [
                    'gate_proposition_id',
                    'domain',
                    'register',
                    'tier',
                ],
            ],
        ];
    }
}
