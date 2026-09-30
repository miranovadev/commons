# Attestation polarity within a Commit Region

These rules apply the [Core Invariants](core-invariants.md), particularly
measurement independence (1), canonical meaning (3), Committal provenance (5),
fixed frames (6), distinct reference frames (7), and evidence preservation (8–9).

Commit Region membership defines calculation/context scope only where a Commons
operation explicitly requires it. A shared polarity, matching position or visible
Proposition never creates a movement edge. Participant movement follows the
current Gate's explicit causal continuation or recorded ancestry backward;
measurement ancestry and Participant arrival provenance remain distinct.
No edge means no movement.

Support and Refute label **Attestations**, never Propositions or Matrix
measurement coordinates. Correspondence, Chirality and Closure remain the three
measurements. Effective Attestation direction is evidence interpreted in a fixed
frame, not a fourth Lean axis.

For an ordinary Commit, the selected source's retained effective Attestation
direction at commitment becomes the new Committal's governing direction and the
Support direction of its new Commit Region. The opposite direction is Refute.
This frame is fixed for the region's lifetime, even if subsequent evidence,
an Expressive parent's direction, or the broader Context Horizon changes.

| Governing direction | Support | Refute | No-Attestation Expressive default |
| --- | --- | --- | --- |
| Positive | Positive | Negative | Positive |
| Negative | Negative | Positive | Negative |

Origin is the unique bootstrap exception. It starts with positive direction;
the creator must explicitly choose Support or Refute as its initial Attestation **label** for that direction, not its sign. No label is selected by default. The Attestation and immutable frame exist before measurement and snapshot publication in the atomic Origin transaction. Origin requires no Receipt; ordinary Commit grounding still requires effective Attestation plus completed evidence. Positive and negative are relational directions, not good and bad.

| Origin's initial label | Initial direction | Region Support | Region Refute |
| --- | --- | --- | --- |
| Support | Positive | Positive | Negative |
| Refute | Positive | Negative | Positive |

In either Origin case, an unattested Expressive descendant defaults to Origin's
positive governing direction. The numeric bootstrap Chirality/Tension calculation
is independent and continues to produce the documented negative bootstrap values.
Later Commits use the ordinary rule above; they do not repeat Origin's label choice.

An Expressive Proposition with no Attestations defaults to the **governing
Committal**, not its immediate Expressive parent. If that parent has diverged,
its new child still begins in the region's governing direction. Neither this
default nor a local flip changes the meaning of Support and Refute.

An Attestation's original region determines its signed evidence. Commit-only
history inheritance preserves that original polarity and original Attestation ID;
it does not relabel old evidence using the destination region's new frame.

## Value and retained direction

This distinction is required by
[Invariant 4: Zero Does Not Erase Direction](core-invariants.md#4-zero-does-not-erase-direction).

A positive or negative numeric value establishes its corresponding direction.
Exactly zero retains the preceding direction. Zero is not directionless, and
never independently removes eligibility when a direction exists.

`AttestationEvidenceService` reconstructs effective directions from ordered
contribution ledger prefixes. Attestation supersession removes the old evidence
only from the replacement's sequence onward. Every effective Attestation has equal weight; Receipts never affect values or retained directions. Original IDs deduplicate
Commit-inherited evidence. No persistent Proposition Support/Refute or Lean state
is introduced, and opening a participant plan is not required to preserve history.

`propositions.correspondence_direction` separately retains the latest established
Correspondence direction when numeric projections are replaced or deleted.
Unestablished Correspondence follows primary lineage (Supersession creation order,
then ID), with a positive root default. This inheritance concerns Correspondence
only; it does not supply Attestation polarity or effective Attestation defaults.

## Mediate and Bias have different anchors

Mediate's preceding Disperse Gate remains its causal Read anchor and ordinary
local eligibility remains independent. Additional operands use the dominant
Correspondence of the full homologous Read population, including that anchor
exactly once. A candidate Read's retained Correspondence must match the dominant
field; its Gate's Correspondence and Attestation values do not select it.
A zero-valued member retains its own established direction. A zero aggregate
retains explicitly supplied prior population direction or initializes from the
positive Correspondence default; matching members may still supply operands.
It cannot borrow the anchor's retained direction. Immediate active canonical
Disperse children remain subject to their own Region/Horizon and action rules.

Mediate — Read and Bias — Gate use their respective homologous populations for
additional Reconcile operands and Reclassify sources. Mediate creates causal edges
from both inputs; Bias creates one edge from the selected source to its new
acting-Cycle classification. The ordinary local paths remain independently eligible. Closure, performed during Order — Write, evaluates
each local expressive Write's own lineage and participation, not regional peers.

For its ordinary local route, Bias compares the current head with its own causal ancestry. The candidate's
own votes and loaded contributions are excluded from the ancestry aggregate.
Both reconciliation paths participate, shared ancestors and Attestations are
deduplicated, and traversal stops at the governing Committal boundary. Other
branches and the wider Stance population cannot set this direction.

At aggregate zero, ledger replay retains the lineage's preceding nonzero
direction, initialized from the governing Committal direction. Historical
ancestor judgments remain context; they do not become additional active
reinforcement copies. An eligible candidate must agree with that direction and
have both a completed Receipt and an effective Attestation. Additional homologous
Gates instead qualify by dominant numeric Chirality, with the same operational
prerequisites. Population influence never vetoes the ordinary local Gate.
Receipts always come from the acting Cycle-entry catalogue, even when the selected
source belongs to another Region/Horizon; its own Receipt stock is not imported.

Unary Bias copies one selected statement exactly into the next register and
creates one shared hard reclassification result. Reclassify explicitly selects, attaches and consumes one available Horizon
Receipt by reference, and resets Attestations and Phase touches. Another
Participant may use that existing Write only when independently authorised on
its own causal lineage. At Bias, Traverse requires the Write continuation of
the current Gate; a same-region classification elsewhere is not a destination.
Commitment preserves an existing edge from that exact Gate, never creates one.

## Upgrade

The additive migration stores immutable region frames, reconstructing old Commit
polarity from evidence at the original Commit edge and Origin polarity from its
initial Attestation label. It preserves recoverable Correspondence signs and
invalidates disposable numeric projections for the corrected evidence polarity.
Older Correspondence signs already overwritten by zero cannot be recovered;
those positions use lineage/default fallback. Immutable measurement snapshots
are not rewritten. No fourth measurement or per-Proposition Lean state is added.


Chirality v2 names the product of the two effective Domain leans **Orientation**. Its second component, Attestation Cadence, counts all historical acts per current canonical Cycle-entry population of those same causal lineages. Chirality is their arithmetic mean; component directions do not authorize actions. See [observables](observables.md#local-chirality).
