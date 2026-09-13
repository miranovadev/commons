# Receipts, Reinforcement and Commitment

The [Core Invariants](core-invariants.md) take precedence over this implementation.

A Receipt is the historical outcome of an Instrument. Reinforcement is a counted
participant contribution backed by qualifying verified evidence. Receipt presence,
a caller-supplied signature, or `content_verified` in submitted metadata is never
authority to create Reinforcement. Old Receipts and immutable commitment snapshots
remain unchanged; old signatures are not automatically promoted.

## Qualification and identity

`citation` (Cite) resolves an existing Commons Proposition or Attestation in the
same substrate. Creating a Cite Receipt records a reference; it does not itself
grant Reinforcement. The current Cite verifier produces no reinforcement signature.
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

A completed, qualifying Hyperlink Receipt can allocate one Reinforcement to its
Instrument's Proposition and executing Participant. It must be a later interaction:
an execution in the Proposition's creating Phase is originating evidence and does
not count. The author may qualify on a later Phase; “independent” does not prohibit
the author or require a new source for every Participant.

Different Participants may reinforce the **same accepted signature**. One
Participant cannot increase weight through another source, version, Receipt,
Attestation target, or Cycle. Service allocation is serialized under the existing
substrate ledger lock and the database uniquely constrains Proposition/Participant
and Receipt allocation. The Receipt remains the ledger event; Reinforcement points
to that event rather than creating a parallel contribution history.

Commit inherits counted participants by reference through Commit edges only,
deduplicating participants across that inherited state. Variant, Revise,
Reclassify and Reconcile start with no inherited reinforcement. A receipt attached
to an Attestation still verifies its Instrument's exact Proposition. Changing the
Attestation does not erase the historical persistence observation; effective
Attestation/Receipt grounding and lean continue to respect Attestation supersession.

Every valid Instrument attempt retains a Receipt: Cite, failed retrieval, an
unqualified changed version, and an already-counted participant are visible history.
Failures complete with an explicit unverified outcome, not claimed verification.
Metadata distinguishes `counted`, `originating-evidence`,
`participant-already-counted` and `non-reinforcing`; version metadata explains
`version-awaiting-stability`. Re-verification is required after the stability
interval; the server does not retroactively promote earlier Receipts.

## Local Closure, then propagation

Depth and Cadence keep their structural definitions, comparison normalization and
hysteretic directions. Reinforcement no longer multiplies path length. Given
their local values `D` and `C`, counted participants `N`, and the existing
`observables.contribution_receipt_ratio` `q` (default 3):

```
persistence mass = N / q
local Closure = (D + C + persistence mass) / (2 + persistence mass)
```

Depth and Cadence each contribute one normalized observation. Each counted
Participant contributes a persistence observation (+1) at the existing ratio.
The weighted mean stays within [-1, 1], preserves `(D + C) / 2` when N=0, and
approaches persistence as independent participation increases. It is monotone in
N and does not add an arbitrary fixed bonus. Reinforcement keeps the existing
participant-weighted Correspondence feature and effective-Attestation lean roles,
using allocated contributions rather than raw signed Receipt counts.

`LocalMeasurementService` forms this Closure with no distance argument.
`LineageMeasurementService` then aggregates **that calculated Closure value** with
`1 / (1 + distance)^2`, just as it aggregates the other measurements. It must not
average reinforcement counts and apply the nonlinear Closure formula afterward.
Depth/Cadence are propagated independently for their existing hysteresis gate.
New evidence invalidates disposable substrate projections; immutable snapshots
are preserved. Calculator version is `participant-reinforcement-v4`.

## Commitment and branching

All operational Commit paths require an effective Attestation, completed evidence,
at least one counted Reinforcement, and the existing phase, region, register and
Depth/Cadence alignment constraints. Snapshot creation checks grounding again.
Strong starting measurements and Cite alone cannot unlock Commit. Reinforcement
alone cannot overcome a divergent Depth/Cadence gate.

The original Origin seed remains the explicit initialization exception:
`origin-bootstrap-v1`, first ledger Proposition, no parents and no prior snapshot.
Its originating evidence supplies no counted reinforcement. Without that seed,
the Committal-only Cycle-entry rule would make initialization impossible.

Reinforcement does not consume source Propositions. Mediate may reuse structurally
reachable hard-superseded Reads/Gates in the same region under the existing
Read-direction and register rules. Thus `A+B→X`, `A+B→Y` and `A+C→Z` can coexist;
each expressive result earns its own subsequent reinforcement. Measurement active
head selection, compact lineage presentation and other phase constraints retain
their existing meanings. No Sybil or coordination policing is introduced.

## Upgrade

Run the additive migration before serving the new code. Existing Receipt history
does not establish verifier provenance and is intentionally not backfilled as
Reinforcement. Existing expressive candidates must earn qualifying later evidence;
historical Committal snapshots remain valid history. Rollback refuses to recreate
the old unique-signature constraint if shared Receipt signatures now exist, rather
than deleting history to make the rollback succeed.
