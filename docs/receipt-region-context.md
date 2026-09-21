# Receipt regions, participant memory and active evidence

Receipts ground Propositions. Attestations express Participant judgments over those Propositions.

| Boundary | Responsibility |
| --- | --- |
| Context Horizon | Existing structural and measurement boundary |
| Commit Region | Ordinary shared discovery and one-use consumption |
| Participant load | Persistent, participant-specific Reinforcement |
| Participant-facing Read | Activation of full loaded evidence |
| Tragedy recovery path | Shared final-attempt Receipt's moving scope until consumption |

## Ordinary discovery and ownership

An ordinary Receipt's regional identity is `Receipt → InstrumentExecution → observed Proposition → CommitRegionService → governing Committal`. Attachment never changes that origin. There is no new region table or persisted seed column. A packet reuses one hydrated graph and a memoized batched seed map, with a linear completeness check. Cycles, orphan paths, missing parents and multiple first Committals fail closed for discovery, without changing the motor's resolver.

An unconsumed completed observation is catalogued only at the same governing Committal, with the existing Horizon/qualification checks. In particular, bootstrap evidence originating in a preceding Horizon does not become a new shared observation merely because its Committal opens the next Horizon. Branches and reconciliation branches within a region share the available pool. A later Commit begins a different pool. Reclassification still consumes once and records its immutable Proposition link; no availability fallback traverses the Horizon.

New Instrument Receipts always own the observed Proposition. Legacy Attestation-owned rows resolve through `Attestation.proposition_id`, including superseded judgments. Their IDs, ledger entries, load contexts, consumption links and bodies stay unchanged. Observation subject, discovery origin, attachment target and participant load are distinct.

## Memory, visibility and weighting

Receipt metadata in inference is exactly `id`, `type`, `reference`, `loaded`. Type preserves existing schema/UI semantics. Full bodies appear once in the existing loaded evidence prefix only when the Receipt is loaded and grounds the current Read. No Gate, Write, catalogue or Lineage occurrence injects a body. Persistent loads remain true outside Read; visibility never enters a measurement formula. No new summarisation or truncation applies.

Reinforcement retains the existing participant-specific uniqueness and cap: multiple qualifying Receipts supporting the same Participant/Proposition do not multiply that Participant's existing weight. Unloading the last qualifying load removes that Participant's current contribution; another Participant is unaffected. An Attestation uses its author's qualifying Proposition loads. Supersession changes the effective judgment while retaining that author's memory; ownership never moves between judgments.

## Gate desired states

A normal contribution may include `receipt_options: {"load": ["id"], "unload": []}`. The authoritative schema enumerates the same exact eligible Gate reference IDs in both arrays, permitting already-satisfied desired states. The server rejects overlap, malformed IDs, non-Gate catalogue items, foreign regions and Tragedy Receipts. The causal Gate is authoritative even at Mediate; reconciliation counterparts cannot add choices.

The existing Substrate → Participant → Phase lock order and contribution transaction cover both operations. Invalid contribution or options rolls back both. Existing load/clear events and authoritative measurement invalidation/recalculation apply only to actual transitions. The accepted action still produces one normal Phase transition. Phase N+1 observes the new state; Phase N is not inferred again. Existing standalone Read/Replay contracts remain available where previously eligible.

## Tragedy scope

A Tragedy Receipt is a shared final-attempt allowance, not a private Participant item. Its still-unconsumed effective region follows the triggering Participant's recorded recovery entries after Rest. Any Participant in that destination region can discover and exercise it through existing Reclassification/final-attempt rules. Other regions cannot. An attenuated selection may skip a nearer Committal; the Receipt follows the selected destination without influencing selection.

Scope uses existing Cycle/Phase entry records and validates the destination against the triggering Participant's backward lineage. Initial entries, another Participant's recovery and normal post-Commit rollovers do not transport it. Further recovery before consumption updates effective scope. Consumed Receipts are excluded from movement; immutable consumption and final-attempt proofs remain authoritative. Tragedy exact-once, later no-Potential Rest and Exhaustion rules are unchanged.

## Compatibility and rollout

No Receipt migration/backfill is required. Historical rows are interpreted in place. Ambiguous legacy origins remain readable through Proposition references and existing participant memory but cannot enter a shared available catalogue or Gate mutation set. Recovery scope also fails closed on invalid lineage. Production data has not been accessed or rewritten by this change.

`LineageMeasurementService::VERSION` becomes `proposition-receipts-v9` because ownership lookup and participant attribution changed. Use the existing bounded `commons:measurements-repair <substrate> --limit=100` maintenance command until obsolete projections are repaired, then `commons:potential-rebuild <substrate>` before resuming participation. Existing lazy observation/mutation paths recognize obsolete versions; immutable Commitment snapshots remain frozen. No ad hoc Closure/Chirality update or ledger rewrite is introduced.

## Validation and boundedness

Focused Core proofs cover locality, malformed origins, ownership/legacy interpretation, exact Gate enums, atomic rollback, idempotency, Read-only activation and participant-specific weighting. Integration covers load/unload across the normal Phase boundary and preserves the natural shared Tragedy journey's actors, actions and exhaustion outcome.

Local SQLite measurement: catalogue growth from one to thirteen ordinary Receipts uses exactly five queries with a supplied ParticipationContext. Batched region derivation on the six-node fixture took 0.24–0.69 ms (illustrative local timings, not a production latency bound). Region derivation is once per context, not one ancestry walk per Receipt. Hydration still scales with substrate history, as before. Tragedy scope adds recovery-history queries only when an unconsumed Tragedy exists. A twelve-Receipt Gate fixture retains three persistent loads and twelve references, but zero active bodies: the evidence wrapper is 22 bytes and the evidence-plus-dynamic prompt is 20,188 bytes. The internal qualification packet is 66,458 bytes; its bodies do not enter cognition. Larger candidate-growth checks retain constant Mediate/Bias/Order query counts. Query Regression and Load/Concurrency remain required validation gates for this change.
