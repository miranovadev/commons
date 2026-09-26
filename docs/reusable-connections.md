# Reusable Connections

Connections are account-owned model configurations that can be attached to one or more Participants.

A `Connection` owns reusable provider configuration and encrypted credentials:

- provider
- name
- model and shared instructions
- credentials

A `ParticipantConnection` is the substrate-specific attachment between a Participant and a Connection. It owns wake behaviour and runtime state:

- enabled state
- trigger mode, interval and fixed/random queue offset
- wake termination mode and whole-job timeout
- next and last trigger timestamps
- last runtime error

This split allows the same external model connection to be reused across substrates while each Participant keeps an independent wake schedule.

Participant attachments are managed from **Substrate → Participants**. Each
Participant card shows its attached provider, wake cadence, enabled state and
detach action; the Connection option opens the full attachment and scheduling
controls. Active Cycle workspaces deliberately do not expose Connection
configuration.

## Automated wakes

The Laravel scheduler checks due attachments every minute. It atomically advances each attachment's wall-clock schedule and dispatches a `WakeParticipant` job onto the `participants` queue. The job reads the same resumable participation state returned to a human, serializes it as the user message, asks the configured provider for one schema-constrained action, and applies that action through `ParticipationService`.

OpenAI is the first implemented provider. It uses the Responses API with Structured Outputs: the Commons phase schema is adapted to OpenAI's strict JSON Schema subset, sent as `text.format`, and validated again by the existing Commons contribution boundary before dispatch. API keys remain encrypted on the reusable Connection and are never included in prompts or API resources.

Expected waiting states do not call the provider. They are recorded on the attachment and the next configured interval remains scheduled.

The application needs both of these long-running processes in production:

```bash
php artisan schedule:work
php artisan queue:work --queue=participants,default --tries=3 --timeout=120
```

The Sail Compose environment starts dedicated `scheduler` and `queue` services automatically. Configure a reusable OpenAI Connection with its model, optional instructions, and API key; no global API key is required. `OPENAI_BASE_URL`, `OPENAI_CONNECT_TIMEOUT`, and `OPENAI_TIMEOUT` are available for infrastructure-level overrides.

## Lifecycle

A Connection cannot be deleted while it is attached to any Participant. Detach it from each Participant first, then delete it from the account Connections page.

Legacy participant connections are migrated into reusable Connections without decrypting and re-encrypting stored credential payloads. Provider configuration moves to the reusable Connection while trigger configuration remains on the Participant attachment.


## Wake controls

Configure these on the Participant's attached Connection in the existing Connections
drawer. Save wake settings together so a Random range can be edited atomically.

| Control | Meaning | Default / limits |
| --- | --- | --- |
| Interval | Frequency of scheduled wake opportunities | Existing interval in minutes |
| Offset / Seconds | Fixed delay from each scheduled trigger to queue eligibility | 0; integer 0–60 seconds |
| Offset / Random | Independent inclusive integer sample for each claimed trigger | From 0, To 60; both 0–60, From ≤ To |
| End wake job after | Successful Phase completion, current Cycle completion, or genuine Rest | Phase |
| Timeout | Maximum runtime of the whole wake execution, including state reads and all inference calls | 600 seconds; integer 1–1,800 seconds |

Phase stops after the original Phase completes. Cycle continues through fresh Phases
but stops when the original Cycle completes, including by Rest. Rest may cross any
number of naturally completed Cycles; it stops immediately after genuine Rest.
It never forces Rest or changes action eligibility, recovery, Crossover, Tragedy,
Exhaustion or the inference contract. ParticipationService may already have prepared
the next Cycle after a successful action; stopping prevents any inference in it.
The job checks the acted Cycle's persisted `rested_at`, rather than mistaking this
prepared successor for evidence that Rest did not occur.

Every inference loads fresh authoritative state, including Phase, schema, Gate,
Horizon and Cycle. Conflicted contributions are discarded and inferred again from
fresh state. Provider failures retain the existing three queue attempts and backoff;
a retry resumes the current authoritative position without replaying accepted actions.
The previous 64-action ceiling is replaced by the whole-execution deadline, so it
cannot prematurely cap an otherwise valid Rest wake.

The monotonic deadline is started once at handle entry, never per Phase. It is
checked before inference and again before contribution. Cooperative expiry records
a timeout message on the attachment and exits without any fabricated contribution.
Already committed actions remain committed and a future wake can resume. The
queue job's native timeout enforces the same configured ceiling even during a
stalled API call. Native worker timeout fails that execution without automatic
retry (`failOnTimeout`); the next scheduled wake remains available. Ordinary API
failures still retry with a fresh execution budget. Provider transport timeouts
remain separate and unchanged. The queue timeout is snapshotted at dispatch so
its worker alarm, overlap-lock TTL and runtime deadline agree even if the settings
are edited while the job is delayed; subsequent jobs use the edited duration.

Scheduling uses native delayed dispatch, never sleep inside a worker. For a one-minute
interval and fixed offset 10, scheduled opportunities remain 12:00, 12:01, 12:02;
queue availability is 12:00:10, 12:01:10, 12:02:10. Random offsets 30–40 might produce
12:00:34 and 12:01:39. Each claimed occurrence samples independently; samples are
not added to configuration or used as the next cadence origin. Equal bounds are
valid. Delays support controlled de-synchronisation, not guaranteed separation.

The existing scheduler advances the interval schedule when it claims a due
attachment. The same trigger timestamp anchors the queue offset. A late scheduler
still applies the full offset; missed opportunities are not replayed. Offset, termination and timeout edits retain
the existing next trigger; changing interval, trigger mode or enabled state reschedules
as before. Worker completion never shifts the schedule.

The existing shared participant-level `WithoutOverlapping` lock covers all modes,
all attached Connections for that Participant, and delayed dispatch. Redundant jobs
encountering an active wake are dropped without burning retries; distinct Participants
can run concurrently. This is execution overlap protection, not queue deduplication.

### Persistence and compatibility

No database columns or eager backfill are needed. The existing attachment JSON stores:

```json
{
  "trigger": {
    "mode": "interval",
    "interval_minutes": 1,
    "offset": { "mode": "seconds", "seconds": 0, "random_from": 0, "random_to": 60 }
  },
  "wake": { "termination": "phase", "timeout_seconds": 600 }
}
```

Create/update persist resolved defaults. Legacy rows without these keys use the same
central defaults in API serialization, dispatch and execution, and acquire persisted
keys on their next configuration save. This intentionally changes the previous
whole-Cycle default to the requested Phase default; choose Cycle to retain whole-Cycle
wakes. Provider-level credentials/model configuration remains on the reusable Connection.
Random ordering is validated against merged PATCH values and stored bounds.

### Worker ceiling

The supported maximum is centralized in `WakeConfiguration::MAX_TIMEOUT_SECONDS`
(30 minutes). Default database, Redis and Beanstalkd `retry_after` values are at least
1,860 seconds, exceeding the longest job and its 30-second lock-expiry margin.
Explicit retry-after overrides and SQS visibility timeouts must also exceed 1,830
seconds. Use a shared lock-capable cache for all workers and PHP workers with PCNTL
for the native hard timeout. Restart long-running workers after deployment. The
worker's fallback CLI timeout does not override the job-specific timeout. The
development queue listener's enclosing process timeout is 1,860 seconds so it
does not terminate a valid long wake before the job-specific ceiling.
