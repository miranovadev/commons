# Committal ownership and basin-local Cadence

This report describes the committed-lineage Closure replacement built on #196.
The dump comparison is counterfactual and read-only; it never rewrites snapshots.

## Cycle entry

Ordinary live entry records `cycles.entry_committal_id` from the selected
Committal. `context_horizon_id` remains the immutable Domain/topology container.
Origin bootstrap is the only production entry without an entry Committal. Completing, resting or
recovering never relabels the old Cycle. A subsequent Cycle records its newly
selected entry Committal. The nullable storage supports Origin creation before the
Origin proposition exists; ordinary production creation requires a Committal.

The #196 governance migration resolves ownership from each Cycle's original Distinguish Gate,
validates committed provenance and substrate/Horizon compatibility, and rejects
unresolved records. It does not infer an owner from the participant's current
position, a Horizon's latest Commit, sibling creation order or timestamps.
Eloquent and database triggers protect both non-null ownership and bootstrap null
from updates. A foreign key protects the referenced proposition.

## Authoritative interval

For each ancestral pair A -> B, freeze at B's successful Commit:

```
interval(A, B) = count of other completed Cycles entered at A before B commits
                + 1 for B's producing Cycle (also entered at A)
```

This counts all participants and expressive paths in A's basin. It excludes
Cycles entered at other Committals, including A's descendants and siblings.
Rest counts as completed participation in its original basin. Returning to A
does not reset its history. If A later produces another successor B2, its interval
includes the accumulated other completed Cycles entered at A before B2 plus B2's
producing Cycle, while the earlier
A -> B interval remains frozen.

The producing Cycle counts exactly once, regardless of whether snapshot publication
precedes its completion. Three previously completed Cycles entered at A plus the
Cycle entered at A producing B give interval(A, B) = 4. Every real transition has
an interval of at least 1. Other unresolved active Cycles do not count, even when
entered at A. There is no zero-interval similarity edge case to introduce.

For C0 -> C1 -> C2, compare interval(C0, C1) with interval(C1, C2).
For non-Origin B, follow B -> creating Phase -> creating Cycle ->
`entry_committal_id` to obtain its direct preceding Committal A. The producing
Cycle must have entered at A. No union of Supersession inputs or Horizon population
is needed.
The chain and the interval comparison continue across Domain boundaries.

The accepted symmetric bootstrap remains:

| Position | Depth | Cadence |
| --- | ---: | ---: |
| Origin C0 | -1 | -1 |
| Immediate descendant C1 | 0 | 0 |
| C2 onward | Measured Domain-run similarity | Measured basin-interval similarity |

Ordinary zero-value hysteresis and directional alignment remain mandatory.

## Persistence and concurrency

Store the interval on the successor's immutable Commit record, including C1 even
though its displayed Cadence is bootstrap zero. Recounting an old basin when
measuring a descendant would let later participation rewrite history.

The preview and persisted candidate must read the same basin count from the same
validated field state, adding the producing Cycle exactly once in both paths.
Existing contribution locking serializes writes; Cycle
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
| Committal Recovery Primes | 20 | 8–15 Cycles (including producer) |
| Committal Recovery Offsets | 17 | 8–16 Cycles (including producer) |

Representative fixed-history recalculations, without reinforcement:

| Commit | Old Depth | Proposed Depth | Old Cadence | Proposed Cadence |
| --- | ---: | ---: | ---: | ---: |
| Primes `01m33zd3n52mt1g4anps1d9qvk` | -0.9346218 | -0.7777778 | -1 | +0.8666667 |
| Primes `01m343nzq5by0613ca8m0rqkts` | -0.9415996 | -0.8 | -1 | +0.2 |
| Offsets `01m33xfpdbtjmf9af3yfqb8ht8` | -0.941477 | -0.8 | -1 | +1 |
| Offsets `01m3403b2spdqan2g3kvckczew` | -0.9544626 | -0.8461538 | -1 | +0.8 |

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

## Calculation architecture and deployed transition

`CommittalLineageService` reads only creating-Cycle entry Committals, using a recursive
query with UNION deduplication and a request-local record map. Calculation contexts
reuse the committed records and grouped completed-Cycle counts. The live interval
uses completed status under the existing root contribution lock; timestamps are
only legacy backfill evidence, never a live clock.

