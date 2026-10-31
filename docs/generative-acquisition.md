# Acquisition for a generative attempt

Existing personally unexplored structure takes precedence. Attestation and Traverse do not require acquisition. Only a prospective fresh Variant creates an acquisition opportunity. The prospective probe excludes acquisition, while actual Variant dispatch requires a current admitted attempt. Explored Writes remain visible and non-actionable.

One execution per Cycle is marked with `attempt_admitted_at`. A partial unique index protects this reservation. Historical executions are retained without retroactive admission. `attempt_resolution` records the single Variant or Defer decision separately from immutable observation status and Receipt metadata. A Variant in an early Phase uses this Cycle's attempt; later acquisition must wait for a new Cycle. This is a deliberate consequence of the handoff's Cycle-wide rule.

Preparation supports Variant or Defer in Persist and Disperse. Prepared Revision is no longer offered: acquisition admits a fresh Variant opportunity, not reconsideration. Ordinary lawful Revision remains available independently, with its existing evidence and explored-Write restrictions.

Defer records a reason, closes the Phase and Cycle with `deferred_at`, preserves the Gate and Receipt, and publishes no Exploration Event. Subsequent entry uses existing backward recovery selection. Defer is never stored as Rest. No measurement or recovery thresholds are changed.

Admission is checked under the Substrate lock. Instrument reserves before observing external evidence; Preparation reserves before reading. Resolution retains the admitted opportunity against ordinary competing Variants, while rechecking Gate position, hard supersession, terminal closure and canonical boundaries. The unique Cycle reservation and write-once resolution prevent reuse. Duplicate resolutions are either exact idempotent retries or rejected conflicts.

Artifact selection reuses the cognitive horizon and recorded Receipt associations. Consumed Receipts remain eligible when causally visible; hashes alone confer no access. Reading capability and storage validation remain authoritative.

## Validation in progress

`GenerativeAcquisitionJourneyTest` uses only application HTTP operations and deterministic external evidence. It covers repeated Instrument/Defer, progressive Preparation/Defer/Variant, independently admitted competing Variants, and a slower Participant's first judgment without acquisition. Three tests pass with 163 assertions. Broader fixture updates, stale/failure coverage, PostgreSQL concurrency and baseline comparisons are still in progress. This branch is not yet ready for merge.
