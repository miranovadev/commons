# Receipts, Reinforcement and Commitment

The [Core Invariants](core-invariants.md) take precedence over this implementation.

A Receipt is the historical outcome of an Instrument. Active Reinforcement comes
from qualifying evidence currently loaded by a Participant. Verification, Receipt
creation and participant uptake are separate boundaries. A caller-supplied signature
or verification flag never establishes qualification.

Receipt capabilities (`readable`, `replayable`), Web Search, configuration and
immutable replay provenance are defined in [Instruments](instruments.md). Replay
is a new Instrument execution; Gate options change Participant memory. Neither initial execution
nor Replay automatically loads evidence.

For discovery, ownership and prompt activation, see the normative [Receipt model](receipt-region-context.md). Receipts ground Propositions; Attestations judge Propositions.

## Persistent participant context

Optional `receipt_options.load/unload` accompanies a successful forward contribution
for the exact current Gate Receipt references. Changes are desired states,
idempotent and atomic with that contribution. Rest accepts no memory changes.
There are no standalone Receipt actions. Informational catalogue evidence cannot
block Rest or independently advance a Phase.

`participant_receipt_contexts` stores one row per Participant/Receipt, with the
original evidence subject, evidence signature, `loaded_at` and nullable `cleared_at`.
Participants belong to one Substrate. Loads survive Cycles and Context Horizons;
they have no Horizon expiry. The packet's `receipts` list includes only encountered
evidence, with `available`, `already-in-context`, `loaded` or `unqualified` status.
Loaded evidence is active only when its grounded Proposition occupies the participant-facing Read register. Other appearances are metadata with loaded state.
Persistent state is not an ever-growing dump of all remembered evidence in every
inference packet. Source text is data, never instructions.

The domain `clear` operation deactivates a load and recalculates its contribution.
Gate `receipt_options.unload` exposes that desired state alongside a forward Phase contribution (never Rest). `receipt_context_events`
preserves each load/clear transition with its Phase and substrate ledger sequence, even when the same state row
is reloaded. No Receipt, historical allocation or commitment snapshot is deleted.
All mutations acquire Substrate, Participant and Phase locks in that order.

## Qualification and identity

`citation` (Cite) resolves an existing Commons Proposition or Attestation in the
same substrate. Creating a Cite Receipt records a reference; it does not itself
grant Reinforcement. Reading resolves the referenced same-substrate object and
uses a versioned identity derived from its type and ID. An Attestation citation
loads its direction/rationale and associated Proposition. If that Proposition is
already in the exposed lineage, Read, Gate, Write or loaded Cite memory, a new Read is unavailable.
Once loaded, its status remains loaded even if the reference reappears in lineage.
`artefact` remains unsupported for reinforcement until it has a qualifying verifier.

Hyperlink now performs an HTTP GET. Verification proves retrieval of bounded
content from a public resource, not the truth of its assertions. Identity is the
lowercase, IDNA-normalized hostname plus case-sensitive path. It excludes scheme,
ports, query and fragment; removes a trailing hostname dot and path dot segments;
and normalizes percent encodings of unreserved characters. Non-root trailing
slashes, path case, encoded separators and `www` subdomains remain distinct unless
an HTTP redirect resolves them to the same final resource.

The verifier fetches the normalized path **without the submitted query or
fragment**. This deliberately under-counts applications whose documents exist
only behind query parameters. Validated redirects (maximum three) establish the
final resource identity. Only standard HTTP(S) ports are fetched. DNS addresses
must be public; cURL pins the validated address and each redirect is checked
again. TLS verification remains enabled, proxies are disabled, each request has
a three-second connection/ten-second request limit, and content is limited to
2 MiB. Compressed responses are rejected to keep the size bound meaningful.
PHP `curl` and `intl` are required.

HTML fingerprints hash decoded text with script/style content and markup removed,
and whitespace collapsed. Text/JSON collapse whitespace; other media hash bytes.
New text/HTML/JSON Receipts retain a verifier-produced excerpt of at most 1,200
characters, with a truncation flag, for context loading. Binary resources and older
verified Receipts can expose retrieval metadata without a text excerpt. Loading
never silently fetches changed content; refreshing is an Instrument execution or an admissible Instrument replay.
This is a conservative content identity, not a claim of semantic equivalence.
HTML changes only to markup, scripts, styles or link destinations may be missed.

## Server-controlled version clock

`ReinforcementSignatureService` owns one global clock per normalized resource,
across substrates. The initial successfully verified content fingerprint establishes
an accepted version immediately. A changed fingerprint must be observed again
**at least seven days after its first observation**, with at least seven days
since the prior version was accepted. Any intervening observed fingerprint change
restarts the pending interval. Returning to the accepted content cancels it.
This establishes stability at observations, not continuous monitoring between them.

HTTP `Last-Modified` is recorded only when it is a valid, non-future HTTP date.
It is source-controlled, so it cannot shorten the server waiting period. Missing,
malformed and future dates use the same server-observed fallback. ETags and
user-supplied/page timestamps do not manufacture new versions.

