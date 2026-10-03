# Source grounding and developmental Attestation Cadence

**The missing vote is an ordinary, supported authored step. Supplying it fixes the
first positive-field boundary and permits its next write. It does not, in the
executed continuation, produce first Settlement → Emergence.**

The distinction between historical rhythm and effective source grounding is deliberate
in the implementation and canonical tests. The earlier specimen skipped available
support actions; there is no evidence here of a lifecycle that requires a vote but
never exposes an opportunity to author it. However, genuine grounding acts themselves
enter Cadence history. The previously measured +1 is not frozen while the lifecycle
continues, and new result propositions also have different field geometry.

## Exact missing source and ordinary opportunity

At the first mature `[5,5]` boundary (archived step 33), A2 is at Constrain. Its Gate
is the **Persist Write authored by A0 at step 3**, statement `Author 0 development 3`,
not the Distinguish proposition that received the first three historical votes.
Its preserved autopsy ID is `01m40f324pp4sxbeb55gqf3awx`.
The continuation reproduces it as `01m40h0fez4qjtwznrf7d4n5ek`.

A1 has just completed Distinguish by casting the third historical vote at step 32.
A1 is therefore at Persist, where the exact missing-vote proposition is an eligible
Write. The real schema offers:

```json
{
  "action": "attestation.create",
  "proposition_id": "01m40h0fez4qjtwznrf7d4n5ek",
  "direction": "support"
}
```

That action is executed through `ParticipationService::contribute`, not by a model
insert. The actor's Phase/Cycle, target eligibility, effective-vote replacement rules,
ledger recording, measurements and Phase progression all use ordinary services.
The actual new Attestation ID is `01m40h20sjbdjm438ydtzeqrhq`.

No particular named participant is semantically required to provide the vote.
Grounding aggregates effective source votes across participants. In this actual
state, **A1 at Persist is the immediately eligible existing helper**. A2 cannot
attest its current Gate from Constrain; its current Write targets are a different
Phase position. A0 is at Distinguish after ordinary recovery.

The **later Bias failure** has a different source: the reconciled Mediate result
created at archived step 69, ID `01m40f656b7yzvx9frzzfgae5s`, statement
`Author 1 development 66`. Its parent votes do not become its votes. At archived
step 70, A2 at Mediate is explicitly offered Attestation on that exact result, but
the preserved scheduler selects Instrument and advances A2 to Bias instead. Thus
this later missing vote also has an existing ordinary opportunity; it is not an
unreachable requirement. This later opportunity was inspected read-only, not run
as a second alternative continuation.

## Scope and deliberate semantics

| Concept | Actual scope |
|---|---|
| Developmental Cadence in this diagnostic | All historical Attestation acts on propositions in the selected causal lineage within the current Horizon, including superseded acts; represented Cycle entries between consecutive acts supply intervals |
| “Domain rhythm” | Shorthand for that lineage/Horizon observation, not one indiscriminate global stream and not a stored value automatically copied to every result |
| Effective source Attestation | A currently unsuperseded vote targeting the specific expressive source; no classifier/committer ownership requirement on who authored the vote |
| Expressive result history | Variant, Revise, Reclassify and Reconcile do not automatically inherit parental Attestations |
| Commit history | Explicit Commit source references preserve permitted contribution history transitively, without copying votes; generic ancestry is insufficient |
| Attestation action eligibility | The actor's current eligible Writes; ordinary shared Receipt or target evidence can permit non-expressive-phase Attestation; Distinguish/Disperse require their additional evidence conditions |
| Commit grounding | Effective source vote plus completed evidence Receipt, followed by separate structural, Closure and write-opportunity checks |

The canonical tests explicitly check new Reconcile results have zero votes while
parent votes still exist, Reclassify preserves evidence references but not judgments,
and an Order participant waits for another participant at Bias to attest its Write.
Another Chirality test counts both an original and replacement act in historical
Cadence while effective Orientation follows the replacement. These contracts support
historical rhythm ≠ current-source grounding.

**This does not canonise the developmental CV rule.** The production calculator at
the pinned revision still uses its existing act/entry similarity. The continuation
reuses the prior diagnostic candidate, unchanged. The full source comparison,
function names, pinned test links, exact source IDs and expected Phase path are in
[SCOPES.md](SCOPES.md).