Closure is evaluated once at the measured head. Expressive propositions carry their
Cycle entry Committal snapshot's structural components and apply their own existing qualified
reinforcement. They do not extend Domain runs or create commitment intervals.
Correspondence and Chirality retain their independent local comparisons and their
existing expressive-lineage attenuation. Receipt qualification, participant caps,
Commit-source inheritance, reclassification effects and the contribution/Receipt
ratio remain unchanged. Closure uses `(D + C + mass) / (2 + mass)` at the head.

At upgrade, the migration invalidates disposable measurements and queues every
existing Horizon's terrain through `potential_dirty_horizons`, with the existing
field-version guard. Every old terrain is affected by this calculator replacement;
this one-time upgrade work is separate from basin-scoped completion invalidation.
Run the existing projection maintenance before resuming participation (or explicitly
run `php artisan commons:potential-rebuild <substrate-id>`). Substrates without an
existing projection still require that explicit rebuild. No projection is declared
current by changing its version label alone.
Historical CommitmentSnapshot observables and directions remain unchanged; the
migration adds only proven incoming intervals. Origin has no incoming interval.
An ordinary predecessor with an ambiguous interval blocks its successor preview
with `cadence_history_unavailable`. It cannot silently bootstrap again or use the
retired formula. This may leave a legacy basin unable to Commit until independent
historical evidence resolves its interval through an explicitly reviewed repair;
no automatic repair or history rewrite is performed here. The supplied dump has
92 reconstructible ordinary intervals and no cutoff ties.

Completion runs under the existing substrate contribution lock and increments the
existing field version, dirtying Horizons containing Cycles entered at A rather
than all substrate Horizons. This is invalidation, not a Cadence clock. Rest records
its existing pre-completion failure proof before completing the Cycle, atomically
under the same lock. This preserves the proof's version check. The input schema
retains a Closure-observation fingerprint. Autonomous responses retain it as their
expected schema, and the human client sends it as `expected_closure`, so a changed
preview retries even when its action remains admissible. Persistence checks the
calculated components and interval against the guarded Commit preview.

## Reinforced fixed-history comparison

The diagnostic replays Receipt-context load/clear events before each Commit's ledger
entry and deduplicates participants using the existing Commit-source/link semantics.
Reinforcement results assume the dump-time contribution/Receipt ratio, because the
dump does not version configuration. Missing event provenance or tied Receipt-link
creation leaves those derived results unavailable. Tension keeps historical
Correspondence and Chirality fixed and uses the unchanged projection formula.
These values do not predict a new natural trajectory or bypass direction alignment.

### Committal Recovery Primes