The signature is SHA-256 of the versioned identity format, normalized resource,
and accepted content fingerprint. Re-verifying the same content resolves the same
signature; reverting to old content resolves its old signature after qualification.
Query changes, fragments and timestamp changes alone cannot create new identities.
Receipts share signatures freely: a unique signature on the Receipt table would
incorrectly discard independent participant history.

## What counts

A qualifying loaded Cite, Hyperlink or Web Search Receipt contributes to its Instrument's
Proposition on behalf of the **reading Participant**, who need not be its author
or executor. Originating evidence may be read later without re-verification.
Receipt creation alone contributes zero.

Active loads are counted once per Participant/Proposition, deduplicating across
Commit-inherited source state and preserved reclassification receipts. Thus equivalent signatures, different sources,
versions, Receipts and repeated Cycles cannot multiply one Participant's count.
Multiple Receipts may remain loaded; clearing one preserves the contribution if
another qualifying load still supports that same state. Clearing the last removes
it. Different Participants can independently load the same Receipt/signature.

Commit inherits participation by reference and freezes its recorded measurements.
Reclassify preserves completed proposition-level Receipts and their loaded
contributions, but starts new Attestations and touches. A superseded classification
retains its historical reinforcement count. Neither classification applies that
count to measurement values. Variant, Revise and Reconcile
start fresh evidence state. A Receipt on
a historical Attestation resolves to that Attestation's Proposition and remains readable after supersession. New Receipts always belong to the observed Proposition.
Attestation lean is the mean of effective signed Attestations under fixed Commit Region polarity. Direction replay uses Attestation creation/replacement and topology events, never Receipt load/clear events. At zero, retained direction follows Attestation history. Participant evidence context still records every load and clear.

Every valid Instrument attempt retains a Receipt. Failed retrieval and changed
versions awaiting stability remain unqualified. Historical `reinforcement_status`
metadata is an event-time fact, not current participant state. New executions
record `not-loaded`; participant packets derive the current status separately.
Re-verification is still required after the stability interval; earlier Receipts
are not retroactively promoted.

## Receipt-neutral measurement

Receipts may establish evidence, authority, context and reinforcement count.
They do not alter Correspondence, Chirality or Closure values.
Reinforcement changes evidential context, never semantic measurement value.
Neither positive nor negative observable polarity is preferable.

Closure is the pure mean `(Depth + Cadence) / 2`, retaining structural history,
bootstrap, directions and admissibility. Chirality retains its v1 cross-Domain
product, with each Domain lean the unweighted mean of effective signed Attestations.
Correspondence remains structural, with unit-weight Shape features and Cardinal
Position comparison. Receipt loads and clears do not enter Orientation replay.

`ReinforcementService` counts qualifying active Participant uptake and supplies
public counts and grounding diagnostics. It provides no measurement weights or
receipt increment. Existing stored contribution/receipt ratio configuration has
no effect on measurement. Reinforcement is not moved into attenuation, population
aggregation, or any derived field.

Ordinary evidence changes may invalidate disposable projections; recalculation
uses `receipt-neutral-observables-v15`. Frozen CommitmentSnapshot values remain
historical, including Closure recorded under earlier reinforced semantics.
Memory-only Receipts keep their existing non-field-invalidating behavior.

## Commitment and branching

All operational Commit paths require an effective Attestation, a completed
Receipt, an uncommitted direct source, and the phase, region, register and
Depth/Cadence alignment constraints. Snapshot creation checks grounding again.
Loaded Reinforcement remains diagnostic metadata and has no required minimum.
Neither evidence existence nor reinforcement overrides divergent Depth/Cadence.

The original Origin seed remains the explicit initialization exception:
`origin-bootstrap-v1`, first ledger Proposition, no parents and no prior snapshot.
Its originating evidence supplies no counted reinforcement until explicitly loaded. Without that seed,
the Committal-only Cycle-entry rule would make initialization impossible.

Reinforcement does not consume source Propositions. Mediate may reuse structurally
reachable hard-superseded Reads/Gates in the same region under the existing
Read-direction and register rules. Each directed pair is generated once: `A+B→X`, `B+A→Y` and `A+C→Z` can coexist;
each expressive result earns its own subsequent reinforcement. Measurement active
head selection, compact lineage presentation and other phase constraints retain
their existing meanings. No Sybil or coordination policing is introduced.

## Original load-context upgrade

The Receipt region refinement needs no data migration; its rollout instructions are in [Receipt model](receipt-region-context.md#compatibility-and-rollout). The following describes the earlier introduction of participant load contexts.


Run the additive migration before serving the new code. It creates empty context
and event tables and invalidates disposable measurement projections. Existing
`reinforcements` rows remain historical verification allocations, but are no
longer the source of active counts. **No loads or Read events are backfilled.**
Consequently, old automatic reinforcement stops contributing until Participants
explicitly load qualifying encountered Receipts. Their historical snapshots remain
unchanged. Verified Hyperlink Receipts with accepted signatures remain readable;
old signatures without verifier provenance do not qualify. Existing valid Cite
references are resolved against the Substrate when offered for Read.

Rollback refuses to discard recorded context transitions. Historical Receipt and
signature migrations retain their own safeguards against destructive rollback.
