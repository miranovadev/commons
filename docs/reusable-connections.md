# Reusable Connections

Connections are account-owned model configurations that can be attached to one or more Participants.

A `Connection` owns reusable provider configuration and encrypted credentials:

- provider
- name
- model and shared instructions
- credentials

A `ParticipantConnection` is the substrate-specific attachment between a Participant and a Connection. It owns wake behaviour and runtime state:

- enabled state
- trigger mode and interval
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
