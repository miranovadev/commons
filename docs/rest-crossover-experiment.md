# Rest crossover routing experiment

Only after a valid Rest completes, recovery inspects that Cycle's entry Commit
Region. A closed Crossover Margin excludes that one Committal from this
participant's recovery search. The existing recorded backward-lineage walk then
selects the nearest otherwise eligible ancestor. Other ancestors are not screened
for crossover. No shared Region state is written.

`CrossoverMarginService::inspect()` exposes a read-only diagnostic. Correspondence
and Chirality are the entry seed's authoritative committed snapshot values. This
is a conditional bound on **current committed geometry**, not a prediction about
future expressive measurements or Future Reopening Capacity.

Depth is computed for a hypothetical next Committal in the resting Cycle's Domain,
with its entry seed as the canonical committed predecessor. `ClosureCalculator`
follows creating-Cycle governance; the seed's frozen Depth/Cadence are not reused.
Zero Depth retains the seed's previous Depth direction. The optimistic Cadence
bound is 0 or +1 in Settlement (negative or positive Depth direction), and -1 or
0 in Emergence. Zero is an optimistic endpoint even where hysteresis makes it a
supremum rather than an attainable value.

Reinforcement mass is the existing qualifying participant count inherited from
the seed by a prospective Commit, multiplied by the substrate's receipt increment.
The shared Closure calculation is `(D + C + R) / (2 + R)`. The canonical field
projection supplies Tension, algebraically `(Correspondence + Chirality + Closure)
/ 3`, using its existing seven-decimal rounding. Margin is Tension in Settlement
and negative Tension in Emergence; margin <= 0 is closed. Zero retains Domain.

The diagnostic is included as `crossover` in recovery inspection, alongside
`rest_skipped_committal_id`. It is not persisted and is not consulted during an
active Cycle, expressive authorization, initial entry, or ordinary continuation.
Missing historical Closure components cannot prove closure and retain ordinary
recovery. Origin remains the final fallback regardless of its margin.

If the skipped entry Committal governs a Horizon boundary, this Rest may continue
through that boundary only when the existing backward walk actually includes its
opener. It does not jump to an opener or search siblings. Exhaustion's existing
independent skip rule remains intact.

Closed crossover is not exhaustion, Tragedy, or zero Potential. It changes neither
Region availability nor measurements, receipts, admissibility, movement edges,
or other participants' positions. The resting participant's new Cycle is created
through normal Cycle entry and its existing field-version/position checks.
