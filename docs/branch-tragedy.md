# Charge-boundary Tragedy and retained exhaustion

**Tragedy is the first expressive proposition within a Commit Region whose
resolved field state reaches zero Charge. Potential Charge does not trigger Tragedy.**

A fresh Variant, Revise, Reconcile or Reclassify is validated and created normally.
After its ordinary observable measurements (including the contribution's Receipt
load/unload effects) resolve, `ProjectionService::field` supplies the authoritative
Tension and Charge. Its existing seven-decimal rounding is the only precision
rule: `Charge = 1 - |Tension|`. Positive Charge, including increasing Tension,
does not create Tragedy. Either signed boundary (`Tension = +1` or `-1`) does.

The contribution transaction records one Tragedy and its unique Receipt for the
resulting proposition's owning Commit Region, resolved from its creating Cycle.
Foreign causal operands do not transfer ownership. The resulting expressive
proposition is the terminal subject; the prior Gate is not the catastrophe.
Committals, existing-proposition Attestations/Instruments/Traverse, maintenance
recalculation, and Rest do not create a new Tragedy. Failed/rolled-back writes
leave no Tragedy or Receipt. Repeated boundary births preserve the first identity
and do not themselves exhaust the Region.

> Stage 2 boundary: Receipt visibility, consumption and recovery scope are retained.
> Exhaustion and recovery rules below remain the legacy lifecycle, pending Stage 6.
> They are not a definition of what repeated Charge-boundary contact will mean.

## State transitions

| Current Commit Region state | Authoritative event | Result |
| --- | --- | --- |
| Open, no Tragedy | Fresh expressive write resolves at Charge zero | One Region Tragedy and one Tragedy Receipt |
| Open, no Tragedy | Rest, including `no_remaining_potential` | Ordinary Rest/recovery; no Tragedy |
| Any open Region | Fresh expressive write has positive Charge | No Tragedy |
| Region with Tragedy | Another expressive birth at Charge zero | Retain the existing Tragedy and Receipt; no new exhaustion consequence |
| Tragedy, Receipt available | Eligible foreign homologous Bias Reclassify using that exact owning-Region Receipt | Atomic boundary-escape consumption/attachment |
| Tragedy, Receipt consumed | Subsequent evidenced no-Potential Rest on the consumed classification's actual continuation | Legacy exhaustion proof, retained pending Stage 6 |

For Bias, the exact unresolved Tragedy Receipt of the acting Commit Region
only authorizes a qualified foreign homologous source. Local and recorded
mediated sources require ordinary evidence and reject Tragedy Receipts, including
when a mediated source also belongs to the homologous population. Same-Region
expressive paths are ordinary. Population supplies possible exits; the Receipt
supplies permission to leave. Source-specific schema bindings and transactional
revalidation enforce this distinction. Historical non-Bias consumption remains auditable but is no longer a live
action. Recovery catalogue transport remains transitional pending Stage 6,
without granting Bias authority in a foreign or recovery destination Region.
Tragedy Receipts are boundary warrants, not ordinary evidence: they cannot be
loaded or replayed and satisfy no Instrument, Attestation, ordinary Reclassify
or shared-evidence prerequisite.

A Tragedy Receipt uses `proposition.reclassify` with `proposition_id` and `receipt_id`, exactly like an ordinary Receipt. No use-Tragedy action exists. A failed or rolled-back Reclassify does not exercise the Receipt.

## Authoritative Rest

Rest is admissible only when the action schema has no available advancing action.
The backend retains `no_remaining_potential` as a numerical diagnostic for an
evidenced actionless Region and as the existing exhaustion witness. It no longer
creates Tragedy. No reason is accepted from a participant. The separate
`no_available_move` case and ordinary spentness/epoch protections are unchanged.
Potential remains descriptive, with unchanged algebra; it cannot veto expression.

## Attainable final attempt

The old special Variant allowance is retired. `TragedyFinalAttemptService` and
its dispatcher bookkeeping are removed. `tragedy_final_attempts` remains only
as historical storage for existing migrations and archived provenance; live
contributions neither read nor write it. No new use is assigned to that table.
Structurally valid Variants need no numerical or Tragedy allowance.

The following consumption/exhaustion requirements are retained pending Stage 6.
Exhaustion additionally checks that the consuming classification lies on the resting participant's actual backward lineage. An unrelated participant's later Rest cannot close another participant's final attempt. The proof is captured outside the contribution lock and revalidated with the field version and exact receipt consumption.

