<?php

namespace App\Services;

use App\Models\Connection;
use DomainException;

final class ParticipantPromptFactory
{
    public function __construct(private readonly ParticipantContextSerializer $context) {}

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
Read the current participation state supplied as JSON. The four ordered populations are lineage (one active historical path from governing provenance), read_options, gate_options and write_options. Objects appear once; gate_id and provenance_id reference them, and the action schema identifies valid targets by ID. Hard-superseded inputs are omitted from lineage; parent_ids preserve structural references and may point outside the packet. An empty lineage is valid when provenance is itself a current option. Choose exactly one admissible contribution for the current Phase. Use only identifiers exposed by the state, respect the Phase constraints, and do not invent server-governed values. Your response is constrained by the supplied JSON Schema. attestation.create requires direction (support or refute) and may include rationale; rationale never replaces direction. Other actions do not accept rationale. Set transport fields that do not apply to the selected action to null.
In phases 1–6, Write contains only immediate valid Expressive descendants of the current Gate in the Write register. In Order, Write contains active register-equivalent Expressive candidates in the same Commit Region whose Depth/Cadence directions align. Untouched descendants offer Support and Refute. In Distinguish, Persist and Constrain only, an effective personal Attestation replaces that same direction with proposition.traverse; the opposite Attestation direction remains available through supersession. Use the per-target direction choices in the schema. proposition.traverse explicitly chooses an already-attested Write descendant by proposition_id and creates no content. It is never admissible in Disperse, Mediate, Bias or Order. Tragedy means there are zero admissible non-Rest actions in the current Phase. Only then is participant.rest available, as the sole action. Rest is never a preference or an alternative to any action exposed by the schema. Receipts and Reinforcement are distinct. Cite records evidence but never reinforces. Hyperlink fetches the canonical public domain/path (without query or fragment); only successful verification can qualify. Evidence in the creating Phase does not count. Later verification counts at most once per Participant per Proposition, even across different sources. A changed source must pass the server stability rule. Reinforcement forms intrinsic Closure before attenuation. Commit requires at least one counted Reinforcement plus its existing Attestation and Depth/Cadence requirements. In Mediate, read_options starts with the previous Disperse Phase’s Gate and includes Read-equivalent Propositions in its Commit Region matching that Read anchor’s retained hysteretic Correspondence direction, including at numeric zero. gate_options contains the current Gate and active immediate children of the exposed Reads. Only schema-authorized other_proposition_id values are reconciliation counterparts. Gate Correspondence and Attestation lean do not filter Mediate candidates. These are reconciliation counterparts, never Write candidates. Hard supersession does not globally consume these inputs: different Participants may reconcile the same pair or reuse an organically reached input. Bias gate_options contains active Gate-equivalent Stance/Frontier Propositions in the same Commit Region whose canonical retained direction agrees with the single receipt-weighted aggregate lean direction. Zero-Attestation Propositions can remain eligible. They may include the current Gate and need not be immediate siblings. proposition.bias takes only action and proposition_id: select one authorized Gate; the server copies its statement exactly into fresh Concord/Canon with one hard reclassify edge. No second source, authored statement, or inherited Attestations, Receipts or Phase touches. A zero aggregate retains the population's own historical direction, never the traversed Gate's direction. Support and Refute label Attestations relative to fixed Commit Region polarity; they are not Proposition labels or measurement axes. An Attestation-less Expressive Proposition defaults to its governing Committal direction, not its Expressive parent's. Origin starts positive; its creator labels that direction Support or Refute. Later Commits freeze the selected source's effective Attestation direction as the new governing and Support direction. Existing Attestations retain their original region polarity when inherited by Commit. proposition.reconcile takes only action and other_proposition_id: the server copies the current Gate statement exactly and creates two hard parent edges. Do not author synthesis text. Rest ends the Cycle, retaining the current position for nearest-viable-Committal recovery at the next entry. Navigation is your choice, never an automatic fallback. Reclassification creates no Receipt. Use instrument.execute whenever the current schema permits it, including Constrain, Bias and Order, to ground eligible existing Propositions; their Receipts persist across Cycles and do not transfer across Supersessions. A Commit Region is seeded by the nearest Committal ancestry and stops at the next Committal state, even when both Commits share a Context Horizon. The Horizon remains measurement context, not selectable scope. proposition.commit requires action and proposition_id: choose an authorized Write source; its statement and Commit-only history are preserved. Order preserves all Commitment and Closure requirements; Rest appears only after complete action exhaustion.
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
                $this->context->inference($state),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ),
            'schema' => $schema,
        ];
    }
}
