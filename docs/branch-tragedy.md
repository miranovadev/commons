# Tragedy and final-attempt Exhaustion

The [core invariants](core-invariants.md#persistence-exhaustion-recovery-and-observation) are normative. Potential projects currently instantiated reducible Tension. It does not measure the total possibility of generating new structure.

## State transitions

| Current Horizon state | Authoritative event | Result |
| --- | --- | --- |
| Open, no Tragedy | Rest: `no_available_move` | Ordinary local recovery; no proof transition |
| Open, no Tragedy | Rest: `no_remaining_potential` | One Tragedy and one shared Tragedy Receipt; normal backward-lineage recovery |
| Tragedy, Receipt unused | Another no-Potential Rest | Horizon remains open; no duplicate Receipt |
| Tragedy, Receipt available, final allowance unused | Eligible Distinguish/Disperse Variant at zero Potential | Atomically record one final attempt and advance only the Variant's own epoch |
| Tragedy, Receipt available | Reclassify with that Receipt | Atomically consume it and create the required classification |
| Tragedy, Receipt consumed | Continuation has positive Potential | Horizon remains open |
| Tragedy, Receipt consumed | Subsequent no-Potential Rest on the consumed classification's actual continuation | One Exhaustion; opening Committal becomes historical and non-actionable |

A Tragedy Receipt uses `proposition.reclassify` with `proposition_id` and `receipt_id`, exactly like an ordinary Receipt. No use-Tragedy action exists. A failed or rolled-back Reclassify does not exercise the Receipt.

## Authoritative Rest

Rest is admissible only after the specialized action schema contains no Phase-advancing action. Optional Receipt Read may remain beside Rest. The backend records `cycles.rest_reason`; the submitted action contains no reason. An unavailable Gate, missing effective Attestation, or absent evidence is `no_available_move`. A grounded, attested Gate with no positive local numerical capacity yields `no_remaining_potential`. Capacity examines every local Committal region without recovery-frontier shadowing or borrowing from child Horizons. Neither a positive historical gain nor a zero recovery frontier determines actionability: the schema and exact current failure epochs decide structural options separately.

## Attainable final attempt

Stage 5 makes the promised final attempt explicit. A unique `tragedy_final_attempts` row records the first Distinguish/Disperse Variant using the zero-Potential allowance. A Tragedy Receipt must still be unconsumed, the Horizon open, and the shared allowance unused. Existing nonempty positions still require every current option to be spent and individually instrumented and attested. Healthy routes cannot be bypassed. The normal successful Variant advances its own epoch; Tragedy creation, Read and consumption do not advance epochs or erase failures. The shared Substrate lock and unique Tragedy key prevent competing claims.

This is a deliberate extension of the previous positive-Potential-only rule. Without it, a naturally spent zero-Potential position could emit a Tragedy Receipt but never generate a fresh classification slot to consume it. The allowance is one final exploration attempt, not an exemption for ordinary further Variants. Fresh evidence and judgment are still required along the new route before Reclassify can consume the receipt. An already eligible classification can also consume Tragedy without using the allowance.

Exhaustion additionally checks that the consuming classification lies on the resting participant's actual backward lineage. An unrelated participant's later Rest cannot close another participant's final attempt. The proof is captured outside the contribution lock and revalidated with the field version and exact receipt consumption.

`HorizonFailureService` observes the field version and local expressive provenance outside the contribution lock. Publication revalidates phase identity, Horizon, completed Rest, reason and version under the Substrate lock. A stale observation cannot close anything. Local failure markers stop at Committal/Horizon boundaries; they do not prove all possible descendants exhausted.

## Persistent proof and recovery

`horizon_tragedies` is unique per Horizon and Receipt. `horizon_failure_witnesses` is unique per terminal Phase. `receipt_consumptions` is authoritative for exercise. `horizon_exhaustions` is unique per Horizon, Tragedy, Receipt and opening Committal. Transactions and constraints back exact-once transitions.

Tragedy retains the terminal position and uses [normal backward-lineage recovery](recovery-basins.md), including intermediate ancestral Committals. It neither sets nor honors a direct opener override. After Exhaustion, recovery may continue into the immediate parent Horizon along the same path. It does not move other Participants directly. A parent's Tragedy and Exhaustion each require their own local proof; child failure alone cannot exhaust the parent.

Origin persists uniquely even if the Origin Horizon reaches Exhaustion. Its exhausted Committal children remain in the substrate and local context. No action deletes, prunes or forgets these records.

## Receipt evidence

Receipt JSON metadata contains reference IDs only: Tragedy, opening and terminal Proposition IDs, and exhausted Committal IDs from the same Horizon. It contains no statements or serialized descendants. The read service resolves statements, producing phase/register, owner and exhausted Horizon IDs, exhaustion state and terminal provenance. Receipt inspection resolves the full local statements by ID; ordinary participation history may use bounded excerpts. The flat local ID set is retained without an age cutoff and ordered newest creation first.

Receipts are visible throughout their Horizon regardless of who generated them or whether another Participant is present. Consumption changes availability, not existence. Explicit loading for Reinforcement is distinct from free visibility and from consumption.

## Realtime and diagnostics

Receipt creation/consumption and Horizon Tragedy/Exhaustion emit compact ID/state invalidations after commit. Workspace and graph fetch current state through existing caches and subscriptions. Participant movement events remain unchanged; rollback emits no proof events.

`commons:exhaustion-audit` checks persisted witness, consumption and transition consistency without modifying history. `commons:recovery-inspect` explains a local hypothetical recovery. Numeric reference recomputation remains a separate explicit diagnostic. Ordinary reads never scan the substrate to prove exhaustion.

See [migration and compatibility](horizon-proof-migration.md) before rollout.