The expected cooperative path is: a peer attests the producing Phase's Write → the
waiting next-Phase actor can classify that grounded source → new result needs its
own support where required → a peer at Mediate attests the reconciled result → Bias
classifies → a peer at Bias attests the new Bias Write → Order rechecks and Commits.
This describes supported actions; it does not guarantee their measured field signs.

## Executed continuation: first vote and immediate progression

The original 33-action prefix (steps 0–32) reproduces exactly. The continuation forks
before archived step 33. Continuation step numbers below restart at 0.

| Same Persist source | Before genuine vote | After A1's genuine vote |
|---|---:|---:|
| Intervals | `[5,5]` | `[5,5,0]` |
| Usable intervals | 2 | 3 |
| Mean | 5 | 3.3333333 |
| Population SD | 0 | 2.3570226 |
| CV | 0 | 0.7071068 |
| Orientation | +0.5 | +0.5 |
| Developmental Cadence | +1 | +0.1715729 |
| Cadence maturity | Mature | Mature |
| Combined attenuated Chirality | +0.6479592 | +0.3436391 |
| Correspondence | −0.3153158 | −0.3153158 |
| Closure | 0 | 0 |
| Tension | **+0.1108812** | **+0.0094410** |
| Charge | 0.8891188 | 0.9905590 |
| Effective source Attestation | Absent | Present |
| Completed evidence Receipt | Present | Present |
| Waiting A2's available action | Rest only | **Reclassify** |
| A1's resulting Phase | Persist before acting | Constrain, with Reclassify available |
| Actual Domain | Settlement | Settlement |

No Cycle entry occurs between the preceding historical act and the new vote, so
zero is the correct interval. It is retained, not discarded or changed to elapsed time.

At continuation step **1**, A1 lawfully Reclassifies this now-grounded source.
The action succeeds and A1 advances to Disperse. Thus the experiment directly
confirms that the original missing vote was repairable through existing participation.
The field being positive is not itself the permission for Constrain; source grounding
and the other production predicates provide that permission.

The newly created Constrain result has **different geometry** even before its next
vote: Correspondence **−0.3700546**, Chirality **+0.3314223**, Closure 0, Tension
**−0.0128774**. It has not inherited the source's Attestation. At continuation step 3,
A2 genuinely attests this new Write; its Tension becomes −0.0329640. Therefore the
positive field on the preceding source is already not a preserved positive field
on every subsequent result, independently of later Bias behavior.

## Subsequent blocker and transition outcome

The fixed cooperation policy continues only through real offered actions, without
reading field values to choose them. Ordinary recovery supplies a second Mediate
partner and an ordinary Reconcile result. A2 then genuinely attests that new result
at continuation step **19**. Its effective source-vote requirement becomes true.

At continuation step **22**, A0 reaches the resulting Bias boundary:

| Measurement or predicate | Observed |
|---|---|
| Intervals | `[5,5,0,0,0,0,0,2,0,0,0]` |
| CV | 1.767766952966369 |
| Orientation / direction | +0.5 / Positive |
| Developmental Cadence / direction | −0.2773958 / Negative |
| Cadence maturity | Mature |
| Combined Chirality | +0.1542422 |
| Correspondence | −0.5343177 |
| Closure | 0 |
| Tension | **−0.1266918** |
| Effective Attestation on current source | **Present** |
| Chirality component gate | **Inadmissible: `component_directions_disagree`** |
| Available actions | Rest only |

The missing-vote obstruction has been removed, but another existing rule now blocks
Reclassify: Positive Orientation disagrees with Negative Cadence. A positive combined
Chirality number does not replace this component-direction check.

At that first Bias observation, direct source evidence Receipt is absent, while an
authorized ordinary classification Receipt catalogue exists. These are different
scopes: Reclassify does not universally require a new direct source Instrument.
By step **34**, ordinary Instrument support has also made the current-source
`grounding()` result fully true (vote and completed receipt). Bias still refuses
Reclassify because the component directions disagree; Tension is −0.1403502.
This later observation removes any ambiguity about missing direct evidence as the
remaining explanation.

**No Order, ordinary Commit, or Settlement → Emergence transition occurs in this
continuation.** The fixed run records 80 additional actions and stops at its bound,
not at a proven global dead end. It performs no field tuning, search over schedules,
or rerun to improve the outcome. All three original participants perform genuine
actions: 73, 20 and 20 including the unchanged prefix; population remains exactly 3.

The decisive local findings arise before that bound. Additional support votes in
this schedule are lawful participation choices; they are **not all claimed to be
universally necessary or a minimal path**. Other lawful schedules remain untested.
This is evidence about the executed continuation, not an impossibility theorem.

