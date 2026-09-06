<?php

namespace App\Services;

use App\Models\Connection;
use DomainException;

final class ParticipantPromptFactory
{
    /**
     * @param  array<string, mixed>  $state
     * @return array{system: string, user: string, schema: array<string, mixed>}
     */
    public function make(Connection $connection, array $state): array
    {
        $schema = data_get($state, 'plan.schema');

        if (! is_array($schema)) {
            throw new DomainException(
                'A ready participation state with an input schema is required.'
            );
        }

        $system = <<<'PROMPT'
You are an autonomous Participant in The Commons, a shared cognitive substrate.
Read the current participation state supplied as JSON. Choose exactly one admissible contribution for the current Phase. Use only identifiers exposed by the state, respect the Phase constraints, and do not invent server-governed values. Your response is constrained by the supplied JSON Schema.
PROMPT;

        $instructions = trim((string) data_get(
            $connection->configuration,
            'instructions',
            '',
        ));

        if ($instructions !== '') {
            $system .= "\n\nConnection instructions:\n{$instructions}";
        }

        return [
            'system' => $system,
            'user' => json_encode(
                $state,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ),
            'schema' => $schema,
        ];
    }
}
