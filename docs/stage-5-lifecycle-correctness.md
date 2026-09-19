# Stage 5 lifecycle checkpoint

Stage 5 repairs the successful-route Bias revisit and makes Tragedy's final
attempt attainable through the real participation lifecycle. Stage 6 suite
reorganisation and Stage 7 cleanup are not part of this change.

## Semantics

- A successful Commit freezes its source against mutation but preserves Bias
  traversal through that classification. An operational snapshot, exact Horizon
  and Commit Region constrain this route. Order then traverses the existing
  Commit. No new content, Attestation, snapshot or failure is manufactured.
- When that Commit's child Horizon later exhausts, its successful incoming
  route remains visible as `continuation_exhausted` history without becoming a
  failure. This state propagates backward only when all other outgoing routes
  are also exhausted continuations or failed in their exact current epoch. A
  live alternative stops propagation; Committals remain boundaries. These
  resolved routes leave action targets and viable generation populations, and
  their historical numerical gain no longer supplies exploration capacity.
- Rest is still derived from the authoritative action schema. An unread
  informational receipt may coexist with Rest; a productive action excludes it.
- Numerical capacity reads all local Committal regions from maintained topology.
  A later Commit cannot hide an earlier region through frontier shadowing, and
  child-Horizon scores do not supply the current Horizon's capacity. Numerical
  gain remains distinct from structural eligibility and current spentness.
- Tragedy now has one explicit shared allowance for a Distinguish/Disperse
  Variant at zero Potential. All existing current-option, evidence and spentness
  checks remain. This deliberately extends the former positive-Potential-only
  rule to implement the promised final attempt. The successful Variant records
  its unique claim and advances its own epoch atomically. Receipt creation,
  Read and consumption themselves do not advance epochs or alter failure rows.
- A consumed Tragedy can justify Exhaustion only after a subsequent evidenced
  no-Potential Rest on that classification's actual backward participant lineage.
  An unrelated failure cannot terminate someone else's continuation.

The [Tragedy lifecycle](branch-tragedy.md) and
[Variant clarification](horizon-proof-variant-clarification.md) describe the
allowance and its limits. Ordinary Variant generation keeps its existing rules.

## Production-path evidence

| Journey | Positive and negative boundary |
| --- | --- |
| Successful Bias revisit | Traverses the frozen classification and existing Commit; excludes source mutation and Rest; preserves content, votes, snapshot and immutable failure history. |
| Rest with optional Read | Rejects premature Rest in Distinguish; accepts Rest before an unread informational receipt is loaded; optional Read leaves the Phase unchanged. |
| Natural Tragedy and final attempt | Real contributions and optional Bias receipt loading open a new Horizon. Actual exploration reaches Tragedy. A second participant traverses into that Horizon, claims the final Variant, reads and consumes the receipt through Reclassify, and eventually fails that continuation. |
| Shared evidence and isolation | The second participant sees the Tragedy receipt; a participant parked in the previous Horizon does not. Read does not consume it; consumption does not immediately exhaust the Horizon; duplicate allowance claims are rejected. |
| Exhaustion and recovery | The failed consumed continuation closes exactly its own Horizon. Recovery continues backward across the real API-created boundary to Origin, retaining the distance across both Horizons and preserving history. The participant then takes another live continuation, rather than being sent repeatedly into the exhausted successful route. |
| Visibility | Local propositions, attestation summaries, receipt evidence and parent-edge references are present. Distant forward structure and foreign-substrate propositions, receipts, attestations and edges are absent; injected foreign targets are rejected. |
| Participant sources | Across all seven phases through Commit, toggling a real inference connection leaves the HTTP state identical and the same contribution validates against inference transport. A real wake uses the same HTTP-observed plans for Variant, Variant, Rest and recovery, then stops at the Cycle boundary. Only external inference is mocked. |

HTTP fixtures still prohibit direct structural writes. The sole additional
application boundary executes the real `WakeParticipant` worker for source
equivalence; it accepts no arbitrary callback. Narrow service tests cover the
allowance's rollback and uniqueness, existing-slot consumption, zero-frontier
false negatives, unrelated-failure exclusion, historical success visibility and
live-alternative/current-epoch guards on backward continuation completion.

The Stage 4 HTTP recovery test formerly expected Rest at the broken successful
Bias revisit. That assertion is explicitly superseded: Rest is now rejected
there because Traverse is available. Its ancestor-only candidate and exact
distance assertions remain. A separate actual actionless HTTP Rest journey
preserves coverage of executed recovery. No test was skipped, moved or assigned
to a cheaper suite to achieve this checkpoint.

## Persistence and validation

The additive `tragedy_final_attempts` table stores the Tragedy, creating Phase,
Variant and start time. A unique Tragedy key and the existing Substrate lock
serialize competing claims. No export, historical failure, receipt, snapshot,
recovery position or old Exhaustion is rewritten. An existing open Tragedy with
an unconsumed receipt may use the new allowance; consumed receipts cannot.
Rollback refuses to discard recorded attempts.

PostgreSQL Core, Full Integration, Query and Load results, exact tested commit,
counts and runtimes are recorded on the Stage 5 pull request. Core and Full
retain their 30-minute ceiling and unchanged existing suite membership.

### Accepted validation evidence

The Stage 5 application/test commit is
`2e64a79ccc8d84f9c9332a95e45ebeae8660eea3`. Its Core and Full Integration
jobs reached the 30-minute ceiling and were cancelled after 30m16s including
teardown. They **do not count as complete suite passes**. Query passed 16 tests;
Load passed 22 tests. Local Core passed 613 tests with the same three
PostgreSQL-only skips.

The unchanged natural Tragedy HTTP test subsequently passed independently on
PostgreSQL 18 / PHP 8.5: **one test, 589 assertions, 142.58 seconds of PHP test
time and 3m34s total job time**. The isolated workflow explicitly checked out
the exact Stage 5 commit. It proves the full Tragedy flow, legitimate shared
receipt use, final-attempt Exhaustion, backward recovery across the Horizon
boundary, and subsequent live exploration. See
[the isolated run](https://github.com/miranovadev/commons/actions/runs/35424219190)
and [PR 180](https://github.com/miranovadev/commons/pull/180).

Claire accepted this targeted result as sufficient for the missing Stage 5
semantic evidence and authorised Stage 6. The cancelled suites will not be
repeated solely to re-prove that journey. At the final Epic checkpoint, one
deliberate all-suite validation must use an appropriate timeout and produce a
complete aggregate result. Targeted passes do not replace that final gate.
