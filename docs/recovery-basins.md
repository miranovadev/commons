# Backward lineage recovery

> Recovery geometry is unchanged by the [approved Stage 4 lifecycle](tragedy-resolution-target.md).

The participant's terminal position and completed Cycle Horizon are the recovery
starting point. Recovery walks incoming causal edges backward and collects only
active ancestral Committals in that Horizon. A retained Committal may itself be a
distance-zero candidate. Descendants, siblings and unrelated Committals cannot
enter the candidate set, regardless of Potential.

Movement topology, Homeostatic populations and contextual visibility remain
separate. Commit Region membership defines calculation/context scope only where
an operation explicitly requires it; it never creates a traversal or recovery
edge. Neither the same Horizon/phase position nor a visible historical Commit
can add a destination. **No edge means no movement.**

Every raw edge counts once, including revisions and historical intermediate
nodes. Display lineage is not used: it omits historical nodes and stops at the
nearest Committal. At a multi-input Write, the participant's consecutive Phases
record the arrival Gate. This takes precedence over the Write creator's Gate.
Normal Order-to-Distinguish rollover also records an arrival; Rest entry does not.
When the walk reaches inherited history without a personal arrival, it continues
through the creating participant's recorded provenance. A backward Phase cursor
prevents a later revisit from changing an earlier hop. An ambiguous multi-input
without a recorded Gate, a missing node, cross-substrate ancestry, or a causal
cycle fails closed.

Recovery proceeds backward through committed basins in causal order. A nearer
committed basin must be resolved before an older committed basin becomes eligible.
The first eligible, non-exhausted Committal on the recorded path is the sole
recovery destination. Higher Potential or Charge at an older ancestor cannot
skip it, even when the nearer basin has zero Potential and zero Charge. An
eligible retained Committal is itself the distance-zero boundary. Once a
Participant actually Rests in Distinguish at that entry, returning to the same position is no
backward movement. Recovery starts with the next ancestor, preserving the
Horizon boundary. Rest at an expressive Gate still allows return to its
governing Committal. A Participant waiting at an entry can resume there when a
new lawful Distinguish action appears. Recovery never consults [Crossover Margin](rest-crossover-experiment.md).

A Commit Region is the recovery basin governed by its Committal. Same-Horizon
non-boundary Commits govern distinct basins. `commit_region_exhaustions` is the
primitive proof authority, keyed by that governing Committal. A qualifying direct expressive Charge-zero encounter establishes terminal
authority; locked evaluation proves Exhaustion when no lawful continuation remains. Potential, ordinary Rest, historical
zero measurements and Tragedy Receipt consumption do not establish that proof.

Only the exhausted region becomes unavailable. Older, sibling and descendant
Commit Regions remain independent even inside the same Horizon. Recovery skips
an exhausted governing Committal and stops at the next open one on the same
recorded path. No values rank those destinations.

The current Horizon remains a boundary on the walk. Its boundary-governing
region's exhaustion permits crossing only when its opening Committal occurs on
the recorded path. This is a consequence of the same regional proof, not another
exhaustion ontology. After crossing, recovery stops at the nearest eligible
ancestral Committal in the parent Horizon. No Horizon opener is substituted.
Opening-region exhaustion and its timestamp derive directly from the governing
Committal proof; no duplicate Horizon timestamp is stored. This does not
retire every region owned by the Horizon. A live descendant region can still
operate there. Origin has no parent crossing, and persists uniquely.

Potential, Charge, field calculations and inverse-square measurement attenuation
are unchanged. Recovery selects its destination **before** calculating diagnostic
values; `potential_charge` and `charge` still report the selected destination's
raw values multiplied by `1 / (1 + backward distance)^2`. They do not rank recovery
destinations. `eligible_candidate_ids` contains only the selected Committal (or
is empty); older unresolved ancestors no longer compete. Initial global entry,
relative-ranking diagnostics and ordinary Commit continuity retain their semantics.

**Commitment creates a recovery boundary. Exhaustion permits recovery past it.**

Tragedy uses this same recovery route. Publication retains the terminal position
and creates no `recovery_proposition_id` override. Legacy override values are
ignored, while retained historical values are not rewritten. Tragedy identity and Receipt authority remain regional. See
[direct Tragedy and Resolution](branch-tragedy.md).

The projected resolver reads maintained local topology and raw measurements plus
the relevant durable Phase arrivals. It never discovers candidates from child
frontiers or performs a whole-substrate graph search. Initial-entry and numeric
maintenance summaries remain in place for their existing consumers.
`commons:recovery-inspect` and `commons:potential-inspect --verify` pass the actual
participant and are read-only. The slow reference independently loads raw graph
and measurement data, using the same lineage contract.

[Stage 4 evidence](backward-lineage-recovery.md) records superseded test contracts
and the validation checkpoint.