## Earlier boundary and sequencing

Archived step 31 has the same missing-vote Persist source as step 33. Giving only
its head the eventual +1 Cadence produced the previously recorded Tension +0.0802688,
but supplied no effective source Attestation.

At step 31, A0 and A2 are at Constrain and A1 is still at Distinguish. A1's actual
step-32 vote targets the Distinguish parent: it completes the historical `[5,5]`
rhythm and advances A1 to Persist, where the separate source vote becomes available.
The executed continuation takes that next vote. It establishes a lawful adjacent
Phase sequence, not a rule that ancestral and source votes should be interchangeable.

There is **no demonstrated lifecycle sequencing contradiction** here. The archived
scheduler passed available support opportunities; production deliberately allows
cooperative grounding by another participant at the producing Phase. A pending
next-Phase actor can have its admissibility rechecked after that contribution.

What is newly demonstrated is an interaction between the two scopes: the genuine
vote that satisfies local grounding is also a historical act that changes Cadence.
Close succession of lawful grounding/support acts produces zero-entry intervals.
In this schedule, their accumulation reduces Cadence and ultimately changes its
direction. The supported ground-then-act path exists; preserving sufficiently
positive field and directional agreement along that path is a separate question.

## Interpretation of the four proposed alternatives

1. **Intended distinction:** yes, directly supported by production code and canonical
   tests separating historical acts, effective source votes and Commit-only history.
2. **Fixture/history gap:** yes, the preserved scheduler omits an already offered
   vote at relevant points. The continuation repairs the first gap through a genuine
   action and immediately unlocks Reclassify.
3. **Scope mismatch:** the scopes differ deliberately. No evidence establishes that
   they should be unified. Their interaction can nevertheless obstruct a continuation
   because adding required/supporting acts changes the measured rhythm.
4. **Missing lifecycle step:** an authored support step is missing from this history;
   its action is present in the lifecycle. The evidence does not show a missing API
   or permanently unreachable vote requirement.

The answer to “does it simply need the current proposition genuinely attested?” is
**yes for the immediate blocked write; not established for first Emergence**.
In the tested continuation, grounding succeeds but ensuing geometry and rhythm do
not retain the conditions needed for late progression.

## Validation, preservation and exact files

[Actions run 37112646953](https://github.com/miranovadev/commons/actions/runs/37112646953)
ran commit `aad4da2ef81b32e884cb440ba5738a6b236a2b78`, PHP 8.5.11 / PostgreSQL 18:

- Continuation: **1 test, 329 assertions passed**, including exact prefix reproduction
  and the genuine first vote's actor/action/resulting grounding.
- Canonical contract checks: **8 tests, 91 assertions passed**, in separate processes
  under their normal production bootstrap. These cover cooperative late grounding,
  evidence isolation, four non-Commit history resets, historical replacement acts,
  and independent non-Origin vote/Receipt grounding.
- Total: **9 tests, 420 assertions passed**.

Canonical tests sometimes construct fixtures or control Closure to isolate their
contract; they are not presented as natural field witnesses. The continuation uses
none of those helpers. It inherits only the pre-existing developmental candidate
binding and the repository's normal deterministic external hyperlink verifier.
All actual new votes, receipts, topology, Cycles and Phase advancement use services.

Added under `diagnostics/chirality-v3-grounding-continuation/`:
`GroundingContinuationTest.php`, `GroundingContinuation.php`, `bootstrap.php`,
`export.py`, `report.py`, `README.md`, `SCOPES.md`, `REPORT.md`, `TRACE.md`,
`run-manifest.json`, `runtime-results.tar.gz`.
Added workflow: `.github/workflows/grounding-continuation.yml`.

Reused unchanged: the preceding natural-rhythm candidate/bootstrap/archive and
boundary-autopsy inspector/bootstrap. Canonical test/application files were read,
not changed. No production semantics, coefficients, Closure, Correspondence,
attenuation, grounding or admissibility were altered. No PR or merge was made.

[TRACE.md](TRACE.md) separates every before-action field/lifecycle observation from
same-target before/after Attestation values. The archive contains the full runtime
JSON with schemas, exact IDs, timestamps, represented occurrence order and all
persisted acts/Cycles. Top-level `actions` is the 33-action prefix;
`continuation.actions` is the separately numbered 80-action continuation.
