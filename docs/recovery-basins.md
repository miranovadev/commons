# Backward lineage recovery

The participant's terminal position and completed Cycle Horizon are the recovery
starting point. Recovery walks incoming causal edges backward and collects only
active ancestral Committals in that Horizon. A retained Committal may itself be a
distance-zero candidate. Descendants, siblings and unrelated Committals cannot
enter the candidate set, regardless of Potential.

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

Candidates retain the existing raw Potential and Charge definitions. Effective
Potential is `raw Potential / (1 + backward distance)^2`; effective Charge,
distance and stable ID break ties. A farther ancestral Committal may beat a
nearer one. A successful Commit does not shadow stronger ancestors during Rest
recovery. Initial global entry and ordinary Commit continuity retain their
existing semantics.

The current Horizon is a boundary on the walk. Only its explicit exhaustion
(or existing legacy unavailability) permits crossing, and its opening Committal
must actually occur on the walked path. Numeric zero alone never closes a fresh
basin. In a previous Horizon, candidates are ancestral Committals encountered
along the same path, with distance still measured from the original terminal
position. Recovery never resets the observer to that Horizon's opener. Every
parent requires its own proof; child exhaustion cannot close its parent.

Tragedy uses this same recovery route. Publication retains the terminal position
and creates no `recovery_proposition_id` override. Legacy override values are
ignored, while retained historical values are not rewritten. Tragedy Receipt
creation, consumption and exhaustion proofs are unchanged; see
[Tragedy and final-attempt Exhaustion](branch-tragedy.md).

The projected resolver reads maintained local topology and raw measurements plus
the relevant durable Phase arrivals. It never discovers candidates from child
frontiers or performs a whole-substrate graph search. Initial-entry and numeric
maintenance summaries remain in place for their existing consumers.
`commons:recovery-inspect` and `commons:potential-inspect --verify` pass the actual
participant and are read-only. The slow reference independently loads raw graph
and measurement data, using the same lineage contract.

[Stage 4 evidence](backward-lineage-recovery.md) records superseded test contracts
and the validation checkpoint.
