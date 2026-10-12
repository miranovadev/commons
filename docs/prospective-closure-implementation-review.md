# Stage 3 implementation readiness review

PR #255 continues on its original branch. Main at
`e7c357551f6e9916a8e84c8c61c309069b280ca6`, including PR #256's
[implementation-readiness protocol](implementation-readiness-protocol.md), is
incorporated by merge commit `e4943b14294842d9c46ff4225b033943e34b3d39`.
The [ten Core Invariants](core-invariants.md) remain the highest authority.

The approved **Stage 3: Resolve Implementation Boundaries and Continue Prospective
Closure** instruction resolves both earlier broad blockers. Origin is a
constitutional exception, and historical Phase aggregation is cumulative across
selected occurrences of each Domain. Neither decision is being reopened.

The [target specification](prospective-closure-target.md) records these rules.
Claire's final clarification resolves the final numerical detail: all
observed Phase intervals, including Origin's, have equal weight. No semantic
ambiguity remains in this register; production implementation can proceed.

## Ambiguity register

| Boundary | Question | Resolution | Authority | Status | Verification |
| --- | --- | --- | --- | --- | --- |
| Constitutional Origin | Must ordinary maturity derive its new outer components? | No. C=+1; Closure, Extent, Rhythm, Depth and Cadence=-1; immutable constitutional exception, no empirical Orientation population required | Boundary-resolution instruction §2; protocol §5 | Documented | Assert constants at creation and after later Cycles/Attestations; no invented primitive populations |
| Origin Phase intervals | Are the first six zeros unavailable? | No. All seven intervals are available constitutional values, distinct from ordinary null first births | Instruction §2 | Documented | Zero/zero compares as +1 without synthetic birth or ledger rows |
| Repeated Domains | Latest Horizon or cumulative history? | Selected E0+E1+E3 versus S1+S2; group raw observations by creating Domain/Phase, not Horizon means | Instruction §3 | Documented | Worked table: unequal S1/S2 counts give Phase-2 mean 4, not 3.5 |
| Mixed constitutional/empirical population | What multiplicity does each Origin interval have in a mixed Phase sum and denominator? | Every observed interval counts once, including each Origin interval; no separate weight, denominator or maturity rule | Claire's final clarification | Documented | Origin 0 plus [2,4] has mean 2; worked repeated-Domain HPP=5/7 |
| Historical Orientation | Product of leans, or mean resolved displacement? | Local O=(current effective lean−previous complementary lineage lean)/2; HO(D)=mean over selected active causal A(D), including genuine zero | Instruction §4 approves prior clarification | Documented | [+0.5,0,-0.5] has mean 0/count 3; test effective replacements and historical boundaries |
| Maturity and weights | Does missing Cadence transfer weight to Orientation? | No. Two genuine committed intervals per compared Domain required; immature contribution 0 remains unavailable | Instruction §5 | Documented | First Settlement versus mature E=[1,7], S=[10,12] example |
| Hysteresis | What about zero and agreeing negative signs? | Independent Extent/Rhythm retention at zero; ++ and -- may satisfy coherence subject to other requirements | Instruction §5 | Documented | Mixed retained directions remain incoherent even at numeric zero |
| Authorised events | Who is updated, and when? | Complete-context birth and genuine governing Cycle entry only; all governed expressives including superseded, each with own history | Instruction §6; full-event diagnostic below | Documented | Atomic all-recipient update; Attestation/structural progression leave older Closure unchanged |
| Canonical preservation | Recalculate at Commit? | No. Under lock validate and preserve last authorised source values/directions; no extra head or attenuation | Instruction §6 | Documented | Intervening Cycle supersedes stale preview; snapshot matches validated current source |

## Final numerical boundary resolved

Claire explicitly confirmed that all Phase Profile intervals are
equally weighted observations, including Origin's. Origin's Phase-2 zero and
subsequent [2,4] therefore have sum 6, count 3 and mean 2. Constitutional versus
empirical source metadata does not affect this arithmetic. There is no extra
maturity requirement. Origin's own frozen Closure bootstrap remains a separate
constitutional exception: Closure=Extent=Rhythm=-1.

