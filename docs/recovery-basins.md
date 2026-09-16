# Rest recovery basins

The authoritative lifecycle is [Exhaustion and Origin Tragedy](branch-tragedy.md).

The completed Cycle supplies the starting Context Horizon. An unpositioned
boundary uses its direct child Horizon; a non-boundary Commit uses its creating
Horizon. Domain, phase position, and Commit Region restrictions remain unchanged.

The incremental recovery resolver reads maintained descendant frontier summaries
and local entries. Sibling frontiers remain peers, ranked by existing
`1 / (1 + distance)^2` observer attenuation, then effective Charge, distance, ID.
There is no recency, sequence, or depth bonus. Historical geometry remains
available for distances from exhausted positions.

A fresh open frontier can have zero measured Potential. It remains eligible for
exploration; this does not manufacture a positive score. Only explicit exhaustion
of the opening Commit's outgoing basin permits climbing to the immediate parent
Horizon. At that parent, remaining sibling routes are considered before further
retreat. Intermediate Committals persist. Origin remains the unique generative
root. Missing projection state is a maintenance condition, not exhaustion.

Ordinary completion continues at the exact Committal. A shared exhaustion
transition never moves another Participant; it changes admissibility at their next
normal action boundary. Exhausted local alternatives remain compact context but
cannot be re-entered.

`commons:recovery-inspect` reports the hypothetical route without changing any
state. `commons:potential-inspect --verify` compares maintained numeric selection
with the slow reference. `commons:exhaustion-audit` separately checks structural
closure from terminal exploration witnesses. None runs in ordinary read paths.
