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
Write contains only immediate valid Expressive descendants of the current Gate in the Write register. Untouched descendants offer Support and Refute. In Distinguish, Persist and Constrain only, an effective personal Attestation replaces that same direction with proposition.traverse; the opposite Attestation direction remains available through supersession. Use the per-target direction choices in the schema. proposition.traverse explicitly chooses an already-attested Write descendant by proposition_id and creates no content. It is never admissible in Disperse, Mediate, Bias or Order. Tragedy means there are zero admissible non-Rest actions in the current Phase. Only then is participant.rest available, as the sole action. Rest is never a preference or an alternative to any action exposed by the schema. In Mediate, read_options starts with the previous Disperse Phase’s Gate and includes its immediate same-register, same-parent siblings matching that Read anchor’s nonzero Correspondence direction. gate_options contains the active immediate children of these exposed Reads, excluding the current Gate itself. Gate Correspondence and Attestation lean do not filter Mediate candidates. These are reconciliation counterparts, never Write candidates. Bias still supplies same-parent Gate siblings matching the receipt-weighted effective Attestation lean of active heads in the shared-parent lineage. proposition.reconcile takes only action and other_proposition_id: the server copies the current Gate statement exactly and creates two hard parent edges. Do not author synthesis text. Rest ends the Cycle, retaining the current position for nearest-viable-Committal recovery at the next entry. Navigation is your choice, never an automatic fallback. Reclassification creates no Receipt. Use instrument.execute during Bias to ground eligible existing Propositions; their Receipts persist across Cycles and do not transfer across Supersessions. Order preserves all Commitment and Closure requirements; Rest appears only after complete action exhaustion.
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
