# Stage 3 test-contract modernisation

This supersedes the requirement in the previous fixture reports to preserve obsolete test identities, assertion counts and arbitrary committed graph shapes. Claire authorised a consolidated modernisation after the `82408fc` reporting checkpoint (tested code `a6a809f`: Core 902/241; Integration 21/12). Production semantics remain unchanged.

## Coverage boundary

`component-contract` identifies authored database/read-model inputs and explicit numerical isolation. These tests exercise selectors, projections, visibility, evidence lookup, polarity, ranking, option composition and rejection/atomicity under stated inputs. **They do not establish that an operational history can produce their authored snapshots, Domain changes or committed depths.** No component pass is counted as recovered end-to-end coverage.

`IsolatesClosureOutput` isolates only prospective Closure in the listed component families. It supplies an explicitly labelled neutral numerical result, with no historical population or persisted expressive Closure state. Constitutional initialization and its frozen result remain real. It issues no per-head read queries. Both this double and a nested legacy numerical double reject canonical preservation. New boundary tests verify the double cannot create a successful authorised Commit.

`ParticipantContextFixture::projectedCommittal` explicitly constructs consumer input without calling preservation. It requires the labelled component boundary. This is deliberately distinct from invoking Commit, and supplies no frozen authorised Closure history. The underlying snapshot/edge rows are authored test inputs, not claimed production provenance.

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
| HomologousMediation's single Integration-labelled authored graph scenario | Same assertions run in Core with the component group | Removed from Integration classification, not skipped or omitted from execution. Foreign-Horizon creation is not proven by this scenario. |

The old source and results remain in git history and the previous per-test inventories. Exact added, removed, reclassified and retained identities will accompany complete validation. A removed invalid scenario is never counted as a passing assertion.

## Genuine lifecycle coverage retained

The unmocked 89-action Commit source scenario and its separately named descendant/search scenario remain unchanged. They verify actual offered operations, evidence authority, canonical source preservation, fresh descendant state, selected search distances and exclusions. The approved C1 and prospective Closure suites retain their own real observation/service contracts; the calculator's repeated-Domain populations are component arithmetic, not fabricated end-to-end histories.

The four documented Integration directional-disagreement Commit blocks and the historical ParticipationLocalEpoch transcript are unchanged. No additional multi-Commit diagnostic or semantic redesign is undertaken. Complete validation must report their actual results.

## Review limits

The modern suite deliberately stops claiming lifecycle coverage for arbitrary multi-Commit branches, forced later-Horizon transitions and the removed Tragedy combinations. These are coverage limits, not unresolved mathematical decisions. New assertion failures must be reported individually; a labelled component fixture is not permission to change expected values to obtain green results.

Validation results pending the consolidated run. No sign-off is claimed here.
