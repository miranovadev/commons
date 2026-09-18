# Substrate motor: review checkpoints

Stage 0 investigated production exports and current implementation at
`170668a027d4b2b8c29051c66eebaf7e3054defd`. Stage 1 only separates test execution
and Actions; [testing.md](testing.md) records suite membership and invocation.
Stop and report Stage 1 validation before beginning the HTTP harness or any
behavioral correction.

The next approved design constraints are:

1. Stage 2 must make the successful-route revisit / Bias dead end one of its
   first explicit red HTTP participation regressions. Revisit a route that has
   already reached Commit and reproduce the externally visible lack of forward
   action before fixing option visibility or surrounding semantics. Setup must
   not manufacture intermediate participation state.
2. Lineage-constrained recovery must include the Tragedy override. The direct
   `recovery_proposition_id` jump to the Horizon opener must not remain a bypass
   around normal backward-lineage recovery. Investigate and report any compelling
   structural reason for an exception before retaining one.

Keep the staged review order: test/Actions separation; HTTP harness and red
regressions; local structural epochs and scoped spentness; backward-lineage
recovery; complete lifecycle/constitutional coverage; protected cleanup. Existing
test expectations are preserved in Stage 1, including expectations that later
stages may demonstrate are wrong.
