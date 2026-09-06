<?php

namespace App\Services;

use DomainException;

final class OpenAIResponseSchema
{
    /**
     * Wrap the Commons action union beneath an object root and make optional
     * fields explicitly nullable, as required by OpenAI Structured Outputs.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function fromCommons(array $schema): array
    {
        $actions = data_get($schema, '$defs.actions');

        if (! is_array($actions) || $actions === []) {
            throw new DomainException(
                'The Commons input schema exposes no admissible actions.'
            );
        }

        $definitions = $schema['$defs'] ?? [];
        unset($definitions['actions']);

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['contribution'],
            'properties' => [
                'contribution' => [
                    'anyOf' => array_values(array_map(
                        fn (array $action): array => $this->strict($action),
                        $actions,
                    )),
                ],
            ],
            '$defs' => array_map(
                fn (array $definition): array => $this->strict($definition),
                $definitions,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function strict(array $schema): array
    {
        if (($schema['type'] ?? null) === 'object') {
            $properties = $schema['properties'] ?? [];
            $originallyRequired = $schema['required'] ?? [];

            foreach ($properties as $name => $property) {
                $property = $this->strict($property);

                if (! in_array($name, $originallyRequired, true)) {
                    $property = $this->nullable($property);
                }

                $properties[$name] = $property;
            }

            $schema['properties'] = $properties;
            $schema['required'] = array_keys($properties);
            $schema['additionalProperties'] = false;
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->strict($schema['items']);
        }

        return $schema;
    }

    /** @param array<string, mixed> $schema */
    private function nullable(array $schema): array
    {
        if (isset($schema['type']) && is_string($schema['type'])) {
            $schema['type'] = [$schema['type'], 'null'];

            return $schema;
        }

        return [
            'anyOf' => [$schema, ['type' => 'null']],
        ];
    }
}
