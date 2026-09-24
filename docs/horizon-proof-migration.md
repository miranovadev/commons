# Horizon proof migration and compatibility

> Historical checkpoint: the Rest-born Tragedy and special final-attempt allowance
> described below are superseded by [Charge-boundary Tragedy](branch-tragedy.md).
> Legacy exhaustion/recovery remains pending its later correction stage.

This is a semantic rollout requiring review of the complete integration PR. No migration deletes legacy Propositions, Receipts, attachments, snapshots, old Tragedies or pruning history.

| Additive migration | Data treatment |
| --- | --- |
| `2026_09_18_000100` | Add Receipt Horizon ownership from InstrumentExecution → Phase → Cycle. Create authoritative `receipt_consumptions`. Reserve the earliest legacy reclassification attachment as one legacy consumption; preserve every old link. |
| `2026_09_18_000200` | Add nullable backend Rest reason. Historical Rests remain unknown; no failure reason is invented. |
| `2026_09_18_000300` | Permit Tragedy Receipts without InstrumentExecution, retain creating Phase, add Horizon Tragedy/failure witnesses/local failure records and exact recovery Proposition. Preserve causal ownership triggers. |
| `2026_09_18_000500` | Index Horizon-scoped chronological Receipt lookup for PostgreSQL. |
| `2026_09_18_000400` | Add final-attempt Exhaustion proof. Copy old Horizon `exhausted_at` to `legacy_exhausted_at`, clear its operational authority, dirty all affected Horizon projections and increment field versions. |

Legacy eager closure cannot prove the new final-attempt invariant. Old `exhaustion_states`, `pruned_at`, `legacy_retired_at`, old Origin `tragedies` (now `legacy_origin_tragedies`) and archived Horizon timestamps remain historical only. Previously closed structure may become eligible again until a new authoritative proof occurs. This compatibility choice is deliberate and requires operator review; it does not silently reinterpret old events as new Tragedies or invent Tragedy Receipts.

Existing Receipt links may show several historical classifications using one observation. The earliest attachment reserves it operationally so it cannot be consumed again. A legacy reservation never counts as exercising a newly generated Tragedy Receipt. Unresolvable historical ownership remains null and therefore unavailable; no owner is guessed.

New proof relations have restrictive foreign keys and unique keys. Runtime consumption and failure publication are transactional. Rollback refuses to discard new consumption, Tragedy or Exhaustion proof history; recovery after live use requires a reviewed forward repair or a pre-rollout backup. Snapshot/back up before applying production migrations. Apply the stack as one coordinated application/schema release and allow projection maintenance to complete before participation resumes.

The backfill performs bounded batches and local joins. It runs once during migration; ordinary participation has no graph-wide proof scan. No production rollout or main merge is performed by this refactor.

## Semantic clarifications and compatibility assumptions

- A grounded, effectively attested Gate is required for an actionless state to evidence no remaining Potential. Other actionless states remain `no_available_move`.
- Confirmed Variant clarification: each viable Variant needs its own completed Instrument and effective Attestation, and positive Commit Region Potential Charge must remain before another Variant becomes an option. Every new Variant repeats this sequence. No prior Mediate Rest, Participant quorum or fixed sibling cap. Mediate still requires two Propositions.
- The Origin Horizon may eventually exhaust under the same proof. Origin itself and its historical Committals persist permanently; no synthetic renewal branch is invented.
- Tragedy Receipt metadata contains IDs only. Loaded context resolves full statements and flat local evidence by ID; no recursive subtree is embedded or historical age cutoff introduced.

## Regional authority migration

The later `2026_09_22_000000` migration supersedes Horizon-wide exhaustion authority. It retains the old proof rows as evidence and promotes only verifiable single-region proofs. See [Commit Region exhaustion and compatibility](commit-region-exhaustion.md) before deployment. Horizon opening-region state derives from regional proof without a stored Horizon timestamp and does not retire other live Commit Regions. Active Tragedies are stored in `tragedies`; unmatched old timestamps remain archival evidence.
