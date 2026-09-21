# Tragedy and final-attempt Exhaustion

The [core invariants](core-invariants.md#persistence-exhaustion-recovery-and-observation) are normative. Potential projects currently instantiated reducible Tension. It does not measure the total possibility of generating new structure.

## State transitions

| Current Commit Region state | Authoritative event | Result |
| --- | --- | --- |
| Open, no Tragedy | Rest: `no_available_move` | Ordinary local recovery; no proof transition |
| Open, no Tragedy | Rest: `no_remaining_potential` | One Tragedy and one shared Tragedy Receipt; normal backward-lineage recovery |
| Tragedy, Receipt unused | Another no-Potential Rest | Commit Region remains open; no duplicate Receipt |
| Tragedy, Receipt available, final allowance unused | Eligible Distinguish/Disperse Variant at zero Potential | Atomically record one final attempt and advance only the Variant's own epoch |
| Tragedy, Receipt available | Reclassify with that Receipt | Atomically consume it and create the required classification |
| Tragedy, Receipt consumed | Continuation has positive Potential | Commit Region remains open |
| Tragedy, Receipt consumed | Subsequent no-Potential Rest on the consumed classification's actual continuation | One Exhaustion; governing Committal and its expressive region become historical and non-actionable |

A Tragedy Receipt uses `proposition.reclassify` with `proposition_id` and `receipt_id`, exactly like an ordinary Receipt. No use-Tragedy action exists. A failed or rolled-back Reclassify does not exercise the Receipt.

## Authoritative Rest

Rest is admissible only after the specialized action schema contains no Phase-advancing action. Informational Receipt metadata does not block Rest; Rest changes no Receipt load state. The backend records `cycles.rest_reason`; the submitted action contains no reason. An unavailable Gate, missing effective Attestation, or absent evidence is `no_available_move`. A grounded, attested Gate with no positive local numerical capacity yields `no_remaining_potential`. Capacity examines the current governing Commit Region, using unchanged regional Potential algebra. Other same-Horizon regions and child Horizons cannot supply its final-attempt capacity. Neither a positive historical gain nor a zero recovery frontier determines actionability: the schema and exact current failure epochs decide structural options separately.

## Attainable final attempt

Stage 5 makes the promised final attempt explicit. A unique `tragedy_final_attempts` row records the first Distinguish/Disperse Variant using the zero-Potential allowance. A Tragedy Receipt must still be unconsumed, the Commit Region open, and the shared allowance unused. Existing nonempty positions still require every current option to be spent and individually instrumented and attested. Healthy routes cannot be bypassed. The normal successful Variant advances its own epoch; Tragedy creation, Read and consumption do not advance epochs or erase failures. The shared Substrate lock and unique Tragedy key prevent competing claims.

This is a deliberate extension of the previous positive-Potential-only rule. Without it, a naturally spent zero-Potential position could emit a Tragedy Receipt but never generate a fresh classification slot to consume it. The allowance is one final exploration attempt, not an exemption for ordinary further Variants. Fresh evidence and judgment are still required along the new route before Reclassify can consume the receipt. An already eligible classification can also consume Tragedy without using the allowance.

Exhaustion additionally checks that the consuming classification lies on the resting participant's actual backward lineage. An unrelated participant's later Rest cannot close another participant's final attempt. The proof is captured outside the contribution lock and revalidated with the field version and exact receipt consumption.

`HorizonFailureService` observes the field version and local expressive provenance outside the contribution lock. Publication revalidates phase identity, Horizon, completed Rest, reason and version under the Substrate lock. A stale observation cannot close anything. Local failure markers stop at Committal/Horizon boundaries; they do not prove all possible descendants exhausted.

## Persistent proof and recovery

`horizon_tragedies` retains its historical table name but is now unique by
`governing_committal_id`, not by Horizon. Its immutable origin remains distinct
from the Tragedy Receipt's effective sharing scope. Null governing IDs identify
archived Horizon-scoped evidence without regional final-attempt authority.
`horizon_failure_witnesses` remains unique per terminal Phase, and
`receipt_consumptions` proves exercise. `commit_region_exhaustions` is unique per
governing Committal, Tragedy, Receipt and terminal Phase. Substrate locking,
field-version revalidation and database constraints protect exact-once transitions.

Tragedy retains the terminal position and uses [normal backward-lineage recovery](recovery-basins.md).
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

Receipt JSON metadata contains reference IDs only: Tragedy, opening and terminal Proposition IDs, and exhausted Committal IDs from the same Horizon. It contains no statements or serialized descendants. The read service resolves statements, producing phase/register, owner and exhausted Horizon IDs, exhaustion state and terminal provenance. Receipt inspection resolves the full local statements by ID; ordinary participation history may use bounded excerpts. The flat local ID set is retained without an age cutoff and ordered newest creation first.

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
