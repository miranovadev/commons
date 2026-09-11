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
In phases 1–6, Write contains only immediate valid Expressive descendants of the current Gate in the Write register. In Order, Write contains active register-equivalent Expressive candidates in the same Commit Region whose Depth/Cadence directions align. Untouched descendants offer Support and Refute. In Distinguish, Persist and Constrain only, an effective personal Attestation replaces that same direction with proposition.traverse; the opposite Attestation direction remains available through supersession. Use the per-target direction choices in the schema. proposition.traverse explicitly chooses an already-attested Write descendant by proposition_id and creates no content. It is never admissible in Disperse, Mediate, Bias or Order. Tragedy means there are zero admissible non-Rest actions in the current Phase. Only then is participant.rest available, as the sole action. Rest is never a preference or an alternative to any action exposed by the schema. In Mediate, read_options starts with the previous Disperse Phase’s Gate and includes active Read-equivalent Propositions in its Commit Region matching that Read anchor’s nonzero Correspondence direction. gate_options contains the active immediate children of these exposed Reads, excluding the current Gate itself. Gate Correspondence and Attestation lean do not filter Mediate candidates. These are reconciliation counterparts, never Write candidates. Bias gate_options contains all active Stance/Frontier choices contributing to the single receipt-weighted effective Attestation lean of active Gate-equivalent Propositions within the same Commit Region, comparing each candidate using its canonical retained hysteretic direction. They may include the current Gate and need not be immediate siblings. proposition.bias takes only action and proposition_id: select one authorized Gate; the server copies its statement exactly into fresh Concord/Canon with one hard reclassify edge. No second source, authored statement, or inherited Attestations, Receipts or Phase touches. Neutral aggregate lean retains the Gate's inherited hysteretic direction. proposition.reconcile takes only action and other_proposition_id: the server copies the current Gate statement exactly and creates two hard parent edges. Do not author synthesis text. Rest ends the Cycle, retaining the current position for nearest-viable-Committal recovery at the next entry. Navigation is your choice, never an automatic fallback. Reclassification creates no Receipt. Use instrument.execute during Bias to ground eligible existing Propositions; their Receipts persist across Cycles and do not transfer across Supersessions. A Commit Region is seeded by the nearest Committal ancestry and stops at the next Committal state, even when both Commits share a Context Horizon. The Horizon remains measurement context, not selectable scope. proposition.commit requires action and proposition_id: choose an authorized Write source; its statement and Commit-only history are preserved. Order preserves all Commitment and Closure requirements; Rest appears only after complete action exhaustion.
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
