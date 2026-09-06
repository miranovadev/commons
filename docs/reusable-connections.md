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

## Lifecycle

A Connection cannot be deleted while it is attached to any Participant. Detach it from each Participant first, then delete it from the account Connections page.

Legacy participant connections are migrated into reusable Connections without decrypting and re-encrypting stored credential payloads. Provider configuration moves to the reusable Connection while trigger configuration remains on the Participant attachment.