| Commit | Old Depth | New Depth | Old Cadence | New Cadence | Old structural Closure | New structural Closure | Old final Closure | New final Closure | Old Tension | New Tension |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| `01m330e55tsjgck9gppnacb0mq` | -1.0 | -1.0 | -1.0 | -1.0 | -1.0 | -1.0 | -1.0 | -1.0 | -1.0 | -1.0 |
| `01m3338vxqr9r266nc5eybdxpz` | -0.2490561 | 0.0 | -0.9182483 | 0.0 | -0.5836522 | 0.0 | -0.5785444 | 0.0 | -0.0516942 | 0.141154 |
| `01m334hmrrvmq3kvgnrdadqrp2` | -0.6683752 | 0.0 | -1.0 | 1.0 | -0.8341876 | 0.5 | -0.6347637 | 0.5714286 | -0.2945287 | 0.1075354 |
| `01m334pqbchbpe86ze1bdx9j6b` | -0.2490561 | 0.0 | -0.9182483 | 0.0 | -0.5836522 | 0.0 | -0.5696058 | 0.0 | -0.327596 | -0.1377274 |
| `01m3369sze8yy0nwy2f6myze22` | -0.7856056 | -0.3333333 | -1.0 | 0.3846154 | -0.8928028 | 0.025641 | -0.8732659 | 0.025641 | -0.3539764 | -0.0543408 |
| `01m336k6b6zywwp6j5kv2hyqpj` | -0.7909349 | -0.3333333 | -1.0 | 0.2857143 | -0.8954674 | -0.0238095 | -0.8761681 | -0.0238095 | -0.373055 | -0.0889356 |
| `01m338re5qdx3vx6axgccja83w` | -0.8471186 | -0.5 | -1.0 | 0.2857143 | -0.9235593 | -0.1071429 | -0.9235594 | -0.1071429 | -0.3937336 | -0.1215948 |
| `01m339f2wf77egb8a6y6nh8xcw` | -0.8417041 | -0.5 | -1.0 | 0.6923077 | -0.920852 | 0.0961538 | -0.920852 | 0.0961538 | -0.3393528 | -0.0003508 |
| `01m33c71p4scj0nwz3d1s5ztf4` | -0.8767628 | -0.6 | -1.0 | 0.3846154 | -0.9383814 | -0.1076923 | -0.9349874 | -0.1076923 | -0.3630276 | -0.0872624 |
| `01m33e4t6nz1mp0b2zqf9acvwv` | -0.8781166 | -0.6 | -1.0 | 0.6923077 | -0.9390583 | 0.0461538 | -0.9287609 | 0.0461538 | -0.3305942 | -0.0056226 |
| `01m33gwhxh0fdz0t69a4neqn1c` | -0.8989465 | -0.6666667 | -1.0 | 0.5384615 | -0.9494733 | -0.0641026 | -0.9393476 | -0.0641026 | -0.3228764 | -0.0311282 |
| `01m33h8zepepkdkw3pdj60gvf8` | -0.8988816 | -0.6666667 | -1.0 | 0.7333333 | -0.9494408 | 0.0333333 | -0.9494409 | 0.0333333 | -0.354266 | -0.0266746 |
| `01m33kg9fxts0yd3ev4e02k36v` | -0.9149806 | -0.7142857 | -1.0 | 0.8 | -0.9574903 | 0.0428572 | -0.93704 | 0.0428572 | -0.3196066 | 0.0070258 |
| `01m33r9akgj8tft4tcfq2pmpeh` | -0.9146644 | -0.7142857 | -1.0 | 0.3333333 | -0.9573322 | -0.1904762 | -0.9472866 | -0.1904762 | -0.329188 | -0.076918 |
| `01m3413qkyz8926wf2px6p3myk` | -0.8471186 | -0.5 | -1.0 | 0.8 | -0.9235593 | 0.15 | -0.9235594 | 0.15 | -0.2962932 | 0.06156 |
| `01m33a2gx9snj9q4hj27hk5hpm` | -0.6683752 | 0.0 | -1.0 | 0.0666667 | -0.8341876 | 0.0333333 | -0.8341876 | 0.0333333 | -0.3057822 | -0.0166086 |
| `01m33nwagf6gnxj3frgw8jnht9` | -0.7909349 | -0.3333333 | -1.0 | 0.7777778 | -0.8954674 | 0.2222222 | -0.8954674 | 0.2222222 | -0.2724986 | 0.1000646 |
| `01m33qxsdf9715chem5jxh9ksd` | -0.9268158 | -0.75 | -1.0 | 0.2857143 | -0.9634079 | -0.2321429 | -0.7304922 | -0.0561224 | -0.2366067 | -0.0118168 |
| `01m33yxzxhvc49kvwkq0wk6mn2` | -0.9248411 | -0.75 | -1.0 | 0.5384615 | -0.9624206 | -0.1057692 | -0.9522162 | -0.1057692 | -0.3348072 | -0.0526582 |
| `01m33zd3n52mt1g4anps1d9qvk` | -0.9346218 | -0.7777778 | -1.0 | 0.8666667 | -0.9673109 | 0.0444445 | -0.954434 | 0.0444445 | -0.3040738 | 0.0288856 |
| `01m343nzq5by0613ca8m0rqkts` | -0.9415996 | -0.8 | -1.0 | 0.2 | -0.9707998 | -0.3 | -0.9707998 | -0.3 | -0.3160714 | -0.0924716 |

### Committal Recovery Offsets

