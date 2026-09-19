# Stage 4: backward lineage and true attenuation

Stage 3 was approved at `68ecffa361bb2068a9a0cf1eb8e3cc2d00f3eeb4`.
Stage 4 replaces recovery only. The successful-route Bias revisit remains an
ordinary red regression; lifecycle, Tragedy proof rules, visibility and cleanup
remain later checkpoints.

## Source of truth

`BackwardRecoveryLineage` walks raw parent edges, retaining revisions and
historical intermediate nodes. It uses durable participant Phase arrivals to
resolve shared multi-input results, with a backward cursor across Cycle sequence
and Phase kind. Consecutive Phases identify a traversed result's actual Gate;
ordinary Order rollover is also an arrival, whereas Rest entry is not. At
inherited history without a personal arrival, creating provenance supplies the
parent and its earlier participant context. No new routing table or migration
is required. Ambiguous multi-input provenance and cycles fail closed.

Read-only investigation of the supplied export found 990 recorded multi-input
arrivals, including 333 through a Gate different from the result creator's Gate.
The new resolver also walked all 792 propositions and all 32 positioned
participants in a typed, read-only copy of the export without an error; the
longest path was 20 edges. This validates lineage derivation, not a replay of old
numeric Potential or a repair of earlier incorrect relocations.

`ProjectedRecoveryService` reads maintained local topology and measurements. It
intersects active Committal entries with the exact walked lineage before scoring.
It does not read child frontiers to discover candidates. The reference evaluator
loads its raw graph and measurements independently and shares the explicit path
contract. Existing numeric projection/frontier maintenance remains for initial
entry, availability and graph consumers; its broader removal is not Stage 4.

Each candidate's distance is its actual backward edge count from the terminal
position. Potential and Charge retain `1 / (1 + distance)^2` attenuation. Both
nearer and farther ancestors can win; a successful Commit no longer shadows
its stronger ancestor during Rest recovery. Current-Horizon scope holds until
the existing exhaustion proof permits retreat. Crossing continues along the
same path, retaining intermediate Committals and the original distance origin.

Tragedy publication no longer moves the participant or writes a
`recovery_proposition_id` override. Cycle entry ignores legacy override values.
The completed Rest's true terminal position therefore reaches the normal
resolver. Receipt creation/consumption, exhaustion predicates and atomic proof
publication are unchanged. Historical overrides and earlier relocated positions
are not rewritten; this change cannot reconstruct a lost historical starting
position merely from the current participant pointer.

## Superseded contracts

These are explicit contract replacements, not weakened assertions. The previous
expectations admitted candidates or distances forbidden by the now-authoritative
lineage invariant. Test methods were renamed to describe the replacement.

| Existing coverage | Superseded assertion | Replacement |
| --- | --- | --- |
| `CausalRecoveryTest`: reconciliation diamond | Shortest distance over every parent | Recorded arrival branch; the unused shortcut cannot reduce distance |
| Connected descendant/sibling/disconnected candidates | Connected descendant wins | Exact candidate set contains only the ancestral Committal |
| Earlier-Domain entry and real Rest | Enter a viable downstream Horizon | Remain in the current Horizon on the ancestral path; other participants remain unchanged |
| Local non-boundary ranking | Select a descendant basin's local Commit | Compare ancestral non-boundary Commits inside the current basin |
| Same-Domain Commit shadowing | New Commit suppresses stronger ancestor | Both compete at true backward distances, including legacy projection edge shape |
| Sibling Potential/recency and deep/shallow frontiers | Rank sibling or descendant Horizons | Neither branch enters the candidate set |
| Exhausted current Domain | Discover a sibling through its parent | Continue backward into the parent; preserve exact distance and searched-Horizon order |
| Cross-Domain expressive evidence | Recover forward, then check numeric scope | Start inside that basin and retain the same cross-Domain numeric exclusion |
| Retained boundary and read-only diagnostics | Prefer a downstream Commit | Retain the distance-zero boundary or actual ancestor; diagnostics remain read-only |
| `RecoveryBasinResolverTest`: downstream/sibling frontiers | Descendants shadow parents; siblings are peers | Search only the current Horizon, then permitted ancestors on the walked lineage |
| `LocalParticipantNavigationTest`: connected alternatives | Relative-ranking diagnostic also defines recovery | Diagnostic retains its old behavior; recovery excludes connected descendants |
| Ancestor ranking fixture | Several unrelated parents compete | Sequential ancestors compete at their actual distances |
| Equal-distance parent/ID ties | Unrecorded multi-input parents are interchangeable | Ambiguous arrival provenance fails closed; a single path cannot contain two nodes at one distance |
| Repeated Rest fixture | Fabricated target-to-ancestor cycle | A distinct historical continuation preserves the open-region assertion without a causal cycle |
| `HorizonTragedyTest`: first Tragedy | Force Origin via override | Normal recovery selects the intermediate ancestral Commit; no override is recorded |

Missing provenance, Horizon cycles, empty open basins, unrelated-Horizon rejection,
historical ownership, other-participant immutability and numeric projection
maintenance coverage remain. No test is skipped or reclassified for this change.

## Evidence and validation

Before implementation, four focused regressions failed on the intended behavior:
unused-parent shortcut distance, descendant selection, observer/creator arrival
disagreement, and the legacy Tragedy override. All four pass after the correction.
Additional regressions cover genuine proof-driven Horizon crossing with an
intermediate Commit, raw revision distance, later-revisit isolation, malformed
cycles and a boundary absent from the actual lineage.

`ParticipationRecoveryTest` creates its complete substrate and route through HTTP.
After one participant reaches Commit, a second revisits that route and Rests at
Bias. The test independently checks the consecutive Phase edges, exact distance,
ancestral-only candidate set, recovery response, stable historical ownership and
the first participant's unchanged position. The untraversed existing Commit is
excluded. The fixture-write guard remains enabled. Controlled routing fixtures
separately cover numeric near/far comparisons and multi-input arrival geometry.

The first local Core validation completed in 203.06 seconds: 597 passed, one
expected Bias failure and three existing PostgreSQL-only skips. Subsequent
focused validation covers the additional provenance guards. The recovery query
gate passes unchanged at 32 participation-state queries with both zero and 1,000
unrelated historical propositions. It still forbids graph-table scans and writes
on that read path.

Final PostgreSQL Core, Full Integration, Query and Load results, exact commit,
job runtimes and artifact links are recorded in the Stage 4 pull request. The
approved 30-minute Core/Full Integration ceilings, workflow commands, frontend
coverage and suite exclusions are unchanged.

The only expected red test is
`ParticipationLifecycleRegressionTest::test_successful_route_revisit_can_advance_through_bias_to_its_existing_commit`:
the successful Bias route lacks `proposition.traverse` before Rest. Stage 4 changes
recovery after Rest, not that earlier eligibility failure. Stop at this checkpoint
before Stage 5, merge, deployment or production mutation.
