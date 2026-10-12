# Stage 3 test-contract modernisation

This supersedes the requirement in the previous fixture reports to preserve obsolete test identities, assertion counts and arbitrary committed graph shapes. Claire authorised a consolidated modernisation after the `82408fc` reporting checkpoint (tested code `a6a809f`: Core 902/241; Integration 21/12). Production semantics remain unchanged.

## Coverage boundary

`component-contract` identifies authored database/read-model inputs and explicit numerical isolation. These tests exercise selectors, projections, visibility, evidence lookup, polarity, ranking, option composition and rejection/atomicity under stated inputs. **They do not establish that an operational history can produce their authored snapshots, Domain changes or committed depths.** No component pass is counted as recovered end-to-end coverage.

`IsolatesClosureOutput` isolates only prospective Closure in the listed component families. It supplies an explicitly labelled neutral numerical result, with no historical population or persisted expressive Closure state. Constitutional initialization and its frozen result remain real. It issues no per-head read queries. Both this double and a nested legacy numerical double reject canonical preservation. New boundary tests verify the double cannot create a successful authorised Commit.

`ParticipantContextFixture::projectedCommittal` explicitly constructs consumer input without calling preservation. It requires the labelled component boundary. This is deliberately distinct from invoking Commit, and supplies no frozen authorised Closure history. The underlying snapshot/edge rows are authored test inputs, not claimed production provenance. Coordinate-only snapshots retain authored creating-Domain memberships for lineage-query inputs, but contain no fabricated direct Commit edge. They are incomplete historical inputs, explicitly unsuitable for empirical Correspondence geometry.

The 34 isolated families are enumerated in `evidence/prospective-closure-component-families.json`. Existing assertions remain except for the explicit consolidations below. HomologousOrder now authors Extent/Rhythm snapshot directions instead of obsolete depth/cadence directions; its zero-retention and coherence assertions remain. Snapshot assembly has explicit isolated interval/reference metadata; those inputs are not claimed observations. Database constraints, ledger/C1 observation code, causal selectors, Chirality, Receipt authority and operation handlers remain production implementations. Passing a component test does not validate Closure-dependent action eligibility or a later Domain lifecycle.

## Consolidations and removals

| Prior contract | Modern coverage | Deliberate limit |
| --- | --- | --- |
| ClosureBirth's 14 Cardinal/lateral/birth-only cases | Two real expressive-birth tests; existing ProspectiveClosureCalculatorTest and ProspectiveClosureStateTest | Old Cardinal centroids and birth-only immutability no longer define production Closure. Historical migration coverage is not represented as prospective lifecycle coverage. |
| CommittalClosure's eight direct-model cases | Four real bootstrap/read-only/missing-state/immutability cases; calculator repeated-Domain cases; batch-history test; genuine Commit and descendant Integration | No manufactured twelve-Commit history or forced Domain alternation. The old archived cadence-backfill scenario is removed, not reported as passing. |
| HomologousPosition direct Committal edge-count assertion | Equal-depth/shared-Origin/coordinate component assertions retained | The shared coordinate builder, later-Horizon projection and Mediate coordinate builder no longer fabricate direct Committal-to-Committal edges. Five such edges are no longer asserted as a lawful history. Genuine Integration separately proves the first actual committed ancestor. |
| CommitParticipationState transitive Committal-to-Committal assertion | Effective evidence and reset component assertions retained; actual Commit inheritance and descendant reset in Integration | No second authorised Commit is claimed. |
| HomologousOrder local Commit success under a numerical double | Local Commit option composition against an opposite population | No canonical success is inferred from controlled eligibility. |
| Three direct-model canonical Chirality/Correspondence/attenuation candidates | Exact source scalar/component/direction preservation in CommitSourceHistoryLifecycleTest and ProspectiveClosureStateTest; descendant immutability in CommitDescendantHistoryLifecycleTest | These are consolidations, not recovered original identities. |
| Nine invalid Tragedy action datasets | Five already-lawful action-exhaustion datasets retained, plus independent existing exhaustion/authority tests | Late-Phase ownership/Revise/Mediate/Order combinations are not newly established end-to-end. Their tier mutation and late-added provenance builders are removed. |
| HomologousMediation's single Integration-labelled authored graph scenario | Consolidated into ordinary Reconcile result comparison and the independent Bias coherence matrix | The intermediate component execution exposes a coherent Bias, contrary to the old assumed outcome. No Mediate-to-inadmissible-Bias end-to-end claim remains; no population is manipulated to force it. |

The old source and results remain in git history and the previous per-test inventories. Exact added, removed, reclassified and retained identities will accompany complete validation. A removed invalid scenario is never counted as a passing assertion.

