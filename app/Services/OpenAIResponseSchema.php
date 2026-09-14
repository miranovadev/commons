<?php

namespace App\Services;

use DomainException;

final class OpenAIResponseSchema
{
    /**
     * Convert the action union into a single strict transport object.
     *
     * OpenAI Structured Outputs accepts a narrower JSON Schema subset than
     * the Commons validator. Every transport field is required; fields that
     * do not apply to the selected action are nullable and removed from the
     * response before the full Commons schema validates it.
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

        // Keep the existing transport keys stable, but bind every field to its
        // action. Inapplicable fields are null-only, never borrowed from a
        // neighbouring action. Only optional fields may be nullable.
        $fields = [];
        foreach ($actions as $action) {
            $fields = array_values(array_unique([
                ...$fields, ...array_keys($action['properties']),
            ]));
        }

        $variants = [];
        foreach ($actions as $action) {
            foreach ($action['anyOf'] ?? [[]] as $choice) {
                $properties = [];
                foreach ($fields as $field) {
                    if (! isset($action['properties'][$field])) {
                        $properties[$field] = ['type' => 'null'];

                        continue;
                    }

                    $property = array_replace(
                        $this->resolve($action['properties'][$field], $definitions),
                        $choice['properties'][$field] ?? [],
                    );
                    $property = $this->transport($property);
                    $properties[$field] = in_array($field, $action['required'] ?? [], true)
                        ? $property : $this->nullable($property);
                }

                $variants[] = [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => $fields,
                    'properties' => $properties,
                ];
            }
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['contribution'],
            'properties' => [
                'contribution' => count($variants) === 1
                    ? $variants[0] : ['anyOf' => $variants],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, array<string, mixed>>  $definitions
     * @return array<string, mixed>
     */
    private function resolve(array $schema, array $definitions): array
    {
        $reference = $schema['$ref'] ?? null;

        if (is_string($reference)
            && str_starts_with($reference, '#/$defs/')) {
            $name = substr($reference, strlen('#/$defs/'));
            $definition = $definitions[$name] ?? null;

            if (! is_array($definition)) {
                throw new DomainException(
                    "The Commons schema definition {$name} does not exist."
                );
            }

            unset($schema['$ref']);
            $schema = array_replace_recursive($definition, $schema);
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $name => $property) {
                $schema['properties'][$name] = $this->resolve(
                    $property,
                    $definitions,
                );
            }
        }

        foreach (['anyOf', 'oneOf'] as $union) {
            if (isset($schema[$union])) {
                $schema[$union] = array_map(fn ($variant) => $this->resolve($variant, $definitions), $schema[$union]);
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->resolve($schema['items'], $definitions);
        }

        return $schema;
    }

    /**
     * Remove constraints that are redundant at the provider boundary. The
     * complete Commons schema remains authoritative after inference.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function transport(array $schema): array
    {
        foreach ([
            '$schema', '$id', 'minLength', 'maxLength', 'pattern', 'format',
            'minimum', 'maximum', 'multipleOf', 'minItems', 'maxItems',
        ] as $keyword) {
            unset($schema[$keyword]);
        }

        if (array_key_exists('const', $schema)) {
            $constant = $schema['const'];
            $schema['type'] ??= get_debug_type($constant);
            $schema['enum'] = [$constant];
            unset($schema['const']);
        }

        if (($schema['type'] ?? null) === 'object') {
            foreach ($schema['properties'] ?? [] as $name => $property) {
                $schema['properties'][$name] = $this->transport($property);
            }

            $schema['required'] = array_keys($schema['properties'] ?? []);
            $schema['additionalProperties'] = false;
        }

        if (isset($schema['anyOf'])) {
            $schema['anyOf'] = array_map($this->transport(...), $schema['anyOf']);
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->transport($schema['items']);
        }

        return $schema;
    }

    /** @param array<string, mixed> $schema */
    private function nullable(array $schema): array
    {
        $type = $schema['type'] ?? null;

        if (is_string($type)) {
            $schema['type'] = [$type, 'null'];
        } elseif (is_array($type) && ! in_array('null', $type, true)) {
            $schema['type'][] = 'null';
        }

        if (isset($schema['enum'])
            && ! in_array(null, $schema['enum'], true)) {
            $schema['enum'][] = null;
        }

        return $schema;
    }
}
