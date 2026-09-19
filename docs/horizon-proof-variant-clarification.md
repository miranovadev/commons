# Variant eligibility in the Horizon proof stack

This clarification is normative for Distinguish (Phase 1) and Disperse (Phase 4).
Mediate still requires a second proposition to reconcile. No new action or
participant-count requirement is introduced.

An empty viable structural position exposes Variant. Once a Variant exists,
it must receive its own completed Instrument execution, producing a Receipt
in the Horizon, and at least one effective Attestation. Only when those
prerequisites have been met, every current route is proven spent in this
position's current exploration epoch, and positive Potential Charge remains in
the Horizon does another Variant become an available option. A viable route
blocks redundant Variant generation. See [exploration epochs](exploration-epochs.md).

Successful paths into an explicitly exhausted child Horizon are resolved
history, not failed options. They are excluded from the viable population
without inventing failure rows. This propagates to a shared expressive source
only while all other outgoing continuations are also resolved or currently
failed; a live alternative prevents it. Their historical numerical gain does
not establish remaining capacity. Ordinary epoch-scoped failures remain separate.

Each new Variant repeats that sequence. A shared Receipt generated against a
different Variant does not satisfy the new Variant's Instrument prerequisite.
Any Participant may perform the Instrument or Attestation; neither is a
personal quota. Receipts still belong to the Horizon and any eligible
Participant may consume them through Reclassify.

Receipt generation or consumption alone never reopens Variant. No prior
Mediate Rest is required, and there is no fixed two-sibling limit. One
Participant can reach Mediate without a counterpart, Rest/recover, and create
another Variant when it returns, provided these prerequisites hold. Healthy
existing continuations retain their ordinary Attest/Traverse options.

The backend checks every currently viable direct opportunity at the same
producing Phase/register position before allowing another. Failed opportunities
remain visible historical evidence. Failure only blocks actions when its Horizon,
producing structural position and epoch match the current state. A successful
Variant advances only that position's epoch, reopening older failures under the
normal action rules without clearing them. An empty viable position can
start an attempt even at zero currently instantiated Potential; this preserves
the distinction between Potential and proof of Exhaustion.

Potential Charge is read from the maintained Horizon-local topology and all
local Committal regions, without recovery/frontier shadowing, using the same
positivity threshold as authoritative Rest-reason derivation.
No global graph reconstruction or participant count is used. The action schema
and contribution dispatcher share the same eligibility service.

Stage 5 adds one explicit lifecycle exception to the numerical requirement:
an unconsumed Tragedy Receipt authorizes a single shared final Distinguish or
Disperse attempt at zero Potential. All existing structural, current-spentness,
Instrument and Attestation checks still apply. The successful Variant records
the unique allowance claim and advances only its own epoch atomically. Receipt
creation, Read and consumption themselves do not reopen epochs. See
[Tragedy and final attempts](branch-tragedy.md#attainable-final-attempt).

Tests: `ExpressiveEvidenceGatingTest` covers both expressive phases, positive
and zero Potential, Instrument-only and Attestation-only failures, repetition
beyond two Variants, same-Participant creation, shared-Receipt isolation,
consumption independence, and the existing next-Cycle Traverse rule.
