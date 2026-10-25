# Participant-relative exploration

Stage B replaces shared continuation failures and Exploration Epochs on a **fresh database**. Authority: Claire's approved 25 October 2026 handoff, “Exploration Events, Active Heads and Recovery”, and the ten Core Invariants. The separate rolling Attestation Cadence correction is outside this change.

An immutable Exploration Event records one Participant's completed Rest encounter at an expressive continuation head. Its creating Phase establishes the Participant, Cycle, entry Committal and Context Horizon. The witness preserves arrival, judgment, evidence and the guarded absence of meaningful continuation. Events do not mutate Propositions, retire Regions, change another Participant's options, or assert global failure.

Current status is derived. Follow the complete authorized forward frontier within the acting Cycle's Region and Horizon; stop at Committal boundaries. Hard-superseded inputs are history, not independent heads. Other Reconcile operands, homologous comparison members and foreign selected sources do not grant movement. An ancestor is explored only when every reachable active head has an applicable personal encounter and no newly lawful advancing move. A new Cycle or field version is not an exploration reset.

Base action lawfulness precedes personal selection. Ordinary Attestation reversals remain lawful. They do not alone renew exploration or prevent Variant forever. Existing traversal opportunities use personal exploration; concrete Revise, Reconcile, Reclassify and Commit opportunities retain their existing structural, measurement, evidence and lifecycle authority. Fresh Variant is excluded from its own prerequisite frontier and is considered only after existing relevant heads are explored.

Rest publication is atomic with its existing completion transaction and unique by Rest Phase. Stale, rolled-back, incomplete, untested, evidence-starved or Preparation-only encounters do not establish exploration. An event remains immutable after a later continuation releases its current restriction. Personal state notifications refresh the actor; existing structural and evidentiary notifications still refresh their ordinary consumers.

## Semantic boundary register

| Boundary | Resolution | Authority / status | Verification |
| --- | --- | --- | --- |
| H1, repeat judgments | Reversal remains lawful; only an advancing opportunity reopens | Handoff §3, documented | Reversal and enabled-progression cases |
| Variant circularity | Existing continuation frontier first, exclude fresh Variant | §4–5, documented | Personal Variant ordering / concurrent writes |
| Branch propagation | All authorized active heads, not one failed descendant | §2, documented | Two heads, revisions, new head, inaccessible branch |
| Participant identity | Rest Phase owns history; another actor cannot inherit it | §1, documented | Independent actors / different Cycle intervals |
| Arrival authority | Recorded forward arrival, exact Region/Horizon, no foreign-operand shortcut | §2, §7, documented | Reconcile and recovery cases |
| Event integrity | Append-only, one Phase, genuine tested dead end, transaction guard | §6, documented | Rollback, stale, idempotence and immutability |
| Terminal lifecycle | Tragedy, Resolution and regional Exhaustion remain independent | §8, §11, documented | Existing lifecycle regression suites |
| Mathematics / evidence | No changes to calculations, snapshots, allowances or Receipt authority | §11, documented | Phase Profile, C3, Commit, Receipt and Preparation suites |
| Migration strategy | Remove obsolete exploration schema; no old-state backfill | §8, documented | Fresh SQLite and PostgreSQL migration tests |

## Implementation and validation

Implementation and test results are recorded with the PR. Historical epoch-based reports describe the previous system and are not current eligibility contracts. The active source-provenance resolver is `StructuralSourceService`; no epoch identity or global spent predicate remains.
