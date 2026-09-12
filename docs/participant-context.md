# Autonomous participant context

The [Core Invariants](core-invariants.md) govern this serialization boundary.
Autonomous cognitive context and human observational data are separate contracts.
Only current local history, operational options, evidence and the valid action
schema belong in an inference packet.

## API boundaries

| Endpoint | Contract |
| --- | --- |
| GET/POST `…/participants/{participant}/participate` | Resumable lifecycle envelope with the compact `plan` below |
| POST `…/participants/{participant}/participate/enter` | Same compact state after entry |
| GET `…/participants/{participant}/plan` | Compact plan only |
| GET `…/participants/{participant}/workspace` | Human lifecycle envelope, presentation plan and broader register browsing |

All routes retain scoped Substrate/Participant bindings and `act` authorization.
The human page fetches `/workspace`. Reverb and contribution mutations invalidate
its existing Participant-specific `Participation` cache tag. Graph routes and
channel semantics are unchanged. No query flag enables workspace data on
`/participate`, and inference never fetches `/workspace`.

`InputPlanService` shares the existing snapshot, candidate calculations and action
schema between the two paths. Its default path does not build the Horizon
workspace. `ParticipantContextSerializer` explicitly allowlists model fields;
`ParticipantPromptFactory` also applies that allowlist at the provider boundary.
The actual external JSON contains only `status` and `plan`. Cycle display,
entry/completion UI, last-Commit display and Perspective explanation stay outside
inference. The resumable API retains those lifecycle fields for clients.

```json
{
  "phase": { "id": "…", "number": 5, "kind": "mediate", "write_register": "stance" },
  "provenance_id": "…",
  "gate_id": "…",
  "lineage": [],
  "read_options": [],
  "gate_options": [],
  "write_options": [],
  "tragedy": false,
  "schema": {}
}
```

The four populations always appear in Lineage → Read → Gate → Write order.
Propositions retain statements, classification, structural parent references,
observables, effective directional evidence, the viewer's Attestation and
Instrument outcomes when present. They omit titles, avatars/callsigns, transition
labels, display timestamps and redundant Support/Refute counts. Positive/negative
counts and the immutable support direction preserve their interpretation.
Order retains relevant Commitment/Closure prerequisites and candidate evaluations.
The complete action schema stays available, including per-target direction
constraints; the provider's transport schema does not replace those constraints.

## One active historical path

`ActiveParticipantLineage` resolves the path from current Gate backwards, then
returns it provenance-first. It stops at the nearest governing Committal, including
same-Horizon Commit boundaries. It never uses the Horizon opener as a substitute
for the current Commit Region's provenance.

* A unary edge follows its actual source, including Revise and Bias selections
  that differ from the operation's Phase Gate.
* Multiple inputs follow the creating Phase's recorded Gate parent. Reconcile's
  other branch is not appended as another historical layer.
* `SupersessionStrength::for()` classifies edges. All hard input IDs are omitted
  from the resolved history, including overlapping inputs and repeated hard
  replacements. Off-path hard replacements retire their input without injecting
  their replacement branch into this history. Traversal through stored inputs
  still reaches earlier history.
* Variant preserves its soft ancestry. Commit ends traversal at its Committal
  result; its replaced expressive input and previous region are outside context.
* Legacy multi-parent records without a recorded parent Gate expose their shared
  resolved history only. They never select a branch by timestamp or identifier.
  Unseeded legacy positions do not receive invented provenance; structural cycles
  and failure to reach a known governing seed fail explicitly.

No Supersession, Proposition, contribution history or participant position is
rewritten. This resolver is separate from graph, measurement and navigation
traversals. `parent_ids` remain raw structural references, potentially outside the
packet. Adjacent displayed history items may be connected through omitted hard
inputs or current options; they are not asserted to be direct database edges.

