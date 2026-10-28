# Receipt regions, participant memory and active evidence

> **Current cognition:** [Gate-causal Receipt cognition](gate-causal-receipt-cognition.md)
> separates the causal cognitive catalogue from the available operational catalogue
> described here. Consumption does not hide observations. Summaries are always
> visible, and direct Read consumption can expose an observation transiently
> without changing personal loads, Reinforcement or any operational eligibility.

> **C2 update:** [Participant Context C2](participant-context-c2-implementation.md)
> supersedes the inference Receipt layout and loading timing described below.
> New readable Instrument Receipts auto-load for their performer; eligible local Write
> loads and personal same-substrate unloads are supported. Loaded Read/Gate
> content appears once in the top-level catalogue; Write alone exposes metadata.
> Operational evidence, causal authority and measurement formulas are unchanged.

> **Receipt authority remains narrow.** The proposed
> [Resolution lifecycle](tragedy-resolution-target.md) changes the Tragedy
> trigger from fresh birth to direct zero-Charge Write encounter, not the
> owning-Region warrant's scope, one-use authority, evidence neutrality or
> historical persistence. The fresh-birth descriptions below reflect
> currently deployed behaviour.

Receipts ground Propositions. Attestations express Participant judgments over those Propositions.

| Boundary | Responsibility |
| --- | --- |
| Context Horizon | Existing structural and measurement boundary |
| Commit Region | Ordinary shared discovery and one-use consumption |
| Ordinary evidence load | Persistent, participant-specific Reinforcement |
| Tragedy memory load | Persistent cognitive context, never evidence or Reinforcement |
| Participant-facing Read | Activation of full loaded evidence |
| Tragedy scope | Owning Region; recovery does not transport authority |

## Evidence and boundary warrants

A Tragedy Receipt is not ordinary evidence. It is a dedicated,
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
supply ordinary evidence. New loads are memory-only, with a null evidence signature. Normal evidence production,
loading, replay, Attestation and measurement weighting are otherwise unchanged.

## Tragedy memory (Stage 7)

A Tragedy Receipt may be loaded as participant memory of the committed lineage
that led to catastrophe. This memory is cognitive context only: it supplies no
Reinforcement, ordinary evidence, replay authority or local classification
authority. Its separate live authority remains the one-shot foreign homologous
Bias boundary warrant of its owning Commit Region.

Load means remember. Consume means cross an eligible boundary. Reinforce: never.

`receipt_options.load` and `.unload` on a successful forward contribution accept
ordinary qualifying Gate references **or** scoped Tragedy memory. Tragedy memory
does not pretend to be a Gate reference. Initial admission uses the existing
Region/substrate catalogue. Loading is neither required for nor sufficient for
escape. Rest still accepts no Receipt options; no standalone action is added.

Tragedy loads persist in `ParticipantReceiptContext` with `evidence_signature =
null`. Type explicitly selects memory mode, including for archived non-null
signatures, which remain untouched but never become evidence. The plan exposes
`load_mode: memory`, `memory_active`, and `active: false`. Only loaded memories
include `tragedy_memory`; this packet is separate from `loaded_receipts` evidence.
Consumption, exhaustion and recovery retain already-loaded same-substrate
memory until explicitly unloaded. They do not make a foreign warrant actionable.
After unloading, a historical Receipt outside the current catalogue cannot be
newly loaded by guessing its ID.

`TragedyMemoryService` follows the canonical creating-Cycle entry Committal edge,
as used by `CommittalLineageService`. It never follows Supersessions or foreign
causal inputs. The ordered chain ends at the owning Committal and starts at the
current committed ancestor when present. From a proven committed descendant it
walks backward to the owner instead, explicitly marked `chain_direction:
backward`; this recalls the prior catastrophe, not a new event. Otherwise it
starts at the nearest canonical root available. Frozen snapshot Domain, Depth, Cadence and Tension are
paired with immutable Committal statements. The explicit terminal catastrophe
uses the Receipt's **recorded** field, never current measurements. Archived
Receipts lacking that field return null values and `recorded_field_available:
false`; no boundary is reconstructed.

