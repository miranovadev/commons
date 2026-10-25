# Participant experience and Preparation visibility

Approved scope: Claire's Participant Experience handoff following Evidence Resilience. This change is presentation and read-only observability; domain validation and execution semantics remain authoritative.

## Background collaboration

The interruption came from `potential_projection_pending`: ParticipationService deliberately returns no plan while the field projection is unavailable. Rendering that response directly unmounted the workspace and its forms. Ordinary RTK Query background fetching was already nonblocking.

The human page now retains its last settled Participation response only during that transient projection state. It keeps websocket invalidation and retry polling, shows a small background status, and replaces the workspace normally on a real lifecycle transition. The retained response is display context, never authority.

An open contribution retains its draft, original Phase and Closure identity, even if refreshed options omit its action. Current options still refresh. Previously selected identities remain visible. The human form permits submission for server validation; a conflict stays in the form, keeps its text, and invalidates the workspace through the existing mutation tags. Duplicate-submit protection and user-request loading feedback remain. No backend validation or locks change.

## Persisted Preparation inspection

`PreparationHistory` opens a shared `PreparationDetails` panel from the selected graph Proposition, the Participant workspace, and Participant cards. Contributed results link into the graph's historical view, including a selected result older than its bounded collection.

Two read-only endpoints use the existing substrate `view` policy:

- `GET /api/substrates/{substrate}/preparations?participant_id=…` or `?proposition_id=…`: exactly one scoped filter, newest first, 20 records per page, optional `before` cursor. Proposition history includes executions originating at that Gate and executions that contributed that Proposition.
- `GET /api/substrates/{substrate}/preparations/{execution}`: same-substrate Preparation execution only. Instruments and cross-substrate identifiers return 404.

The projection uses EvidenceExecution identity, immutable input, acknowledged presentation, terminal outcome and associated Receipt identity. Source metadata comes from the selected immutable Receipts, regardless of their later consumption. Storage paths, presentation tokens and extracted bodies are never exposed. Reading, recovery, acknowledgement, Receipt loading and measurement services are not called.

Requested selections and acknowledged deliveries are separate sections. No delivery is inferred from a request or extraction. Pending/presented statuses have no final outcome; failed execution status remains distinct from a recorded failure outcome and may have no Receipt. Missing information is shown as not recorded. Failure text is bounded, escaped and described as operational failure, not source support or refutation.

History queries exist only while the drawer is open. They use existing Participant/Proposition invalidation tags and a small periodic refresh while observed; closing the drawer stops polling. No new event payload, historical storage or state-management system is introduced.

## Verification boundaries

- Mounted UI tests exercise in-flight HTTP refresh, projection-pending responses, draft survival, same-Phase option removal, stale Phase identity, successful submissions, authoritative conflicts, local loading and real waiting transitions.
- Both observer entry points exercise the same component and read-only endpoints. All outcomes, multiple selections, oversized requested/actual boundaries, missing deliveries, escaped diagnostics and result links are covered.
- API tests cover public/private access, membership, cross-substrate isolation, bounded pagination, unchanged history after Receipt consumption, no writes on inspection, and readable metadata after source storage is removed.
- Existing realtime cache/subscription and Preparation lifecycle tests remain regression coverage. Core and Integration baseline failures must be reported separately; this scope does not authorise changing their mathematical or lifecycle fixtures.
