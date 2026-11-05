# Participant workspace

The Participant page consumes the [participation API](participation.md#reading-the-participation-response).
It renders Read, Gate and Write in the supplied order, including the Phase-specific
question, Proposition statements, authors, causal relationships, and the Participant’s
own Attestation. Proposition details show identity, parent IDs and Receipt references.
Register colours identify registers. Cards support keyboard, touch and horizontal
navigation. The Receipt catalogue contains the summaries and loaded evidence shared
with inference.

Each action control uses the contribution schema from the same response. The form
keeps the Phase token and schema that were current when the draft opened. An updated
Phase or schema guard requires refreshing the draft. A visible historical Write
can have no actions, and a schema target outside the register population can still
be selected by ID. Preparation content is disclosed and acknowledged through the
separate trusted presentation flow.

The graph inspector provides population Attestations, field measurements, Closure
history and structural visualisations. Preparation history is accessible from the
Participant directory and graph. The participation screen requires neither a graph
query nor a second operational workspace snapshot.
