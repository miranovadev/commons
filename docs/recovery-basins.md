# Backward lineage recovery

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
eligible retained Committal is itself the distance-zero boundary.

Exhaustion is established by the existing Tragedy/final-attempt proof, never by
ranking values or inferring that an empty basin is closed. A Participant can
return to an open basin with no admissible continuation; its subsequent lifecycle
must establish exhaustion before recovery may continue past it. Same-Horizon
Commits do not acquire a new per-Commit exhaustion mechanism in this change.
Existing authoritative Horizon exhaustion makes its owned positions and opening
Committal unavailable.

The current Horizon remains a boundary on the walk. Its explicit exhaustion
permits crossing only when its opening Committal occurs on the recorded path.
After crossing, recovery stops at the nearest eligible ancestral Committal in
the parent Horizon, rather than substituting that Horizon's opener. Every parent
requires its own proof; child exhaustion cannot close its parent.

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
