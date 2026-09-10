# Operational Closure path

`OperationalClosurePathTest` is the canonical late-phase lifecycle regression.
It uses normal `ParticipationService::enter` and `contribute` operations for all
Cycles, Phases, Propositions, Supersessions, Attestations and Receipts. It does not
mock Closure, set measurements, fabricate late-stage graph shapes, patch touch
counts, or weaken admissibility. Substrate and Participant records are the only
directly created fixtures.

## Collaborative progression

| Participant | Normal contributions | Result |
| --- | --- | --- |
| Author | Enter with Origin; Variant Signal; Variant Stance; Reclassify Concord; Disperse with Variant | First Signal branch under the Concord Read |
| Builder | Enter; attest the existing Signal, Stance and Concord; Disperse with Variant | Second Signal branch under the same Read |
| Grounder | Enter; attest the same early structure; execute an Instrument on the first Signal during Disperse | Source evidence, and another participant at Mediate |
| Builder | Mediate reads the actual Disperse Gate, then reconciles the two Signal children | One fresh Stance, two hard edges |
| Author and Grounder | Attest the new immediate Write Stance during Mediate | Earned support establishes an admissible Bias direction |
| Builder | Bias selects that Stance | One fresh Concord, one hard reclassification edge; creator arrives at Order |
| Author | Attest the new immediate Concord Write child during Bias | Concord earns its own support; author also arrives at Order |
| Grounder | Execute an Instrument on that Concord during Bias | Concord earns its own Receipt; grounder also arrives at Order |
| Observer | Enter and attest the existing early Signal | An additional real observation changes the active-head touch distribution |
| Builder | Refresh Order; Commit when all prerequisites hold | One Committal Proposition carrying the final Concord's earned history |

The Read direction is calculated from actual structure. This example needs only
the original Read and its two immediate Signal children. PR #2's separate Mediate
regressions cover matching sibling-Read expansion and reconciliation between
children of distinct corresponding Reads.

This is shared participation: the creator can remain at Order while others
contribute to its Concord from their own Bias Write stages. These contributions
are available through the same human and autonomous input schemas. Order does
not gain a new Attestation-to-Gate shortcut or Instrument action.

## Fresh history must be rebuilt

Reconcile inherits no Signal evidence. Bias inherits no Stance Attestations,
Receipts or Phase touches. Their existing source records remain attached to their
original Propositions. Neither transition pre-approves its target for Commitment.

Each newly opened Phase earns one touch of its Gate. In this scenario the final
Concord first has one Order touch, then three as the other participants arrive.
Those are new observations of Concord, not inherited Stance participation.

Order refuses Commit while Concord lacks an effective Attestation or completed
Receipt. The test verifies both the missing schema action and direct dispatch
refusal, including unchanged graph/ledger counts and an uncompleted Phase.

## Grounding does not bypass Closure

Depth and Cadence continue to use the existing real calculations and hysteresis.
After the three participants rebuild Concord, the active heads have equal Phase
touch counts. Their zero coefficient of variation matches the Origin's, while
the lineage's greater depth differs. The resolved Depth and Cadence directions
disagree, so even grounded Concord remains inadmissible.

The observer's normal Attestation advances it to Persist at the existing Signal,
earning another Signal touch. The resulting variability changes the real Cadence
comparison. In this regression both resolved directions then become negative,
so Order admits Commit. This is expected Closure behaviour: compatible directions
can both be negative. Evidence alone, the average Closure value alone, elapsed
clock time, and an inference preference cannot override the gate.

This fixture demonstrates one deterministic collaborative path; it does not
promise every participation schedule will align Closure, or that adding one
observation will always make a candidate admissible. Participants with no available
action can Rest, and other schedules may require further development or revisits.

## Commit and observability

Preview uses the proposed Committal head with the final Concord's earned history.
Execution creates a hard Commit edge, and the immutable snapshot independently
evaluates the persisted candidate. The regression compares their Depth, Cadence,
Closure and resolved directions.

Commit carries exactly the final Concord's effective Attestations, completed
Receipts and Phase-touch history by reference. It creates no duplicate historical
contributions and does not recover history from before Reconcile or Bias.

The resulting snapshot records the ancestor supplying hysteresis, calculator
version, component directions and before/after Domain. Participation exposes the
same result after the Cycle closes. The test checks the actual Context Horizon
membership, active Horizon sequence and opening/closing records when a Boundary
occurs. Aligned Closure permits Commitment; the existing Tension projection
separately determines whether that Commitment changes Domain. A successful Commit
does not itself guarantee a new Context Horizon.