The target's repeated-Domain calculation is now unconditional. The earlier
evidence limitations below explain why this explicit clarification was needed;
they are no longer blockers and must not reintroduce a special weighting rule.

## Diagnostic evidence inspected

The following preserved files were retrieved and read. Filenames identify
supporting artifacts, not newly added repository files. This is not a claim of
exhaustive access to historical discussions.

| Artifact | Scenario/result inspected | Evidence and limitation |
| --- | --- | --- |
| `commons-lineage-historical-extent-observation-manifest.csv` | 546 observation rows, 16 candidate Commit IDs; B1/B2 controls; types expressive_birth, prospective_phase7, preserved_committed_phase7 | Selected empirical/boundary samples; no constitutional Origin rows defining mixed multiplicity |
| `commons-lineage-historical-extent-both-baselines.csv` | 128 candidate/control/extra-Cycle rows with Phase counts and means | First-Settlement versus Origin, no later empirical Emergence mixed with Origin |
| `phase_profile_bootstrap_comparison_v3.py` | Origin vector [0,0,0,0,0,0,1]; profile() counts selected empirical intervals and compares their mean directly with origin[phase−1] | Lineage-correct C1 comparison, but no mixed Origin/empirical denominator |
| `commons-phase-profile-bootstrap-four-candidates.csv` | C1 Offsets candidate 1: counts [0,2,2,2,1,1,1], means [null,4.5,1,0,2,6,1], contributions [0,−1,−1,+1,−1,−1,+1], HPP=−2/7 | Concrete C1 zero/unavailable arithmetic, only standalone Origin reference |
| `commons-c1-step1-bootstrap-correspondence-extent.csv` | origin_reference="constitutional axiom, not empirical interval sample"; null_first_interval_is_sample=False | Source distinction, not mixed constitutional denominator |
| `historical-phase-profile-v1-running-domain-history.csv` and `historical-phase-profile-v1-horizon-profiles.csv` | Older prospective-closure-primes Horizon 3 Emergence: empirical Phase-1 mean 37/count 4 remains 37/4 cumulatively; Phase-7 mean 0/count 2 becomes mean 1/3/count 3 with Origin | Older Horizon-pooled calculation assigns Origin P1–6 count 0, P7 count 1. Not approved C1 selected-lineage aggregation; cannot override explicit constitutional zeros |
| `historical-phase-profile-v1-observations.csv` | Schema and first 99 data rows, including Origin's recorded Order interval | Partial source-row inspection only; the two summary tables above were read fully. Ordinary event rows do not determine constitutional multiplicity |
| `c1_full_event_hysteresis.py` | Birth and governing Cycle triggers, independent directions, frozen historical observations | Supports last-trigger preservation; imported replay helper was not recovered, so no reproducible full replay claimed |
| `commons_step3_field_replay.py` | C1 field replay and canonical boundaries | Supporting lifecycle evidence, not approval of attenuation B |
| `commons-hysteresis-orientation-corrected-audit.csv` and `commons-hysteresis-closure-directions-v2.csv` | Historical versus Commit-time observations and earlier 13 opposed / 3 agreeing result | Observation-boundary evidence, not universal candidate expectations |

The earlier `chirality-v3-historical-extent-v1.csv` and null-bootstrap variant
were also inspected; neither establishes the mixed-population rule. Historical
Orientation witnesses and Rhythm candidate/summary files are diagnostic controls,
not permission to restore shortcut arithmetic. The reported 145/145 local
Orientation reconstruction is prior evidence, not a production test run here.

The old Horizon-pooled table cannot resolve the gap by excluding constitutional
zeros: that contradicts the explicit resolution. The C1 script's standalone
reference cannot prove a mixed count it never computes. B1, B2, C2 and
attenuation B remain excluded.

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

