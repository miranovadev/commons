# Receipt regions, participant memory and active evidence

Receipts ground Propositions. Attestations express Participant judgments over those Propositions.

| Boundary | Responsibility |
| --- | --- |
| Context Horizon | Existing structural and measurement boundary |
| Commit Region | Ordinary shared discovery and one-use consumption |
| Participant load | Persistent, participant-specific Reinforcement |
| Participant-facing Read | Activation of full loaded evidence |
| Tragedy recovery path | Tragedy Receipt's retained moving scope until consumption |

## Evidence and boundary warrants

A Tragedy Receipt is not ordinary evidence. It is a dedicated, un-loadable,
non-replayable warrant authorizing one eligible foreign homologous Bias
Reclassification out of its immutable owning Commit Region. It does not satisfy
Instrument completion, Attestation, ordinary Reclassification, or generic
shared-evidence requirements. It requires no participant load state.

`HorizonReceiptService::ordinaryAvailable()` supplies ordinary evidence only.
`tragedyWarrant()` resolves the exact current-Region, unresolved, unconsumed
warrant, checking immutable ownership as well as current scope.
`catalogue()` is a presentation union only; its visibility is never evidence
or permission. `BiasReceiptAuthorityService` retains the Stage 4 source split,
including ordinary precedence for recorded mediated sources and same-Region
expressive variation. Constrain and every non-Bias Reclassify use ordinary
Receipts only, enforced by both schema and transactional consumption.

Proposition evidence excludes Tragedy in both hydrated and database paths.
Tragedy has no evidence signature, active evidence body, replay invocation or
Reinforcement. Participant context labels it
`purpose: homologous_boundary_escape`; catastrophe details are separate warrant
provenance. Historical load and consumption rows remain auditable, but cannot
supply ordinary evidence or create a new live load. Normal evidence production,
loading, replay, Attestation and measurement weighting are otherwise unchanged.

## Ordinary discovery and ownership

An ordinary Receipt's regional identity is `Receipt → InstrumentExecution → observed Proposition → CommitRegionService → governing Committal`. Attachment never changes that origin. There is no new region table or persisted seed column. A packet reuses one hydrated graph and the same memoized batched entry map as the motor. A Committal owns itself; an expressive observed Proposition belongs to its creating Cycle's immutable entry Committal. Missing or invalid Cycle entry fails closed. Foreign causal inputs neither change this owner nor make their Receipts available.

An unconsumed completed observation is catalogued only at the same governing Committal, with the existing Horizon/qualification checks. In particular, bootstrap evidence originating in a preceding Horizon does not become a new shared observation merely because its Committal opens the next Horizon. Branches and reconciliation branches within a region share the available pool. A later Commit begins a different pool. Reclassification still consumes once and records its immutable Proposition link; no availability fallback traverses the Horizon.

New Instrument Receipts always own the observed Proposition. Legacy Attestation-owned rows resolve through `Attestation.proposition_id`, including superseded judgments. Their IDs, ledger entries, load contexts, consumption links and bodies stay unchanged. Observation subject, discovery origin, attachment target and participant load are distinct.

## Memory, visibility and weighting

Receipt metadata in inference is exactly `id`, `type`, `reference`, `loaded`. Type preserves existing schema/UI semantics. Full bodies appear once in the existing loaded evidence prefix only when the Receipt is loaded and grounds the current Read. No Gate, Write, catalogue or Lineage occurrence injects a body. Persistent loads remain true outside Read; visibility never enters a measurement formula. No new summarisation or truncation applies.

Reinforcement retains the existing participant-specific uniqueness and cap: multiple qualifying Receipts supporting the same Participant/Proposition do not multiply that Participant's existing weight. Unloading the last qualifying load removes that Participant's current contribution; another Participant is unaffected. An Attestation uses its author's qualifying Proposition loads. Supersession changes the effective judgment while retaining that author's memory; ownership never moves between judgments.

## Gate desired states

A successful forward Phase contribution may include `receipt_options: {"load": ["id"], "unload": []}`. The authoritative schema enumerates the same exact eligible Gate reference IDs in both arrays, permitting already-satisfied desired states. The server rejects overlap, malformed IDs, non-Gate catalogue items, foreign regions and Tragedy Receipts. The causal Gate is authoritative even at Mediate; reconciliation counterparts cannot add choices.

The existing Substrate → Participant → Phase lock order and contribution transaction cover both operations. Invalid contribution or options rolls back both. Existing load/clear events and authoritative measurement invalidation/recalculation apply only to actual transitions. The accepted action still produces one normal Phase transition. Phase N+1 observes the new state; Phase N is not inferred again. `participant.rest` does not accept `receipt_options`. Rest retains its existing recovery/proof behavior and cannot alter Receipt load state. There are no standalone Receipt Read/Replay participation actions. Replay uses `instrument.execute` with `instrument.replay_receipt_id` and the exact eligible Proposition target; it restores the original invocation and creates a new Receipt with provenance, without loading either source or result. Equivalent evidence exclusions remain authoritative.

## Tragedy scope

A Tragedy Receipt records the first zero-Charge expressive birth in its governing Commit Region. Its catalogue transport behavior remains transitional pending Stage 6; it does not confer Bias authority. When legitimate backward-lineage recovery retreats from that region, the Receipt follows the exact selected destination, regardless of which Participant recovered. `tragedies.participant_id` remains immutable provenance of the original trigger, never ownership or exclusive transport authority. Participants in that destination may still discover it through the legacy catalogue. For Bias, however, the Receipt must belong to that exact Region’s unresolved Tragedy as well as be scoped there; relocation does not make it a new Region’s escape warrant.