`HorizonFailureService` observes the field version and local expressive provenance outside the contribution lock. Publication revalidates phase identity, Horizon, completed Rest, reason and version under the Substrate lock. A stale observation cannot close anything. Local failure markers stop at Committal/Horizon boundaries; they do not prove all possible descendants exhausted.

## Persistent proof and recovery

`tragedies` names the event independently of its scope and is unique by
`governing_committal_id`, not by Horizon. Its immutable origin remains distinct
from the Tragedy Receipt's effective sharing scope. Null governing IDs identify
archived Horizon-scoped evidence without regional final-attempt authority.
`horizon_failure_witnesses` remains unique per terminal Phase, and
`receipt_consumptions` proves exercise. `commit_region_exhaustions` is unique per
governing Committal, Tragedy, Receipt and terminal Phase. Substrate locking,
field-version revalidation and database constraints protect exact-once transitions.

A Tragedy birth does not move participants. A later ordinary Rest uses [normal backward-lineage recovery](recovery-basins.md).
The unresolved local Committal is the nearest recovery boundary. Once its region
exhausts, recovery can reach the preceding ancestral region. Only exhausting a
boundary-governing region permits leaving its child Horizon. This does not move
other Participants directly or retire other live regions. A later basin requires
its own Tragedy and consumed-continuation failure proof.

Recovery stops at the nearest eligible non-exhausted recorded ancestral
Committal, regardless of greater values at older ancestors. Recovery never resets to a Horizon
provenance node or searches siblings or descendants. A shared Tragedy Receipt,
regional calculation population or visible exhausted history creates no
movement edge. No edge means no movement.

Origin persists uniquely even if its own Commit Region reaches Exhaustion. Its exhausted Committal children remain in the substrate and local context. No action deletes, prunes or forgets these records.

An incoming successful route whose outgoing Commit Region has exhausted is contextual
history, not a continuation failure. `ExhaustedContinuationService` propagates
that state backward through expressive nodes only when every other outgoing
continuation is also exhausted or failed in its exact current epoch. Any live
alternative stops propagation, and Committals are hard boundaries. The resulting
`continuation_exhausted` flag preserves the proposition and evidence in context
while excluding it from action targets and viable generation populations.
Historical gain on these resolved paths cannot supply numerical exploration
capacity. No failure row is inserted or rewritten for this successful history.

## Receipt evidence

Receipt JSON metadata retains the birth trigger and canonical resolved Tension/Charge, plus reference IDs: Tragedy, opening and terminal Proposition IDs, and exhausted Committal IDs from the same Horizon. It contains no statements or serialized descendants. The read service resolves statements, producing phase/register, owner and exhausted Horizon IDs, exhaustion state and terminal provenance. Receipt inspection resolves the full local statements by ID; ordinary participation history may use bounded excerpts. The flat local ID set is retained without an age cutoff and ordered newest creation first.

An unconsumed Tragedy Receipt follows legitimate backward recovery from its current unresolved Commit Region, regardless of which Participant performs that recovery. It is shared with every Participant in the destination Commit Region, with recovery stopping at the nearest eligible non-exhausted ancestral Committal. A later legitimate recovery from that basin moves its effective region again. Normal movement and recovery from another basin do not. The original trigger Participant remains historical provenance only. The Tragedy-only scope projection updates atomically with recovery entry; foreign lineage cannot transport it. Consumption freezes its historical attachment. Ordinary Receipt regional origin remains immutable and does not constrain recovery selection.

Catalogue and Proposition references expose metadata with participant loaded state. Full evidence activates only when loaded and grounding the participant-facing Read. Loading, visibility and one-use consumption remain distinct. See the normative [Receipt model](receipt-region-context.md).

## Realtime and diagnostics

Receipt creation/consumption and regional Tragedy/Exhaustion emit compact ID/state invalidations after commit. Workspace and graph fetch current state through existing caches and subscriptions. Participant movement events remain unchanged; rollback emits no proof events.

`commons:exhaustion-audit` checks persisted witness, consumption and transition consistency without modifying history. `commons:recovery-inspect` explains a local hypothetical recovery. Numeric reference recomputation remains a separate explicit diagnostic. Ordinary reads never scan the substrate to prove exhaustion.

See [migration and compatibility](horizon-proof-migration.md) before rollout.

Regional migration preserves old `horizon_exhaustions` as immutable evidence.
Only proofs whose Tragedy, Receipt scope, consumption and final failed lineage
identify the same region can acquire regional authority. See
[regional migration](commit-region-exhaustion.md) for the explicit compatibility policy.
