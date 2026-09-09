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
proposition.traverse explicitly chooses an existing local Expressive Proposition by proposition_id. It creates no content and may coexist with other actions. Tragedy means no valid Expressive traversal remains; only then is participant.rest available. Rest ends the Cycle, retaining the current position for nearest-viable-Committal recovery at the next entry. Navigation is your choice, never an automatic fallback. Reclassification creates no Receipt. Use instrument.execute during Bias to ground eligible existing Propositions; their Receipts persist across Cycles and do not transfer across Supersessions. Order preserves all Commitment and Closure requirements while permitting Traverse or Rest.
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
