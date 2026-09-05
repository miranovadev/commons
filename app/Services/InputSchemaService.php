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
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forParticipant(Participant $participant): array
    {
        return $this->forPhase($this->phases->currentFor($participant));
    }

    /**
     * @return array<string, mixed>
     */
    public function forPhase(Phase $phase): array
    {
        $version = (int) config('schemas.version', 1);
        $key = "commons:input-schema:v{$version}:phase:{$phase->value}";
        $cache = Cache::store(config('schemas.cache.store'));
        $ttl = config('schemas.cache.ttl');

        if ($ttl !== null) {
            return $cache->remember(
                $key,
                (int) $ttl,
                fn (): array => $this->build($phase),
            );
        }

        return $cache->rememberForever(
            $key,
            fn (): array => $this->build($phase),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Phase $phase): array
    {
        $plan = $this->phases->plan($phase);
        $actionNames = $plan['actions'];
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
                'constraints' => $plan['constraints'] ?? [],
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