`tragedy_receipt_scopes` stores only this mutable Tragedy basin and its latest recovery-entry Phase witness. Cycle entry updates it under the existing Substrate/Participant transaction after the recovery engine selects the destination. The source must be its current basin; the destination must lie on that recovery's backward lineage. Nearest-Committal recovery cannot skip an unresolved nearer basin. The Receipt cannot go farther than the actual destination and never influences selection. Historical recovery witnesses retain their recorded destinations. Initial entries, normal movement/Commit and recovery from unrelated regions cannot transport it. Consumption excludes it from all later movement; Receipt, Tragedy, consumption and historical final-attempt rows remain unchanged. Rest never creates Tragedy. The retained exhaustion/recovery lifecycle awaits Stage 6.

## Compatibility and rollout

Ordinary Receipt rows are interpreted in place without backfill. The additive `2026_09_21_000100_project_tragedy_receipt_basins` migration initializes only the Tragedy scope projection. Under each Substrate lock it replays existing post-Rest recovery entries from all Participants in chronological ULID order, matching each source against the current basin and checking backward Committal ancestry. Consumed Receipts initialize from their immutable consumption region and never replay later movement. IDs, ledger entries, trigger provenance, load contexts and all proof rows are untouched. Run migrations with workers paused as for the existing measurement rollout.

Malformed ancestry or legacy entries with ambiguous equal-millisecond ordering leave `current_committal_id` null, withholding catalogue availability rather than guessing. Such rows are identifiable with `SELECT tragedy_id FROM tragedy_receipt_scopes WHERE current_committal_id IS NULL` and require review of the recorded recovery witnesses; historical evidence remains readable. Missing projections also fail closed. This mutable projection is exclusively for Tragedy; ordinary Receipt identity is derived from immutable Cycle entry. Ambiguous legacy origins remain readable through Proposition references and existing participant memory but cannot enter a shared available catalogue or Gate mutation set. Recovery scope also fails closed on invalid lineage. Production data has not been accessed or rewritten by this change.

The original ownership rollout used measurement version `proposition-receipts-v9`; the current Stage 5 version and refresh guidance are below. Use the existing bounded `commons:measurements-repair <substrate> --limit=100` maintenance command until obsolete projections are repaired, then `commons:potential-rebuild <substrate>` before resuming participation. Existing lazy observation/mutation paths recognize obsolete versions; immutable Commitment snapshots remain frozen. No ad hoc Closure/Chirality update or ledger rewrite is introduced.

## Validation and boundedness

Focused Core proofs cover locality, malformed origins, ownership/legacy interpretation, exact Gate enums, atomic rollback, idempotency, Read-only activation and participant-specific weighting. Integration covers load/unload across the normal Phase boundary and preserves the natural shared Tragedy journey's actors, actions and exhaustion outcome.

Local SQLite measurement: catalogue growth from one to thirteen ordinary Receipts uses exactly five queries with a supplied ParticipationContext. Batched region derivation on the six-node fixture took 0.24–0.69 ms (illustrative local timings, not a production latency bound). Region derivation is once per context, not one ancestry walk per Receipt. Hydration still scales with substrate history, as before. Tragedy scope uses one memoized indexed projection query per context, with no recovery-history scan or per-Receipt ancestry walk. Recovery validates one actual route only when an unresolved scope occupies its departing region. A twelve-Receipt Gate fixture retains three persistent loads and twelve references, but zero active bodies: the evidence wrapper is 22 bytes and the evidence-plus-dynamic prompt is 20,188 bytes. The internal qualification packet is 66,458 bytes; its bodies do not enter cognition. Larger candidate-growth checks retain constant Mediate/Bias/Order query counts. Query Regression and Load/Concurrency remain required validation gates for this change.

The historical transcript uses legal forward Gate loading. It is now a regression that zero-Potential Rest does not create Tragedy. Its [load relocation record and boundary comparison](evidence/receipt-tragedy-transcript.md) document the retired interactions, the alternate pre-Tragedy evidence attachment, and the unchanged final-attempt/Exhaustion recovery proof.


## Mediated incoming options

A Bias Gate's immediate Mediate provenance can identify the exact operand of a
recorded incoming Reconcile. Exposing its result adds a Reclassify source, not
Receipt scope. Foreign mediated sources are excluded from option evidence
catalogues and their Receipt metadata is not offered for loading or consumption.
The selected Receipt still belongs to the acting Cycle's Commit Region. Ordinary
same-Region evidence remains available. This adds no Tragedy requirement and
cannot consume Tragedy Receipts. Foreign homologous escape instead requires the acting Region’s exact unresolved Tragedy Receipt. Source-specific schema branches and transactional server validation enforce the pairing.

## Stage 5 compatibility and rollout

No migration deletes or reinterprets historical Receipt, load, consumption,
Tragedy or scope records. Existing scope projection and exhaustion authority
remain unchanged pending Stage 6. Pre-Stage-5 non-Bias Tragedy consumption is
retained only as archived test/migration data via an explicitly named fixture;
the current dispatcher cannot create it. Archived fixture insertion emits no
live consumption event. New homologous Bias consumption uses the existing
transaction, locks, uniqueness and after-commit events.

The measurement cache version is `ordinary-receipt-evidence-v11` so historical
Tragedy load weights cannot survive as cached live evidence. Ordinary formulas,
Charge, Potential and immutable Commitment snapshots are unchanged. Use the
existing bounded `commons:measurements-repair <substrate> --limit=100` and then
`commons:potential-rebuild <substrate>` maintenance workflow to refresh stored
projections before resuming participation. No production data was modified by
this implementation.
