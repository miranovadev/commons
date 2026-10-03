# Local / acting Reconcile cursor diagnostic

This directory extends the previous propagating-cursor / zero-mean-regularity diagnostic with exactly one clarification: a Reconcile result takes its beat cursor only from its local acting source. The source is identified by the creating Phase's persisted `gate_proposition_id`, not by insertion order or the largest endpoint. Every real incoming edge is recorded for audit. A missing local cursor remains missing, even if the other parent has a cursor.

The interval formulas, all-zero mature special case, own-Attestation cache, birth-time capture, fresh local reset and ordinary cursor propagation are unchanged. The existing `CursorFormulaMeasurements.php` is required from the previous directory, not modified. Production direction/attenuation/Correspondence/Closure/grounding/lifecycle services remain real and unchanged.

The recorder reports local-only, other-only and shared ancestry from the production unique lineage positions, with shared ancestors counted once. It does not select a cursor on behalf of attenuation or exclude the other parent.

`ReconcileReplayTest.php` replays all preserved actions until the first actual Emergence Commit, the end, a new semantic boundary, or an archived action that the unchanged production lifecycle no longer permits under the new measurements. It does not replace an unavailable archived action with a newly offered action. Such a stop records the actual schema and current state; it is a replay-contract constraint, not invented production semantics.

The single-participant 17-action control is rerun with the same adapter. Canonical Reclassify, Reconcile and stale-Bias tests run separately without the adapter. The workflow uses PHP8.5/PostgreSQL18. Reports include only actual measured states; later archived baseline results are labelled separately.

No existing source, fixtures, participant choices or production semantics are changed. No PR or merge.
