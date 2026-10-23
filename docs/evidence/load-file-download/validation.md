# Stage 3B validation

Base: merged Stage 3A (`a336aa80dbfaf293823fe1309cd588d4c2dd64a9`).
Validated implementation: `a756a56405e456456f69b4c96fdee49d3c1b21a6`.
The final report commit changes documentation only; production and test code are
identical to this validated implementation.

## Old and new behavior

Previously, HTTP acquisition supplied Hyperlink's descriptive observation.
Load File adds explicit Download intent through the same Persist Instrument
lifecycle, preserving exact bytes and an ordinary non-loadable Receipt. Existing
Hyperlink identity, reference extraction and seven-day version behavior remain.

The new capability is complete through the human HTTP contribution path and an
autonomous wake: Download creates a Receipt, refreshes the same Persist Phase,
and the next ordinary Variant advances to Constrain. The autonomous request
contains acquisition metadata but never the downloaded body. Consumed Download
Artifacts remain available for lawful Preparation without a loading step.

## Results

| Check | Result |
| --- | --- |
| [Core / PostgreSQL](https://github.com/miranovadev/commons/actions/runs/40021141134) | **1,418 passed, 1 baseline failure**, 1,419 cases and 14,805 assertions across all five completed shards. |
| [Integration / PostgreSQL](https://github.com/miranovadev/commons/actions/runs/40021150771) | **32 passed, 3 baseline failures**, 9,367 assertions. Summary confirms all 35 discovered cases executed with no skips across four completed shards. |
| [Phase Profile / SQLite and PostgreSQL](https://github.com/miranovadev/commons/actions/runs/40021141090) | **All 12 jobs passed**, including lifecycle, canonical committed history and final-review contracts. |
| [C3 context](https://github.com/miranovadev/commons/actions/runs/40021150802) | **108 passed**, 2,414 assertions, including the causal-context comparison. |
| [Frontend](https://github.com/miranovadev/commons/actions/runs/40021141134/job/120645863580) | **136 passed**; TypeScript and production build passed. |
| SQLite portability | **14 passed**, 46 assertions, separate from Core's PostgreSQL totals. |
| Local focused evidence/lifecycle/context | **258 passed**, 1,717 assertions; includes Hyperlink, Download, Preparation, Stage 2A/2B, C2/C3, replay and duplicate contracts. |
| Final targeted validation | **48 passed**, 391 assertions, including human HTTP, autonomous Download and retained Reinforcement fixtures. This overlaps the focused run. |
| Local Artifact storage contracts | **8 passed**, 28 assertions, including concurrent immutable publication. |

Pint and whitespace checks pass. Full Core also exercises the existing ArtifactStore
S3 conditional publication and integrity contracts. PostgreSQL's independent-process
allowance test passes for both Citation and Download: one completed execution and
Receipt, no duplicate allowance use, no Download loading event. Existing competing
Receipt-consumption/generation tests pass too.

## Cause-level baseline comparison

- Core `ChargeBoundaryTragedyTest` and Integration
  `NaturalChargeBoundaryLifecycleTest` fail at the unchanged
  `tests/Fixtures/NaturalBoundaryHistory.php:63`: expected `settlement`, observed
  `emergence`. The assertion, observed value and cause match merged Stage 3A.
- The two `FreshBootstrapTest` aligned-policy cases exhaust 101 driver iterations
  without an admissible Commit. Their complete 107-action arrays and final Bias
  diagnostics were compared directly with Stage 3A's validated Integration log
  (job 120438603686), excluding only generated Gate IDs. Both are identical,
  including six Prepare/contributed-Variant pairs and the final Cycle 25 / Constrain
  Reclassify action. Bias reports `component_directions_disagree`: positive
  orientation, negative Attestation cadence and positive Chirality.

No new failing case or changed failure cause remains in the requested suites.
No independent mathematical or causal assertion was weakened, no lifecycle was
forced to Commit, and no baseline failure was suppressed.

## Consequential corrections

- Widening SQLite enum columns rebuilds tables. The migration explicitly preserves
  partial allowance-index SQL, preventing Laravel's rebuild from turning the
  Instrument/Preparation indexes into unconditional per-Cycle uniqueness.
- Creator loading now honors `Readable`; ordinary catalogue availability already
  had the correct independent ownership and consumption contract.
- Download duplicate checks require the acquired byte identity and run again under
  the existing contribution lock. URL-only exclusions would incorrectly prohibit
  changed versions, so they are not emitted for Load File.
- Existing verifier fixture constructors follow the shared transport boundary.
  An initial misplaced database guard in that low-level transport was corrected;
  Download retains its outside-transaction acquisition guard, and Hyperlink's
  existing Reinforcement fixture assertions pass unchanged.
- Current and historical C2 documentation explicitly notes the Stage 3B capability
  refinement: only readable creator Receipts auto-load.

No production/shared database records were modified. The PR introduces one additive
kind/type constraint migration, no new execution or Artifact table. Remaining
format/reading limitations and future Upload scope are documented in the
[Download contract](../../load-file-download-stage-3b.md).