Additional contract corrections preserve behaviour rather than historical scalar accidents:

- Reinforcement captures authorised Closure **after** each genuine Cycle entry and **before** Receipt loading, then compares the entire state across the load. The former comparison crossed two distinct event boundaries.
- Raw attenuation records the resolved Chirality before disposable projection writes and compares it afterwards, alongside unchanged raw local values. It no longer substitutes the historical constant `-0.5` for the no-reuse invariant (the intermediate run observed `-0.1`).
- Bias reclassification compares the resulting scalar, Orientation and Cadence with ordinary independent calculation; it no longer requires that a particular authored graph produce a Negative direction. The intermediate result was Positive, and no numeric value was changed to force a direction.

The API graph serializer now receives an explicit Closure-state boundary value (0.375), alongside its controlled Correspondence/Chirality. This verifies authorised-state serialization rather than the retired fallback from an ungoverned expressive to Origin Closure. The concurrent-Horizon test now verifies an open Cycle retains its owner across a supplied boundary projection and an actual subsequent expressive contribution; its two forced Commit successes are removed. Simultaneous late Commit across real Domain transitions remains outside demonstrated end-to-end coverage.

## Genuine lifecycle coverage retained

The unmocked 89-action Commit source scenario and its separately named descendant/search scenario retain their operation policy and transcript. The source scenario now also checks every actually executed Reconcile against ordinary calculated Correspondence and both components, checks its actual parents, and requires at least one such operation. They verify actual offered operations, evidence authority, canonical source preservation, fresh descendant state, selected search distances and exclusions. The approved C1 and prospective Closure suites retain their own real observation/service contracts; the calculator's repeated-Domain populations are component arithmetic, not fabricated end-to-end histories.

The four documented Integration directional-disagreement Commit blocks and the historical ParticipationLocalEpoch transcript are unchanged. No additional multi-Commit diagnostic or semantic redesign is undertaken. Complete validation must report their actual results.

## Review limits

The modern suite deliberately stops claiming lifecycle coverage for arbitrary multi-Commit branches, forced later-Horizon transitions and the removed Tragedy combinations. These are coverage limits, not unresolved mathematical decisions. New assertion failures must be reported individually; a labelled component fixture is not permission to change expected values to obtain green results.

Receipt-neutral Chirality and grounding checks now use the real governed-expression fixture instead of developed coordinate-only histories. Two Homeostatic hydration tests explicitly isolate numerical outputs; they measure population/query composition, not full numerical geometry cost. HomologousBias, HomologousMediation, HomologousOrder, AttestationPolarity, ReceiptContext and the open-Cycle boundary-projection case explicitly isolate numerical Correspondence as well as Closure. Their selectors, Chirality, ownership, evidence and atomicity remain exercised. The standalone foreign Cardinal-placement case and the two cross-region geometry assertion blocks are removed: source-less coordinate snapshots cannot prove that geometry. C1 has independent real observation/geometry contracts, but this exact multi-Horizon case remains a coverage gap. The intermediate attempt to omit memberships was rejected by lineage provenance checks and reverted; no production fallback was added.

## Focused validation

Tested code: `524e61dacc7cb52c2a80554303c8c881c7102b54`. [PostgreSQL/SQLite matrix](https://github.com/miranovadev/commons/actions/runs/38311736609).

| Selection | PostgreSQL | SQLite |
| --- | --- | --- |
| C1 Phase Profile | 48 pass / 300 assertions / 60.80s | 48 pass / 300 assertions / 16.55s |
| Prospective Closure | 14 pass / 85 assertions / 13.56s | 14 pass / 85 assertions / 3.47s |
| Observable/ledger and component boundaries | 131 pass / 1,961 assertions / 152.19s | 131 pass / 1,961 assertions / 35.29s |
| Representative fixtures | 172 pass / 3 fail / 2,045 assertions / 240.49s | 170 pass / 3 fail / two existing PostgreSQL-only skips / 2,039 assertions / 73.89s |
| Genuine Commit, including actual Reconcile checks | 1 pass / 112 assertions / 70.07s | 1 pass / 112 assertions / 26.45s |
| Genuine descendant/search | 1 pass / 129 assertions / 95.08s | 1 pass / 129 assertions / 27.29s |

The same three ClosureHysteresis assertions remain in both fixture selections: read-only historical scalar expectation, negative-alignment Domain boundary expectation, and a source-polarity candidate correctly blocked by disagreeing Extent/Rhythm. These selections overlap complete-suite identities. Scoped Pint (32 files) passes on both databases. No skip was introduced; the two SQLite skips already require PostgreSQL-specific behaviour.

Complete-suite accounting and coverage comparison follow after all shards finish. No full regression sign-off is claimed from these focused passes.