The projection performs two queries regardless of chain depth, examining at most
32 committed states from each of the owner and current entry seeds. The packet
contains at most 32 states, always ending at the owner. A fallback retains the
nearest 32 to the owner in ancestral order.
`earlier_history_omitted` explicitly marks a capped or incomplete earlier chain;
`starts_at_current_committal` distinguishes a proven current-context start.
It does not hydrate the expressive graph. Load/unload events remain ledgered but
do not dirty field projections, recalculate observables or change calculator
version. Both database and context-backed Reinforcement continue excluding
Tragedy by type. Ordinary load events retain their existing behavior.

## Ordinary discovery and ownership

An ordinary Receipt's regional identity is `Receipt → EvidenceExecution → observed Proposition → CommitRegionService → governing Committal`. Attachment never changes that origin. There is no new region table or persisted seed column. A packet reuses one hydrated graph and the same memoized batched entry map as the motor. A Committal owns itself; an expressive observed Proposition belongs to its creating Cycle's immutable entry Committal. Missing or invalid Cycle entry fails closed. Foreign causal inputs neither change this owner nor make their Receipts available.

An unconsumed completed observation is catalogued only at the same governing Committal, with the existing Horizon/qualification checks. In particular, bootstrap evidence originating in a preceding Horizon does not become a new shared observation merely because its Committal opens the next Horizon. Branches and reconciliation branches within a region share the available pool. A later Commit begins a different pool. Reclassification still consumes once and records its immutable Proposition link; no availability fallback traverses the Horizon.

New Instrument Receipts always own the observed Proposition. Legacy Attestation-owned rows resolve through `Attestation.proposition_id`, including superseded judgments. Their IDs, ledger entries, load contexts, consumption links and bodies stay unchanged. Observation subject, discovery origin, attachment target and participant load are distinct.

## Memory, visibility and weighting

Ordinary Receipt metadata in inference is exactly `id`, `type`, `reference`, `loaded`. Type preserves existing schema/UI semantics. Full bodies appear once in the existing loaded evidence prefix only when the Receipt is loaded and grounds the current Read. No Gate, Write, catalogue or Lineage occurrence injects a body. Persistent loads remain true outside Read; visibility never enters a measurement formula. No new summarisation or truncation applies.

Reinforcement retains the existing participant-specific uniqueness and cap: multiple qualifying Receipts supporting the same Participant/Proposition do not multiply that Participant's count. Unloading the last qualifying load removes that Participant's current contribution; another Participant is unaffected. Attestations contribute equally to lean under fixed Commit Region polarity. Receipt loading never changes a judgment's contribution. Supersession changes the effective judgment while retaining that author's memory; ownership never moves between judgments.

## Gate desired states

A successful forward Phase contribution may include `receipt_options: {"load": ["id"], "unload": []}`. The authoritative schema enumerates exact eligible ordinary Gate reference IDs plus scoped Tragedy memory IDs in both arrays, permitting already-satisfied desired states. The server rejects overlap, malformed IDs and unadmitted references. Ordinary evidence remains Gate/Region-restricted, including at Mediate; reconciliation counterparts cannot add evidence choices. Tragedy admission follows its separate memory rule above.

The existing Substrate → Participant → Phase lock order and contribution transaction cover both operations. Invalid contribution or options rolls back both. Existing load/clear events apply only to actual transitions. Only ordinary evidence transitions invalidate/recalculate measurements; memory transitions never do. The accepted action still produces one normal Phase transition. Phase N+1 observes the new state; Phase N is not inferred again. `participant.rest` does not accept `receipt_options`. Rest retains its existing recovery/proof behavior and cannot alter Receipt load state. There are no standalone Receipt Read/Replay participation actions. Replay uses `instrument.execute` with `instrument.replay_receipt_id` and the exact eligible Proposition target; it restores the original invocation and creates a new Receipt with provenance, without loading either source or result. Equivalent evidence exclusions remain authoritative.

