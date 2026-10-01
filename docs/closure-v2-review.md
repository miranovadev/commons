# Closure v2 review boundary

This draft implements immutable Proposition birth observations, committed-only
lateral placement, full same-Domain depth counts, population-CV Cadence and the
approved temporary-next-child rule. The focused Closure/observable run passes
44 tests / 382 assertions; the frontend build and seven inspector tests pass.

## Existing Crossover Margin still assumes the retired Closure composition

`CrossoverMarginService::inspect()` obtains prospective Depth, resolves its
direction, chooses an optimistic admissible Cadence endpoint and calculates
`(Depth + endpoint) / 2` through `new ClosureValues(...)`.

Closure v2 instead calculates `(Depth + Lateral + 2 * Cadence) / 4`, with
Committed Cardinal Position as the outer structural component. Even at identical
Depth and Cadence, the old expression is not the new Closure bound:

| Depth | Lateral | Cadence endpoint | Old Closure expression | Closure v2 |
|---|---|---|---|---|
| 0 | 1 | 0 | 0 | 0.25 |

This is an algebraic counterexample, not a claim that every endpoint in this
example is attainable in a given historical region. A bound also needs to agree
with the relevant retained component directions and available interval history.

The existing Rest recovery code consumes this bound to decide whether to skip
its current Commit Region. Updating it therefore touches the explicitly deferred
continuation/path-viability area; retaining it does not establish a valid v2 bound.
This draft leaves that implementation unchanged for an explicit scope decision.

The broader local runs expose changed Crossover/Recovery expectations and a
constructed natural-catastrophe history that no longer passes its Commit gate.
There are also old controlled-field fixtures whose mock Gate values must be made
consistent with their authored birth observations. Those failures are not grounds
to weaken birth immutability or the real Commit guard. Some local checks require
PostgreSQL and cannot be validated by the available SQLite runtime.

This draft is not ready to merge. Before completing broad validation, decide
whether adapting the existing Crossover Margin consumer to Closure v2 belongs in
this correction, or how that consumer should be handled until the next task.
No new continuation filtering, movement, Rest or recovery policy has been added.