The complete active region is resolved before operational roles are removed.
Silent count/token truncation cannot cut provenance off from the historical head.
The old `max_read_count`/statement-token ancestry window therefore does not truncate
this contract; `max_read_count` still bounds the human register browsing window.
There is no finite model-wide maximum active soft-chain depth. The size regression
below is a fixture guard, not a runtime cutoff or a promise that arbitrary user
statements or genuinely growing actionable populations fit a fixed byte budget.

## Operational populations and identity

Read candidates come from the existing Mediate Read calculation. Gate contains
the current Gate plus current counterpart/Bias candidates; the action schema
identifies which IDs can actually be selected. The current Gate may be contextual
without being an authorized Bias or reconciliation target.

Write is the union of actual schema-authorized Write, revision, traversal,
Instrument and Attestation targets. Standalone copies under those secondary names
are not serialized. Read owns a shared object first, then Gate, then Write;
additional action roles reference the same ID through the schema. All current
operational IDs are excluded from Lineage. IDs for `gate_id` and `provenance_id`
resolve within the four populations, without standalone full copies. At initial
entry, provenance is the current Gate: it appears once in Gate Options and
Lineage is empty.

The human plan preserves its presentation/action collections for existing cards
and forms, and adds the separate Horizon browsing projection. Its historical
Lineage uses the same resolved history and role exclusions as inference.
Both the lineage modal and historical register preview consume that ordered
history directly. Historical preview never unions Horizon cards, the current
Gate, provenance display, or current action populations. It is a read-only view
of current resolved history grouped by register, not a reconstruction of past
measurements or action schemas.

## Payload regression and measurement

`ParticipantContextFixture` builds a 25-position active path before role removal,
a hard Reconcile, six additional Read/Gate branches, three actionable Writes,
realistic 327-character statements, effective Attestations with rationales and
completed Instruments/Receipts. Growth adds 10 then 500 propositions in a separate
Commit Region within the same Horizon. Current Read/Gate/Write and lineage remain
identical. Additional dense Constrain, Bias and Order cases exercise Instrument
and Closure metadata.

Compared on the same dense fixture with 10 unrelated propositions, against
`main` commit `5b459d8` (compact JSON with unescaped slashes):

| Measurement | Before | After |
| --- | ---: | ---: |
| Plan JSON bytes | 107,945 | 31,904 |
| Model-facing state JSON bytes | 108,432 | 31,930 |
| Full Proposition copies | 92 | 42 |
| Lineage / Read / Gate / Write copies | 25 / 15 / 13 / 3 | 10 / 15 / 14 / 3 |

Model JSON shrinks by about **71%**. The Gate increase is the single contextual
Gate moved from its standalone copy. The history reduction reflects current
Read/Gate separation as well as hard-collapse semantics; it is not text truncation.

Previously `InputPlanService::build()` attached `workspace.registers` from the
active Horizon (a window bounded by configurable `max_read_count`), a standalone
Gate and provenance, and repeated full objects in action collections. The prompt
then JSON-encoded the entire human state. This was a broader Horizon window,
not an unbounded full-Horizon query, but unrelated growth could change the packet
and repeated populations amplified its size.

CI checks **45,000 serialized bytes** and **55 full Proposition objects** for the
dense fixtures, including the actual prompt JSON. The Mediate case uses about
32,000 bytes and 42 objects, leaving roughly 40% byte and 30% object headroom; the denser Order metadata case uses
about 38,000 bytes, leaving about 18% byte headroom;
a doubling fails. Guards also check ordered population keys, every schema target
resolving to an exposed object, no duplicate full objects anywhere in the plan,
no unrelated IDs, and exact plan equality/byte equality after 10 → 500 growth.
A mocked Responses request verifies the JSON delivered to the provider. No real
inference credits are consumed by these tests.

These changes bound serialized context by relevance. The calculation-local
`ParticipationContext` still hydrates broader data needed by existing measurements;
this change does not claim database-memory independence from Substrate size.