## Tragedy scope

A Tragedy Receipt records the first fresh zero-Charge expressive birth in its governing Commit Region. New `tragedy_receipt_scopes` rows initialize to that immutable owner. Recovery no longer moves them. Archived relocated scopes and recovery witnesses remain readable; catalogue visibility never supplies another Region’s escape authority. The owning Region’s exhaustion immediately removes live warrant authority. No historical scope, load or consumption row is deleted.

## Compatibility and rollout

Ordinary Receipt rows are interpreted in place without backfill. The additive `2026_09_21_000100_project_tragedy_receipt_basins` migration initializes only the Tragedy scope projection. Under each Substrate lock it replays existing post-Rest recovery entries from all Participants in chronological ULID order, matching each source against the current basin and checking backward Committal ancestry. Consumed Receipts initialize from their immutable consumption region and never replay later movement. IDs, ledger entries, trigger provenance, load contexts and all proof rows are untouched. Run migrations with workers paused as for the existing measurement rollout.

Malformed ancestry or legacy entries with ambiguous equal-millisecond ordering leave `current_committal_id` null, withholding catalogue availability rather than guessing. Such rows are identifiable with `SELECT tragedy_id FROM tragedy_receipt_scopes WHERE current_committal_id IS NULL` and require review of the recorded recovery witnesses; historical evidence remains readable. Missing projections also fail closed. This mutable projection is exclusively for Tragedy; ordinary Receipt identity is derived from immutable Cycle entry. Ambiguous legacy origins remain readable through Proposition references and existing participant memory but cannot enter a shared available catalogue or Gate mutation set. Recovery scope also fails closed on invalid lineage. Production data has not been accessed or rewritten by this change.

The original ownership rollout used measurement version `proposition-receipts-v9`; the current Stage 5 version and refresh guidance are below. Use the existing bounded `commons:measurements-repair <substrate> --limit=100` maintenance command until obsolete projections are repaired, then `commons:potential-rebuild <substrate>` before resuming participation. Existing lazy observation/mutation paths recognize obsolete versions; immutable Commitment snapshots remain frozen. No ad hoc Closure/Chirality update or ledger rewrite is introduced.

## Validation and boundedness

Focused Core proofs cover locality, malformed origins, ownership/legacy interpretation, exact Gate enums, atomic rollback, idempotency, Read-only activation and participant-specific weighting. Integration covers load/unload across the normal Phase boundary and covers the fresh first/later boundary lifecycle and ordinary evidence behavior.

Local SQLite measurement: catalogue growth from one to thirteen ordinary Receipts uses exactly five queries with a supplied ParticipationContext. Batched region derivation on the six-node fixture took 0.24–0.69 ms (illustrative local timings, not a production latency bound). Region derivation is once per context, not one ancestry walk per Receipt. Hydration still scales with substrate history, as before. Tragedy scope uses one memoized indexed projection query per context, with no recovery-history scan or per-Receipt ancestry walk. Recovery does not scan or update Receipt scope. A twelve-Receipt Gate fixture retains three persistent loads and twelve references, but zero active bodies: the evidence wrapper is 22 bytes and the evidence-plus-dynamic prompt is 20,188 bytes. The internal qualification packet is 66,458 bytes; its bodies do not enter cognition. Larger candidate-growth checks retain constant Mediate/Bias/Order query counts. Query Regression and Load/Concurrency remain required validation gates for this change.

The historical transcript uses legal forward Gate loading. It is now a regression that zero-Potential Rest does not create Tragedy. Its [load relocation record and boundary comparison](evidence/receipt-tragedy-transcript.md) document the retired interactions, the alternate pre-Tragedy evidence attachment, and archived pre-correction proof. Current catastrophe acceptance is described in [the boundary lifecycle](branch-tragedy.md).


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
retain historical compatibility; recovery no longer relocates live scope. Pre-Stage-5 non-Bias Tragedy consumption is
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