| Commit | Old Depth | New Depth | Old Cadence | New Cadence | Old structural Closure | New structural Closure | Old final Closure | New final Closure | Old Tension | New Tension |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| `01m330ft5wmycpy01xgsaafnyc` | -1.0 | -1.0 | -1.0 | -1.0 | -1.0 | -1.0 | -1.0 | -1.0 | -1.0 | -1.0 |
| `01m333b1za4hpp1q1y8zhfgv42` | -0.2490561 | 0.0 | -0.9182483 | 0.0 | -0.5836522 | 0.0 | -0.5836522 | 0.0 | -0.1820274 | 0.0125234 |
| `01m3348bywe7pk8nmj18dn80dj` | -0.6683752 | 0.0 | -1.0 | 0.3846154 | -0.8341876 | 0.1923077 | -0.8341876 | 0.1923077 | -0.1951498 | 0.1470154 |
| `01m335da2sj05rekrx12k96b1j` | -0.7856056 | -0.3333333 | -1.0 | 0.8 | -0.8928028 | 0.2333334 | -0.8928028 | 0.2333334 | -0.2325058 | 0.1428728 |
| `01m336yf55334awrk2wb27gb8h` | -0.845778 | -0.5 | -1.0 | 1.0 | -0.922889 | 0.25 | -0.9057074 | 0.25 | -0.2665594 | 0.1186764 |
| `01m3376dqpxpx4q887grahevfw` | -0.6683752 | 0.0 | -1.0 | 0.625 | -0.8341876 | 0.3125 | -0.8341876 | 0.3125 | -0.1872736 | 0.1949556 |
| `01m33838e2f6wmpz1qrytbc58z` | -0.8799184 | -0.6 | -1.0 | 0.6 | -0.9399592 | 0.0 | -0.9399592 | 0.0 | -0.2237626 | 0.0895572 |
| `01m33cr2y8dy9d2yjs81exb0kp` | -0.9258777 | -0.75 | -1.0 | 0.8 | -0.9629389 | 0.025 | -0.9629389 | 0.025 | -0.311162 | 0.018151 |
| `01m33efz47abzfxmnqrkrbxta6` | -0.9334422 | -0.7777778 | -1.0 | 1.0 | -0.9667211 | 0.1111111 | -0.956492 | 0.1111111 | -0.3036086 | 0.0522592 |
| `01m33j2d77g6be4a3mmn2ewqa3` | -0.9344579 | -0.7777778 | -1.0 | 0.3333333 | -0.9672289 | -0.2222222 | -0.9638164 | -0.2222222 | -0.305706 | -0.0585078 |
| `01m33nbqbkm7xsekbefam8bava` | -0.7909349 | -0.3333333 | -1.0 | 0.875 | -0.8954674 | 0.2708333 | -0.8922316 | 0.2708333 | -0.2703562 | 0.1173322 |
| `01m33waz4b338wpennw0ac4tdp` | -0.9502633 | -0.8333333 | -1.0 | 0.5384615 | -0.9751317 | -0.1474359 | -0.7414352 | 0.0164835 | -0.2163526 | 0.036287 |
| `01m33xfpdbtjmf9af3yfqb8ht8` | -0.941477 | -0.8 | -1.0 | 1.0 | -0.9707385 | 0.1 | -0.7264841 | 0.2285714 | -0.225141 | 0.0932108 |
| `01m3403b2spdqan2g3kvckczew` | -0.9544626 | -0.8461538 | -1.0 | 0.8 | -0.9772313 | -0.0230769 | -0.9640868 | -0.0230769 | -0.288843 | 0.024827 |
| `01m339dhg9eaptyetvmmwvz3ke` | -0.9013544 | -0.6666667 | -1.0 | 0.7777778 | -0.9506772 | 0.0555556 | -0.9506772 | 0.0555556 | -0.2399524 | 0.0954586 |
| `01m33at5mdrvp0r365jyqdvzzs` | -0.9161692 | -0.7142857 | -1.0 | 1.0 | -0.9580846 | 0.1428571 | -0.9580846 | 0.1428571 | -0.3095864 | 0.0573942 |
| `01m33krxm8yydwtwbre6wb89bh` | -0.9397442 | -0.8 | -1.0 | 1.0 | -0.9698721 | 0.1 | -0.9596251 | 0.1 | -0.2969534 | 0.056255 |
| `01m33s1heh38x5sje5qnwr0h0x` | -0.9458001 | -0.8181818 | -1.0 | 0.5384615 | -0.9729001 | -0.1398601 | -0.9694761 | -0.1398601 | -0.3037326 | -0.027194 |
