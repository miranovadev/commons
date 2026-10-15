# Stage 3 complete-suite inventory

These CSVs are derived from the JUnit XML, discovered inventories, selected
shard inventories and PHP runtime artifacts of the Actions runs linked in the
[validation report](../../prospective-closure-validation.md).

`summary.json` identifies the tested commit, exact coverage accounting, counts
and PHP wall times. Each CSV contains at most 100 cases from one shard, including
passing cases. Named and numeric data sets are distinct test identities. No
case is removed because its assertion failed or its fixture errored.

Failure categories are relative to Stage 2: A unchanged baseline, B new lawful
trajectory, C defect, D setup/infrastructure. A provisional D classification
identifies the immediate missing-state boundary only; it does not prove the
underlying application assertion will pass after fixture conversion. The
validation report explains masked baseline failures and the remaining limits.

Failure summaries retain the exception/assertion and first test stack frame.
For the four new Integration trajectory failures they retain the scalar and
component-direction diagnostic instead of incidental generated IDs. Full
traces remain in Actions artifacts subject to their retention policy.
