# Exhaustion architecture audit

The former retirement path coupled causal membership to `origin_branches.pruned_at`.
`Proposition::active()` delegated to that branch filter, so retirement changed action
schemas, lineage context, receipt access, numeric Potential, recovery, and graph
queries at once. `TragedyService` treated zero numeric region summaries (plus an
earliest-Commit checkpoint) as a proof. `ProjectedRecoveryService` also climbed on
an empty positive frontier. Neither represented unexplored generative structure.

## Replacement

* An immutable exhaustion transition closes an expressive excursion, or the
  outgoing basin of a Committal. The Committal proposition is never exhausted.
* A validated Rest after completed evidence and an effective Attestation is a
  terminal exploration witness. It can close an expressive leaf; it
  cannot close a node with any unexhausted child. Creation and zero Potential are
  never witnesses. Internal nodes close only after **every direct child** has
  closed. A Committal child counts only when its outgoing basin has closed.
* The proof walks indexed direct parent/child edges upward from that witness,
  stopping at open siblings. This includes all reconciliation inputs and every
  intervening Commit, including Commits that do not change Domain. No terminal
  ancestry alone proves sibling exhaustion.
* Each Commit persists as a causal boundary. Context Horizons remain Domain boundaries;
  a Horizon opened by a Commit closes when that Commit's entire outgoing basin
  closes. Empty new basins remain open until an actual validated terminal witness.
* Only closure of a direct expressive Origin child records Tragedy. Origin stays
  generative. A local Rest is not called Tragedy.
* Recovery retains open descendant/local frontiers in the current Horizon;
  otherwise it crosses its opening Commit into the immediate parent Horizon. Numeric Potential
  ranks available continuations; zero never substitutes for structural closure.

Proof construction happens outside the contribution lock. Publication checks the
field version, exact phase/participant/position, and completed Rest, then inserts
unique transitions and dirties only affected projection regions. No other
Participant is relocated. Events publish after commit with IDs only.

Legacy pruning remains immutable metadata. Migration copies retired branches to
an explicit legacy hold, without labelling their unproven topology exhausted or
resurrecting them. Fresh transitions never read or write `pruned_at`. Historical
inspection remains available. Removing the old column is a later schema change.

## Regression evidence and cost

Before implementation, all 25 old branch-Tragedy tests passed. A new regression
with an explored terminal beside a fresh zero-Potential post-Commit sibling
nevertheless reported Origin exhaustion. `ExhaustionTest` now requires only the
explored terminal to close, keeps the child Horizon open, and recovers locally.
The old earliest-Commit proof and its expectations have been removed.

The existing production recovery path was already incremental. This change keeps
that architecture, replacing numeric exhaustion inference with indexed structural
propagation. The proof scales with affected ancestry and sibling fanout; this is
not a claim that a single enormous affected basin has constant cost.

Local PHP 8.5 / SQLite measurements, with unrelated history added to the same
fixtures (timings are illustrative, query-growth assertions are enforced):

| Read/proof | No added history | 1,000 historical propositions |
| --- | --- | --- |
| Exhaustion proof | 114 queries, 27 ms | 114 queries, 29–41 ms |
| Recovery participant state | 26 queries, 13 ms | 26 queries, 14 ms |
| Projected recovery | 14 queries, 1.5 ms | 13 queries, 1.7 ms |
| Slow recovery reference | 14 queries, 3 ms | 14 queries, 43 ms |

The graph read remains 20 queries at both 1 and 500 cards. Existing dense
participation profiles stay at 75/69/54 queries for Mediate/Bias/Order. Exhaustion
proof and context computation run outside the guarded contribution transaction.
PostgreSQL CI separately exercises actual lock contention, simultaneous sibling
Rest, a fresh sibling racing publication, and projected/reference agreement.
