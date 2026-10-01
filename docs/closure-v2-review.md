# Closure v2 review boundary

The draft implements immutable Proposition birth observations, committed-only
lateral placement, full same-Domain depth counts, population-CV Cadence and the
approved temporary-next-child placement. Crossover Margin now consumes the
canonical prospective Committed Cardinal Position and its retained direction.
Its optimistic endpoint policy, field projection, margin sign, zero threshold,
Rest eligibility and backward recovery walk are unchanged.

Controlled tests now supply consistent birth observations instead of Gate answers
that contradict the new Committal's measurement. Recovery fixtures author open
v2 geometry where they require an open crossover; preliminary Rest is allowed to
make its existing crossover-based selection. No production recovery policy was
changed. PostgreSQL trigger installation also tolerates test database resets,
and the inspector contract expects the new component labels.

## Stop: the existing natural catastrophe path cannot reach its second Commit

The smallest unbranched case is Origin E, followed by C1 S at lateral coordinate 1.
For the next Commit, whether its Domain is E or S:

| Component | Value | Direction |
|---|---:|---|
| Committal Depth | 0 | Retained Negative |
| Lateral Committal Position | 1 | Positive |
| Committed Cardinal Position | 0.5 | Positive |
| Committal Cadence | 0 | Retained Negative |

One Domain still has only one interval sample, so Cadence remains in bootstrap.
Increasing the open-region population cannot supply the missing committed sample.
The unchanged direction-agreement requirement therefore rejects the Commit.
`ClosureBirthTest` explicitly proves both Domains, at populations 1 and 5.

`NaturalBoundaryHistory` attempts exactly this second Settlement Commit. Its
Core natural-boundary test and Integration catastrophe/recovery test consequently
fail before a catastrophe or recovery occurs. Merely updating a projected value
cannot preserve that authored path. Permitting it would require an admissibility
or bootstrap decision; establishing a different reachable branching history is
separate path-design work. Neither is silently introduced here. This does not
claim that every possible branched topology is blocked.

The existing natural-path tests remain failing, rather than being skipped,
weakened, or converted to mocked measurements. This draft is not ready to merge.

## Validation

The focused Closure/observable/Commit-region/Crossover run passed 62 tests / 566
assertions before adding the explicit second-Commit boundary cases. The reconciled
controlled recovery suite passed 56 tests / 619 assertions. PostgreSQL Core and
Integration reruns and the latest focused results are recorded in the draft PR.
Local SQLite broad runs cannot validate PostgreSQL-specific invariants.
