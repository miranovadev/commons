# Traversing personally explored structure

The [current Exploration rules](participant-relative-exploration.md) separate personal judgment from meaningful movement. The implementation and measurements below describe the earlier experiment.

An explored badge records the Participant’s completion of the existing route. The same Write can be a Traverse destination on the way to new generation. The action schema continues to suppress repeat Attestation and Revision on explored Writes.

## Reproduced on merged main

At `dd47ed2`, the HTTP exploration fixture created P1 and P2 through fresh Variants, P3 through Constrain Reclassification and P4 through an acquired Disperse Variant. The Participant judged the route, Rested at Mediate and recovered to Origin with one personal Exploration Event. The governing Crossover Margin was positive. All existing continuations through P4 were personally explored.

Before testing Traverse, a read-only Disperse Phase at P3 in the recovered Cycle returned acquisition status `required`. Instrumentation at P3 was eligible. Immediate Variant submission was unavailable. The recovered action schema and equivalent projections of subsequent arrivals gave these results.

| Current Gate and Phase | Next Write | Personally explored | Base Traverse | Offered on main | Offered after correction |
| --- | --- | --- | --- | --- | --- |
| Origin, Distinguish | P1 | Yes | Yes | No | Yes |
| P1, Persist | P2 | Yes | Yes | No | Yes |
| P2, Constrain | P3 | Yes | Yes | No | Yes |
| P3, Disperse | P4 | Yes | Yes | No | No |

On main, `PropositionOptionService::traversalOptionsFor()` removed every explored destination. P2 had already been reclassified. From P2 the only forward action in this fixture is Traverse to P3. P4 has no eligible Mediate counterpart.

The regression takes all three restored hops through the participation API. It compares the human and autonomous schemas, then Instruments at P3 and creates a second Disperse Variant there. Both sibling edges exist. The hops leave the original Attestations, Exploration Event and acquisition count intact. The November 1 cook motivated this fixture. It does not establish how any particular Mediate encounter in that cook would have resolved.

## How a route qualifies

The selector starts with the current Phase’s base Traverse options. Personally unexplored destinations retain their ordinary eligibility. For an explored destination it projects the next Phase at that exact Gate in the same Cycle and Domain. It asks whether an acquisition could begin there or whether the relevant Reconcile, Reclassify or Commit selector has a concrete operand. If necessary it follows the next Phase’s base Traverse options. It stops when it reaches generation, a Phase without Traverse, or a Committal boundary.

Each hop needs a lawful local Write at the right producing position, with the usual personal Attestation or existing Commit traversal authority. Comparison operands can inform generation at a reachable Gate. A foreign operand does not supply a movement edge. A projected Mediate arrival carries the Disperse Gate on the proposed route as its anchor.

All state belongs to the current request. Sibling arrivals share the generation-history query and batched comparison operands. A new request rechecks eligibility. A regional Tragedy or hard Supersession can remove the fresh opportunity on which a projected route depended. Independently lawful generation and unfinished personal exploration can still justify movement. An admitted acquisition retains its ordinary Phase-scoped resolution rules.

The branching benchmark grows from two to eight explored Constrain Writes, each with its own personally judged Disperse head and Mediate Rest. With the participation graph hydrated, SQLite selection on merged main took 12 cold queries at both sizes and offered neither route set. The corrected selector took 20 at both sizes and offered every eligible Gate. Repeating the selection in the same context issued zero queries at either revision. The benchmark also checks for writes and locks during selection.