The table records the pre-implementation mapping. The draft now implements these
boundaries through `ProspectiveClosureCalculator`, `ProspectiveClosureHistory`
and `ProspectiveClosureService`, with durable `prospective_closure_states` rows.
Origin has a frozen constitutional row. Ordinary births initialise after their
ledger context and real edges exist. New governing Cycle entries batch-load
selected histories and atomically update all governed expressive rows. Stored
reads never repair missing state. PostgreSQL and SQLite guards prevent updates
to frozen canonical rows. No records are backfilled or deleted.

Commit copies the source observation exactly, including the historical inputs,
trigger identity and independently retained directions. Snapshot validation
compares the complete authorised observation, rather than just its scalar.
Projection, inspection, Order coherence and UI labels consume Extent/Rhythm.
The underlying Correspondence and Chirality implementations remain unchanged.

The existing `ClosureBirthService` is still used by the separately specified
Rest crossover diagnostic; it does not supply production Closure measurements.
`CrossoverMarginService` and movement/recovery rules have not been changed. The
old Cardinal/Cadence conditional bound in `rest-crossover-experiment.md` is not
a validated bound on the new four-primitive Closure. This dependency remains a
review concern; no replacement routing formula is inferred in this PR.

## Acceptance and validation plan

As production wiring is implemented, run focused
tests on both PostgreSQL and SQLite:

- Exact Origin constants, all seven constitutional intervals, ordinary null
  first intervals, immutable snapshots and unchanged Attestation polarity.
- First Settlement, unequal repeated-Domain sample counts, alternating histories
  and cumulative selected ancestry without siblings or neighbouring Regions.
- Revision, acting-Gate Reconcile and foreign-source Bias, preserving temporal
  selection and independent governing-basin clocks.
- Effective Attestation replacement, genuine zero Orientation and frozen
  historical populations; the prior 145/145 diagnostic is not a substitute.
- Immature/mature Cadence and independent Extent/Rhythm directions, including
  coherent -- and incoherent mixed signs.
- Atomic Cycle updates for active and superseded expressives, each using its own
  history; Attestation independence and no older structural-progression trigger.
- No Commit-time measurement or attenuation, exact source preservation,
  stale-state protection and fresh descendants without rewriting frozen history.
- Batched versus individual calculations with identical populations/values;
  query counts, Cycle fan-out and runtimes on both databases.

Run focused C1/observable/ledger regressions, Core and complete Integration to
final summaries. Preserve Stage 2 PostgreSQL isolation, sharding and complete
coverage accounting: preferred under 15 minutes, maximum 20 minutes. Classify
unchanged baseline failures, lawful trajectories, genuine defects and
infrastructure failures separately. Do not alter authored populations or historic
numeric assertions merely to regain previous transitions.

## Readiness decision and current validation status

Protocol §§1–3: explicit decisions supersede experimental tables; manifests,
scripts and repeated-Domain tables were retrieved before identifying the gap.
§§4–6: the register distinguishes constitutional, empirical, unavailable and
zero-valued observations and distinct lineage scopes. §§7–8: the target states
atomic events, hysteresis, canonical preservation, stale-state handling and
worked cases, with implementation mapping and verification plan here.
§§9–11: the final explicit clarification closes the necessary numerical boundary.
The numerical specification is resolved and production wiring is implemented.
This does not claim full production correctness or successful regression validation.

The [fixture repair follow-up](prospective-closure-fixture-repair.md) and
[original validation checkpoint](prospective-closure-validation.md) record exact tested
heads, PostgreSQL/SQLite results, complete Core and Integration execution,
baseline comparison, performance and a durable per-test inventory. Focused C1
and prospective Closure contracts pass on both databases. Complete suite
execution is within existing CI budgets, but many older direct-model fixtures
still lack authorised birth or frozen canonical history. Four historical
Integration trajectories now correctly stop at incoherent Extent/Rhythm.
Those failures remain visible and do not constitute successful lifecycle proofs.

Remaining review boundaries are lawful fixture conversion, replacement authored
scenarios and the separately scoped Rest-bound interpretation described above.
No production fallback or fabricated history resolves those gaps. Continue in
PR #255; Claire performs final review. Do not merge.
