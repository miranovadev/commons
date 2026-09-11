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

        $properties = [];
        $occurrences = [];
        $requiredOccurrences = [];

        foreach ($actions as $action) {
            $required = $action['required'] ?? [];

            foreach ($action['properties'] ?? [] as $name => $property) {
                $property = $this->transport(
                    $this->resolve($property, $definitions),
                );
                $properties[$name] = isset($properties[$name])
                    ? $this->merge($properties[$name], $property)
                    : $property;
                $occurrences[$name] = ($occurrences[$name] ?? 0) + 1;

                if (in_array($name, $required, true)) {
                    $requiredOccurrences[$name] =
                        ($requiredOccurrences[$name] ?? 0) + 1;
                }
            }
        }

        $actionCount = count($actions);

        foreach ($properties as $name => $property) {
            if (($occurrences[$name] ?? 0) < $actionCount
                || ($requiredOccurrences[$name] ?? 0) < $actionCount) {
                $property = $this->nullable($property);
            }

            $properties[$name] = $property;
        }

        $transport = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['contribution'],
            'properties' => [
                'contribution' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => array_keys($properties),
                    'properties' => $properties,
                ],
            ],
        ];

        // Bias and Instruments share proposition_id but have disjoint Gate/Write
        // targets. Keep their target sets separate at the provider boundary too.
        if ((isset($actions['proposition.bias']) || isset($actions['proposition.commit'])) && count($actions) > 1) {
            $base = $transport['properties']['contribution'];
            $variants = [];
            foreach ($actions as $action) {
                foreach ($action['anyOf'] ?? [[]] as $choice) {
                    $variant = $base;
                    foreach ($base['properties'] as $field => $unused) {
                        if (! isset($action['properties'][$field])) {
                            $variant['properties'][$field] = ['type' => 'null'];
                            continue;
                        }
                        $property = $this->transport($this->resolve($action['properties'][$field], $definitions));
                        $property = array_replace($property, $choice['properties'][$field] ?? []);
                        $variant['properties'][$field] = in_array($field, $action['required'] ?? [], true)
                            ? $property : $this->nullable($property);
                    }
                    $variants[] = $variant;
                }
            }
            $transport['properties']['contribution'] = ['anyOf' => $variants];

            return $transport;
        }

        // Preserve per-target Attestation directions at the inference boundary.
        $choices = $actions['attestation.create']['anyOf'] ?? [];
        if ($choices !== []) {
            $base = $transport['properties']['contribution'];
            $variants = [];
            $otherActions = array_values(array_diff(array_keys($actions), ['attestation.create', 'proposition.traverse']));
            if ($otherActions !== []) {
                $other = $base;
                $other['properties']['action']['enum'] = $otherActions;
                $variants[] = $other;
            }
            if (isset($actions['proposition.traverse'])) {
                $traverse = $base;
                $traverse['properties']['action']['enum'] = ['proposition.traverse'];
                $traverse['properties']['proposition_id'] = $this->transport(
                    $actions['proposition.traverse']['properties']['proposition_id'],
                );
                $variants[] = $traverse;
            }
            foreach ($choices as $choice) {
                $variant = $base;
                $variant['properties']['action']['enum'] = ['attestation.create'];
                foreach ($choice['properties'] as $name => $constraint) {
                    $variant['properties'][$name] = array_replace(
                        $variant['properties'][$name], $constraint, ['type' => 'string'],
                    );
                }
                $variants[] = $variant;
            }
            $transport['properties']['contribution'] = ['anyOf' => $variants];
        }

        return $transport;
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

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->transport($schema['items']);
        }

        return $schema;
    }

    /**
     * Merge the same field as exposed by multiple admissible actions.
     *
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return array<string, mixed>
     */
    private function merge(array $left, array $right): array
    {
        if (isset($left['enum']) || isset($right['enum'])) {
            $left['enum'] = array_values(array_unique([
                ...($left['enum'] ?? []),
                ...($right['enum'] ?? []),
            ], SORT_REGULAR));
        }

        return $left;
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

