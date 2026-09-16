# Participation reads and write locks

Participation reads are optimistic and take no database write locks or enclosing
transactions. Inference and external Instrument observation run outside database
transactions. Contributions acquire locks while revalidating and committing a
state transition. Races are resolved through stale-state rejection, not reader
serialization. No lock survives a contribution response, inference call, or wake
iteration. A wake still completes at most its original Cycle.

## Request lifecycle

1. Read the current Phase and build its calculation-local context without locks.
   State, workspace and schema use the same observed Phase occurrence. The read
   may include concurrent committed changes; it does not promise a frozen snapshot.
2. Infer an action. For external Instruments, validate the input, collect the
   observation, and retain it only in a request-local `InstrumentObservation`.
   Hyperlink verification and Web Search never run in the write section.
3. Begin the contribution transaction. Acquire Substrate → Participant → active
   Cycle → active Phase. Confirm the submitted Phase and recalculate admissibility
   with a fresh context. Validation builds the schema without display serialization.
4. Dispatch, persist evidence/ledger/measurements, and advance or rest atomically.
   Instrument invocation identity, source availability and duplicate evidence are
   checked again. A stale observation persists neither an execution nor a Receipt.
5. Commit, release locks, and build the response state as a fresh optimistic read.

A formerly valid inference rejected by fresh validation remains a 409 conflict.
Invalid model output is still an error. No timeout, retry, wake-frequency or
queue-timeout settings were increased. Existing full-cycle retry behavior remains.

## Projection reads and maintenance

`observeMissing` calculates missing/obsolete values in memory, using frozen
Committal snapshots where applicable. It does not upsert measurements or retain
new directions in the database. Numeric zero uses the existing persisted
Correspondence direction/ancestry as before. Current projections remain reusable.
Graph and Proposition Inspector reads continue to display persisted projection
status without repair. Potential selection also uses read-only fallback values.

`repairMissing`, `recalculate` and `project` remain explicit mutation/maintenance
operations. Contributions maintain their changed subject's projection. The
measurement repair command locks one subject's repair at a time. An explicit
Horizon rebuild remains a maintenance transaction; it is never invoked by a page
load. `forEntry` validates existing immutable causal ownership without locks;
`currentFor` only locks when it may need to create the initial Horizon.

## Remaining lock audit

There are no `sharedLock` calls. These are the remaining application transactions
and explicit row locks (schema migrations are outside the participation runtime):

| Path | Scope and protected invariant |
| --- | --- |
| ParticipationService::contribute | Substrate → Participant → Cycle → Phase; current occurrence, shared admissibility, atomic contribution/progression |
| ContributionDispatcher::dispatch | Substrate for direct callers; shared generation, source consumption, directed reconciliation; effective Attestation row when replacing it |
| CycleEntryService::start | Substrate → Participant; one active Cycle, Origin uniqueness, causal entry and immediate rollover/recovery. Active `prepare` is a lock-free no-op, then rechecks inside the transaction if entry is needed |
| OperationalInstrumentService::execute | Substrate; recheck mutability/invocation duplication and persist the already gathered observation atomically |
| ReceiptReplayService::replay | Substrate → Participant → Phase; fresh encounter/capability/eligibility check, immutable replay invocation |
| ReceiptContextService::read/clear | Substrate → Participant → Phase; active occurrence, one load/event, reinforcement and projection invalidation |
| InstrumentExecutionService::start | Single insert, no explicit lock or transaction; ULID and foreign keys protect the pending record |
| InstrumentExecutionService::complete | Substrate → execution; pending-to-completed transition, one Receipt, evidence identity, ledger and projection writes |
| InstrumentExecutionService::fail | Execution row only; terminal status. Never enters ledger/projection code or escalates to Substrate |
| LedgerService::record | Substrate; monotonic substrate sequence allocation and membership inclusion |
| CommitmentSnapshotService::record | Ledger takes Substrate, then measurement; frozen snapshot and causal boundary application |
| ContextHorizonProjector::currentFor/include/apply/rebuild | Substrate, then Horizon for apply; unique initial/child sequence, idempotent memberships and explicit boundary ownership |
| PropositionMeasurementService::project / repair command | Substrate → Proposition; numeric projection and retained direction move together and cannot overwrite concurrent evidence maintenance |
| ReinforcementSignatureService::resolve | Global resource-key row, after Substrate/execution; accepted content version and stability clock |
| SubstrateMembershipService::join | Substrate; unique participant/membership establishment |
| DueParticipantWakeDispatcher | Connection row only; short scheduler claim commits before dispatch |
| SystemBootstrapService | Transaction for initial system identity/roles; no participation row locks |

The remaining write root is intentional: ledger sequence allocation is shared,
and admissibility depends on shared evidence, hard successors, classifications,
Commit consumption and causal Horizon boundaries. Locking only a Participant
would allow two writers to validate incompatible shared changes before either
records its ledger entries. Replacing this with narrower source/version locks
requires a separate complete conflict protocol. Reads need none of these locks.
Private Bias/Commit helpers no longer reacquire the root or add savepoints.
Nested write services may reacquire an already-owned root but never acquire one
for the first time after a child lock. No multi-Substrate write is composed while
holding a global reinforcement source lock.

Participant activity uses a short per-participant cache lease lock for
compare-and-clear. Status snapshots use batched cache reads. Queue
`WithoutOverlapping` prevents overlapping wakes for the same participant; neither
cache lock is a database lock or a substrate-wide reader barrier.

## Regression coverage

`TransactionLockOrderTest` uses committed fixtures and independent PostgreSQL
processes. It launches simultaneous plan/state/workspace reads while the parent
holds Substrate, Participant and Phase locks, with missing projections. All reads
must finish before that writer commits. Existing competing-write tests retain
Phase/ledger, generation, classification and Commit-source guarantees. Additional
cases cover receipt-load uniqueness, boundary projection idempotency, external
observation with a competing writer and stale-result rejection. Inference tests
allow another participant to contribute between steps of a full-cycle wake.

`ParticipationSnapshotTest` commits evidence from a second PostgreSQL connection
while a plan is hydrating and verifies that the next read sees it.
`OptimisticParticipationTest` rejects read transactions/mutations, preserves
missing/stale persisted projections, and checks that response contexts are built
after contribution commit. Performance fixtures and remaining scale limits are
in [participation-performance.md](participation-performance.md).

## Tragedy and recovery

[Branch Tragedy](branch-tragedy.md) observes exhaustion before the Rest transaction.
The locked contribution writes Rest, checks the observation version, and conditionally
retires the branch with one immutable event. It never walks the branch under the lock.
Cycle recovery/selection now runs outside the contribution transaction and outside
the subsequent Cycle-entry transaction; entry validates its observed field version.
Order rollover likewise follows contribution commit. Existing positions are retained
until each participant's own Rest/entry transition.
