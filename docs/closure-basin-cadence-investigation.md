# Committal ownership and basin-local Cadence

This is the investigation checkpoint for the new Closure baseline. The ownership
change is implemented; the proposed Cadence calculator below is diagnostic only.
The existing live Closure calculator has not yet been replaced.

## Cycle ownership

Ordinary live entry records `cycles.governing_committal_id` from the selected
Committal. `context_horizon_id` remains the immutable Domain/topology container.
Origin bootstrap is the only ungoverned production entry. Completing, resting or
recovering never relabels the old Cycle. A subsequent Cycle records its newly
selected governor. The nullable storage supports Origin creation before the
Origin proposition exists; ordinary production creation requires a Committal.

The new migration resolves ownership from each Cycle's original Distinguish Gate,
validates committed provenance and substrate/Horizon compatibility, and rejects
unresolved records. It does not infer an owner from the participant's current
position, a Horizon's latest Commit, sibling creation order or timestamps.
Eloquent and database triggers protect both non-null ownership and bootstrap null
from updates. A foreign key protects the referenced proposition.

## Proposed interval

For each ancestral pair A -> B, freeze at B's successful Commit:

```
interval(A, B) = count of completed Cycles with governing_committal_id = A
                before B is produced
```

This counts all participants and expressive paths in A's basin. It excludes
Cycles governed by other Committals, including A's descendants and siblings.
Rest counts as completed participation in its original basin. Returning to A
does not reset its history. If A later produces another successor B2, its interval
includes the accumulated completed Cycles under A up to B2, while the earlier
A -> B interval remains frozen.

The current write order creates the CommitmentSnapshot before completing the
producing Cycle. Under the literal "completed before" definition, B's producing
Cycle does not count. Cycles that remain open also do not count. Therefore an
interval can be zero. The existing similarity helper returns +1 for (0, 0) and
-1 when exactly one operand is zero; those edge cases must be explicit in the
new calculator's tests, rather than adding an implicit +1 to every interval.

For C0 -> C1 -> C2, compare interval(C0, C1) with interval(C1, C2).
Follow each committed proposition's creating Cycle governor to obtain the actual
Committal chain. No union of Supersession inputs or Horizon population is needed.
The chain and the interval comparison continue across Domain boundaries.

The accepted symmetric bootstrap remains:

| Position | Depth | Cadence |
| --- | ---: | ---: |
| Origin C0 | -1 | -1 |
| Immediate descendant C1 | 0 | 0 |
| C2 onward | Measured Domain-run similarity | Measured basin-interval similarity |

Ordinary zero-value hysteresis and directional alignment remain mandatory.

## Persistence and concurrency requirements for the calculator

Store the interval on the successor's immutable Commit record, including C1 even
though its displayed Cadence is bootstrap zero. Recounting an old basin when
measuring a descendant would let later participation rewrite history.

The preview and persisted candidate must read the same basin count from the same
validated field state. Existing contribution locking serializes writes; Cycle
completion must also invalidate the affected measurement/admissibility state,
including Rest without a new ledger contribution. A stale preview must retry
before publication. A count change must not be hidden by request/projection caches.

No substrate-wide sequence, participant counter subtraction or wall-clock
duration is required. Historical interval reconstruction needs a separate audit:
past Cycle completion and Commit timestamps have second precision and cannot
resolve ties. Do not invent an ordering for tied legacy events or rewrite old
measurement snapshots.

## Supplied dump diagnostic

The 2026-09-22 dump contains 1,538 Cycles: 1,530 ordinary Cycles with explicit
Committal entry Gates and eight Origin bootstrap Cycles. All ownerships resolve
without guessing. Across 92 non-Origin Commits, no other completed Cycle in the
same basin ties the Commit cutoff timestamp. This supports this dump's diagnostic
reconstruction; it does not guarantee arbitrary legacy histories are unambiguous.

| Run | Operational Commits | Observed interval range |
| --- | ---: | ---: |
| Committal Recovery Primes | 20 | 7–14 completed Cycles |
| Committal Recovery Offsets | 17 | 7–15 completed Cycles |

Representative fixed-history recalculations, without reinforcement:

| Commit | Old Depth | Proposed Depth | Old Cadence | Proposed Cadence |
| --- | ---: | ---: | ---: | ---: |
| Primes `01m33zd3n52mt1g4anps1d9qvk` | -0.9346218 | -0.7777778 | -1 | +0.8571429 |
| Primes `01m343nzq5by0613ca8m0rqkts` | -0.9415996 | -0.8 | -1 | +0.1428571 |
| Offsets `01m33xfpdbtjmf9af3yfqb8ht8` | -0.941477 | -0.8 | -1 | +1 |
| Offsets `01m3403b2spdqan2g3kvckczew` | -0.9544626 | -0.8461538 | -1 | +0.7777778 |

The proposed Cadence recognises stable commitment spacing. Depth still becomes
negative when a Settlement run grows longer than its preceding Emergence run,
as required by the proposed Depth formula. In these examples the directions
diverge, so the new alignment gate would reject those particular historical
candidates. These are counterfactual observations at fixed historical points,
not predictions that the same Commit path or Domain outcome would recur.

Run the read-only diagnostic with:

```bash
python3 scripts/inspect-basin-cadence.py /path/to/plain-text-dump.sql
```

The script emits identifiers and numeric observations, never proposition text.
It uses historical timestamps only to order events, not to measure durations.
It flags ambiguous cutoff ties and performs no writes.
