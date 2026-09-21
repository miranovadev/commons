# Autonomous participant context

The [Core Invariants](core-invariants.md) govern this serialization boundary.
Autonomous cognitive context and human observational data are separate contracts.
Only current local history, operational options, evidence and the valid action
schema belong in an inference packet.

Movement topology, Homeostatic populations and context visibility are separate
contracts under [Invariant 17](core-invariants.md#17-lineage-constrained-participant-movement).
Forward movement follows an explicit causal edge from the current Gate; recovery
follows recorded ancestry backward. Visible history and regional calculation
members do not become movement targets. **No edge means no movement.**

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
  "exhausted_alternatives": [],
  "rest_available": false,
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
  that differ from the operation's Phase Gate in stored history. Resolving that
  history does not endorse movement from an unrelated Gate or create a missing
  causal edge.
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

The complete active historical path is resolved before operational roles are removed.
Silent count/token truncation cannot cut provenance off from the historical head.
The old `max_read_count`/statement-token ancestry window therefore does not truncate
this contract; `max_read_count` still bounds the human register browsing window.
There is no finite model-wide maximum active soft-chain depth. The size regression
below is a fixture guard, not a runtime cutoff or a promise that arbitrary user
statements or genuinely growing actionable populations fit a fixed byte budget.

## Operational populations and identity

Mediate exposes only its causal Read anchor; regional compatible Reads remain internal calculation inputs. Mediate Gate
contains the current Gate plus authorised reconciliation counterparts. Those
counterparts are operands, not movement destinations; Reconcile creates the two
causal edges. Bias Gate contains only the current causal Gate, including when it
has no selectable action. Regional Bias calculation candidates are not serialized
as operational Gates.

Commit Region membership defines calculation/context scope only where a Commons
operation explicitly requires it; it never creates a traversal edge. Mediate —
Read and Bias — Gate may calculate regionally. Closure, performed during Order —
Write, independently evaluates each causally local Write's own lineage/history
and participation, without a regional cross-lineage Write population.
The schema must enforce structural authority from the current Gate independently
of a candidate's measurement rank or serialized lane.

Write is the union of actual schema-authorized Write, revision, traversal,
Instrument and Attestation targets. At Order it additionally retains **all**
causally local expressive Writes, regardless of Closure or grounding eligibility.
Commit targets are an admissible subset; ordinary expressive eligibility remains
separate from Closure, and only each Write's own direct Committal
result may be traversed. `commitment_options` evaluates every local expressive
Write, including inadmissible and frozen ones. Standalone copies under secondary names
are not serialized. Read owns a shared object first, then Gate, then Write;
additional action roles reference the same ID through the schema. All current
operational IDs are excluded from Lineage. IDs for `gate_id` and `provenance_id`
resolve within the four populations, without standalone full copies. At initial
entry, provenance is the current Gate: it appears once in Gate Options and
Lineage is empty. Order's recorded Gate is the selected Bias-produced Write and
appears in Write, not as an alternate Gate selection. Its existing same-position
revision paths remain causal; siblings sharing its Stance/Frontier parent do not.

Expressive Traverse targets are immediate valid causal descendants of the current
Gate, subject to phase, evidence and personal traversal prerequisites and the
documented revision rules. Traversal never selects globally. Same-region and
same-position Writes which are not causal descendants of the current Gate are
not Traverse targets. At Bias, successful-route continuity preserves only the
existing edge from that exact Gate to its frozen classification. At Order, it
continues along the direct Commit edge from that classification. Neither regional
membership nor visibility can substitute for either edge.

The human plan preserves its presentation/action collections for existing cards
and forms, and adds the separate Horizon browsing projection. Its historical
Lineage uses the same resolved history and role exclusions as inference.
Both the lineage modal and historical register preview consume that ordered
history directly. Historical preview never unions Horizon cards, the current
Gate, provenance display, or current action populations. It is a read-only view
of current resolved history grouped by register, not a reconstruction of past
measurements or action schemas.

## Recovery is a backward movement contract

Recovery begins at the Participant's actual current position and walks backward
through recorded arrival provenance. Only ancestral Committals on that path may
compete, with attenuation based on actual backward edge distance, including
revisions and historical intermediates. At a multi-input Write, the recorded
arrival Gate determines the path; a display shortcut or the other input cannot
replace it.

The persisted Cycle Horizon confines recovery until exhaustion permits crossing
its opening boundary. The same walk then continues backward into previous
Horizons, retaining the original position as the distance origin. Recovery never
resets to a Horizon provenance node, searches siblings or descendants, or searches
unvisited branches. Context serialization can omit historical intermediates; the
recovery walk cannot. See [backward lineage recovery](recovery-basins.md).

## Payload regression and measurement

`ParticipantContextFixture` builds a 25-position active path before role removal,
a hard Reconcile, six additional Read/Gate branches, three actionable Writes,
realistic 300-character statements, effective Attestations with rationales and
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

## Persistent Receipt context

Ordinary available Receipts are local to the governing Committal of their observed Proposition. The current Gate's governing Committal must match; unresolved or malformed ancestry fails closed. Existing qualification and Horizon ownership checks remain in force. No Horizon-wide fallback exists.

Every dynamic Receipt reference is `id`, `type`, `reference`, `loaded`, including catalogue, Read, Gate, Write and Lineage references. Attached Receipts leave the shared available catalogue. Attachment does not load evidence.

Only persistently loaded Receipts grounding the current participant-facing Read appear, once each, in the stable `loaded_receipts` evidence layer. Loaded Gate, Write and historical evidence stays metadata. Mediate still exposes its single causal Read anchor; regional calculation inputs never activate evidence. This visibility rule does not change Reinforcement.

Optional `receipt_options: {load: [...], unload: [...]}` accompanies a forward Phase contribution (never Rest). Both lists enumerate exact eligible current Gate IDs, exclude Tragedy and reject overlap. Idempotent changes commit in the same transaction and affect measurements and the next Phase's cognition. No second inference or cache-warming call occurs. Existing standalone `receipt.read` and Replay eligibility remain compatible.

The human transport retains capability and replay lifecycle metadata required by its controls; inactive bodies, signatures and payloads are omitted. Model requests reduce all dynamic Receipt entries to the simple reference shape above. See the normative [Receipt model](receipt-region-context.md).

## Local exhausted context

Exhaustion removes operational eligibility, not local contextual visibility.
`ParticipantHistoricalContext` owns `exhausted_alternatives`, separately from the
operational/evidence working set in `ParticipationContext` and the active-only
`PropositionOptionService`. Both workspace and autonomous plans receive this same
collection. The provider boundary explicitly allowlists its compact fields.

Each alternative contains `id`, `role` (`read`, `gate`, or `write`), producing
`phase_number`, `register`, `tier`, a statement excerpt (240 characters plus the
ellipsis, with `statement_truncated`), `exhausted`, `basin_exhausted`,
`exhausted_at`, and `actionable: false`. Only `exhaustion_states` establishes its
status. An unavailable legacy hold is not relabelled as proven exhaustion.

Selection uses the exact producing Phase, creating Horizon, Domain-derived
register and tier, then the local structural relationship:

| Current Phase | Historical context |
| --- | --- |
| Distinguish–Mediate Write | Direct Gate children and same-position revisions |
| Mediate Read | Constrain alternatives in the governing Commit Region |
| Mediate Gate | Disperse children of internal comparison Reads or local exhausted Reads, within that region |
| Bias Gate / Write | Mediate / Bias alternatives in the governing Commit Region |
| Order Gate / Write | Bias alternatives and their Order-produced Committal results with exhausted outgoing basins |

Historical alternatives explain explored structure; directional/evidence
admissibility remains solely in the action services. Region resolution batches
candidate ancestry and stops at each Committal, including same-Horizon Commits;
ambiguous multi-Commit ancestry is excluded. Direct Write lookup follows revisions
only, never later-phase descendants. Neither lookup hydrates historical evidence
or serializes downstream subtrees. The normal validation plan returns before
historical context is calculated.

These are historical visibility populations, not movement candidates. Regional
Read/Gate/Write history is exposed only where the documented Homeostatic/context
contract requires it. No alternative enters Traverse merely because it has the
same producing position, region or Horizon. Actionability requires the operation's
structural authority; movement additionally requires the current Gate's causal
continuation. No edge means no movement.

Every return to Origin Distinguish exposes exhausted direct first-register
excursions in the Cycle's Domain (Signal or Activity). Persist replaces those
with exhausted alternatives at its own local Write position. A repeated register
label never grants historical relevance across producing positions. A current
exhausted position awaiting Rest is itself represented as Gate context; persistent
Commits are marked `basin_exhausted`, never `exhausted`.

There is no arbitrary first-25 cutoff for relevant scars. As with the active
lineage, `max_read_count` only bounds broader human register browsing. Packet size
can grow with the number of local alternatives, but not with the size of the
historical subtrees below them. Existing active option/schema populations retain
their strict eligibility semantics. No historical ID enters receipt targeting,
Traverse, Attest, Instrument, Revise, Reconcile, Bias, or Commit because it is visible.

The workspace shows these excerpts within their actual Read/Gate/Write lane, with
an exhausted context label and no controls. Previewing an earlier phase does not
reuse the current phase's scars. Graph observer modes remain separate. The human
participant view subscribes to its current Horizon through the shared Reverb
transport, so another participant's proposition status update refreshes local
context even while this viewer remains still. Events retain identifier-only
payloads; no history is broadcast.

