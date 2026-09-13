# Commons transaction lock order

Shared Commons mutations acquire the owning **Substrate row FOR UPDATE first**,
before any child row lock or write (including inserts that take foreign-key
locks). Hold it until the outermost transaction commits or rolls back.

Participant actions then acquire Participant → active Cycle → active Phase →
contribution-specific rows. Cycle entry acquires Substrate → Participant before
checking or creating a Cycle. Resolve ownership with ordinary reads first;
ownership identifiers are not reassigned by Commons operations.

The Substrate is the serialization boundary for its ledger, shared propositions,
measurements and horizon transitions. Different contribution branches need
different child rows; they cannot race with each other after owning the same
root. Reacquiring an already-owned Substrate lock in a nested service is safe;
acquiring it for the first time after a child lock is not. Nested transactions
do not release locks at the end of their callback.

## Audited paths

| Path | Lock discipline |
| --- | --- |
| ParticipationService::contribute (including Rest and Traverse) | Substrate → Participant → Cycle → Phase, before validation, dispatch and progression |
| CycleEntryService::enter | Substrate → Participant, before Origin, recovery, potential selection or Cycle writes |
| ContributionDispatcher::dispatch | Substrate before direct dispatch; Bias/Commit nested root locks are reacquisitions |
| Instrument start / operational execution | Substrate before execution inserts and their foreign-key locks |
| Instrument complete / fail | Substrate → InstrumentExecution; complete then resolves evidence and records the Receipt |
| LedgerService::record | Substrate before sequence allocation or horizon inclusion |
| CommitmentSnapshotService::record | Ledger acquires Substrate before measurement lock and horizon transition |
| ContextHorizonProjector current/include/apply/rebuild | Substrate before horizon locks, deletion or reconstruction |
| PropositionMeasurementService::project | Substrate → Proposition → measurement upsert, including standalone plan repair |
| SubstrateMembershipService::join | Substrate before participant/membership insertion |
| DueParticipantWakeDispatcher | Connection-only claim transaction ends before dispatch; never enters participation while holding that lock |

Phase progression and polarity creation are internal steps of the owning
contribution/entry transaction. Callers that compose lower-level services must
acquire the Substrate before their first write, not wait for Ledger::record to
do it later. Legacy console insert commands are single statements and do not
escalate to ledger/root locking; modernizing their obsolete payloads is separate.

Reinforcement source rows are global. A supported operation resolves one source
after taking its Substrate and execution locks, then only touches rows in that
same Substrate. Never acquire another Substrate while holding a source lock.
Future multi-Substrate or multi-source batches must acquire all roots by ascending
ID, then all source keys in sorted order, before lower-level work.

## Plan reads and contention

Standalone plans retain their existing REPEATABLE READ snapshot and serialization
retry policy. Warm projections require no new root lock. A missing or obsolete
projection acquires the Substrate before its first repair write and holds it
through the enclosing plan transaction, preventing opposite node visitation
orders from becoming a deadlock. No deadlock retries were added to actions:
synchronous instrument verification can perform external I/O, so replaying the
entire action deserves a separate idempotency design.

Contributions already serialized at ledger recording. They now wait before
holding Participant/Cycle/Phase locks, and standalone repairs join that same
serialization boundary. Synchronous hyperlink verification still runs inside
the action transaction and therefore extends root lock duration. Moving network
verification outside it requires revalidation of the action on return and is
not part of this lock-order fix.

## Regression coverage

`php artisan test --filter=TransactionLockOrderTest` uses committed fixtures and
an independent PHP/PostgreSQL backend. The parent holds the Substrate, observes
the worker waiting via pg_stat_activity, exercises the competing operation,
then releases the root. Timeouts bound failures; sleeps only poll the barrier.
Coverage includes entry versus contribution for the same Participant, two
Participants contributing within one Substrate, standalone Instrument completion,
and standalone measurement projection. Assertions check completion, active Phase
uniqueness, one Receipt, and contiguous ledger sequence allocation. These tests
skip on non-PostgreSQL databases; the existing CI runs PostgreSQL 18.
