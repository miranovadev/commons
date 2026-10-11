# Stage 3 implementation review boundary

Base inspected: `85ddf01d2970a5c446b6225e28a15beb22c07795` (`main`, including
merged Phase Profile Stages 1–2). This draft records the October 11 approved
contract before production wiring. It does not implement prospective Closure or
claim Stage 3 validation.

The implementation instruction explicitly requires stopping when a measurement
rule cannot be derived from the approved specifications. Two remaining cases
need an explicit answer; the old Closure implementation cannot supply replacement
semantics by analogy.

## 1. Origin's new four-primitive state

What exact values and availability statuses should a newly created Origin store
for Depth, Historical Phase Profile, Committal Cadence and Historical Orientation?

The existing implementation gives Origin Depth/Cadence/Closure = -1, its
committed interval = 1, and local Chirality Orientation = 0. The Stage 3 contract
defines the four-way equal mean and the constitutional Phase Profile reference
`[0, 0, 0, 0, 0, 0, 1]`, but does not specify a Historical Phase Profile comparison
result or Historical Orientation population for Origin itself. A profile vector
does not determine its scalar comparison without a comparison reference.

The instruction to use established Closure bootstrap/reference-frame rules
resolves unestablished directions, but does not explicitly assign the two new
primitive values. Retaining old numeric Closure -1 would require additional
primitive assignments or a bootstrap scalar exception; treating the new slots
as unavailable zero would produce a different Origin Closure. Neither choice
has been implemented. Please also specify whether Origin retains its existing
constitutional Cadence -1 exception to ordinary comparative maturity.

## 2. Historical Phase Profile after both Domains develop

For a selected committed chain `Origin(E0) → S1 → E1 → S2`, followed by an
expressive in Emergence, which exact observations constitute the two seven-slot
profiles compared for Historical Phase Profile?

Please specify whether each profile aggregates all selected committed and
current developmental observations with the corresponding creating Domain, or
uses the immediately preceding completed complementary Domain boundary, and
how the constitutional Origin reference participates once empirical Emergence
history exists. In particular, the Origin vector must not silently become seven
empirical samples or acquire an invented sample weight.

The existing production `PhaseProfileService` follows one selected local temporal
chain and compares it with the profile ending at the current Horizon's opening
Committal. The new Historical Phase Profile explicitly adds selected committed
ancestry. Reusing its arithmetic and birth observations is defined; choosing the
general historical aggregation/reference rule is not resolved by that existing
local comparison method.

The inspected October 9 replay derives historical profile values against Origin
for the Offsets/Primes candidates. Those observations establish the tested
Settlement-versus-Origin case, but do not distinguish these later-history cases.
No new comparison rule has been inferred from those experimental trajectories.

## Approved Orientation clarification

The [target contract](prospective-closure-target.md) now explicitly records:

- Proposition-local signed displacement, not a product of leans.
- The arithmetic mean of independently resolved local Orientations in A(D).
- Active population selection by hard supersession within selected historical
  causal lineage, not shared Horizon or Region membership.
- Genuine zero contributions, separate availability and historical boundaries.
- Complete effective-Attestation semantics for diagnostic reconstruction.
- Reuse of event-born state, and preservation of the last authorised Closure
  observation instead of a fresh Commit-time evaluation.

The approved four-slot weighting, neutral immature Cadence slot, independent
Extent/Rhythm directions, atomic Cycle lifecycle and attenuation A are also
recorded. These clarifications do not introduce a replacement numeric rule for
the unresolved cases above.

## Implementation sites established by inspection

| Responsibility | Current implementation | Required Stage 3 change once the contract is complete |
| --- | --- | --- |
| Expressive initial state | `Proposition::created` calls `ClosureBirthService` before ledger birth and Supersessions | Initialise prospective state from the complete authorised birth context in the contribution transaction |
| Genuine Cycle trigger | `Cycle::save` records new entries under the Substrate lock and distinguishes completion | Update every governed expressive on entry only, with batched ancestry and immutable facts |
| Local Orientation | `LocalChiralityStateService` stores own-Attestation-driven state | Read its resolved values without changing that lifecycle |
| Phase observations | `PhaseProfileObservationService` stores immutable coordinates and selected temporal references | Reuse the facts and seven-slot comparison; keep Correspondence unchanged |
| Committed ancestry | `CommittalLineageService` follows creating-Cycle entry Committals | Preserve committed ownership, Depth and frozen interval semantics |
| Commit preview | `ClosureGateService` calculates a hypothetical new Committal birth | Read the selected expressive's authoritative prospective state |
| Canonical Commit | `CanonicalMeasurementService` preserves C/Ch; Closure is independently born | Preserve the validated prospective Closure and components with the existing canonical boundary |
| Snapshot validation | `CommitmentSnapshotService` validates old Cardinal/Cadence metadata | Validate Extent/Rhythm and exact authorised source state under the existing lock |
| Projection and UI | Closure readers use birth state and old component names | Read the new state and expose its true component names without reusing legacy fields |

These are implementation findings, not evidence that the changes already exist.
No production source, migration, fixture population, authority rule or substrate
data is changed by this draft.

## Diagnostic evidence inspected

The following preserved artifacts were read as supporting evidence:

- `c1_full_event_hysteresis.py`: initial source birth plus genuine governing
  Cycle events, frozen ancestral source observations, independent directions,
  and preservation of the last trigger rather than Commit-time recalculation.
- `commons_step3_field_replay.py`: C1 field replay with canonical boundaries
  and separate attenuation controls; it does not approve attenuation B.
- `commons-hysteresis-orientation-corrected-audit.csv` and
  `commons-hysteresis-closure-directions-v2.csv`: concrete differences between
  last-trigger and Commit-time Orientation and the earlier corrected 13/3 result.
- `commons-lineage-historical-extent-observation-manifest.csv`,
  `commons-lineage-historical-extent-both-baselines.csv`,
  `commons-lineage-historical-orientation-witnesses.csv`, and historical Rhythm
  candidate/summary files: selected populations and the earlier diagnostic
  controls. Earlier shortcut values and B1/B2 are not promoted to production.

The 145/145 reconstruction is a previously reported diagnostic result, not a
replay executed for this draft. The inspected full-event script distinguishes
B1, B2 and C1; their candidate outcomes are not interchangeable test expectations.

## Validation status

Only documentation and local-link/whitespace checks apply to this draft. No
Stage 3 PostgreSQL/SQLite, performance, Core or Integration result is claimed.
After the two decisions are supplied, implementation and all required validation
can continue in this same PR. Keep the Stage 2 Integration sharding, complete
coverage accounting and 20-minute maximum intact. Do not merge this draft.
