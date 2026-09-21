<?php

namespace App\Services;

use App\Enums\PhaseKind;

/** Seven fixed contracts. Shared paragraphs are private text, not a capability framework. */
final class ParticipantPhaseInstructions
{
    // Relevant in phases 1–6; deliberately absent from terminal Order.
    private const EVIDENCE = <<<'PROMPT'
Existing local mutable Writes may expose instrument.execute and attestation.create only as authorized by the schema. Instrument records an observation subject and creates an immutable Horizon Receipt; it never automatically loads evidence or verifies truth. Variant and revision start fresh evidence; reclassification retains effective evidence and its exclusions. Equivalent direct or inherited evidence blocks a repeated Instrument invocation regardless of who loaded it; instrument_exclusions supplies the target restrictions. Variant children alone do not disqualify an Instrument target. Hyperlink observes the canonical public domain/path without query or fragment and applies server stability checks. Web Search discovers up to five resources from a non-empty query of at most 1000 characters. Creating a Cite Receipt does not itself grant Reinforcement.
receipt.read loads an available observation without advancing the Phase or Gate; reading is optional, not an endorsement. A loaded Receipt is not read again. A Cite cannot be newly loaded if its referenced Proposition is already exposed in lineage, Read, Gate, Write or loaded Cite memory; existing loaded state persists when the reference reappears. Readability and replayability are independent. Cite is readable-only; Hyperlink and Web Search can also be replayable. receipt.replay uses only receipt_id, follows the same target eligibility and progression as instrument.execute, and creates a new immutable observation with replay provenance. It neither modifies nor automatically loads either observation.
attestation.create requires support or refute in direction; optional rationale never replaces direction. Other actions accept no rationale. The schema binds direction choices to exact targets. Opposite-direction Attestation supersedes the personal judgment; same-direction re-attestation is unavailable. Spent Writes remain evidence without Traverse, Attestation or redundant Instrument targets. continuation_exhausted marks successful history whose actual continuation is exhausted, not new actionability or generation capacity. Another live causal continuation keeps a shared source live.
PROMPT;

    private const TRAVERSE = <<<'PROMPT'
proposition.traverse selects an authorized causal Write by proposition_id and advances the Phase without creating content. An effective personal Attestation from an earlier Cycle replaces that same direction with Traverse; the opposite direction remains available through Attestation supersession. Receipt consumption does not erase that established path when the route is not currently spent. Another Participant's Attestation, a matching register or a same-region Write does not create personal Traverse eligibility.
PROMPT;

    private const REVISION = <<<'PROMPT'
When proposition.revise is exposed, it targets the exact owned active local Write with effective self-Refute from an earlier Cycle, never the Gate or a different producing position. Revision starts fresh votes, touches and evidence; it never resets generation.
PROMPT;

    private const GENERATION = <<<'PROMPT'
Variant is shared structural opportunity, never a Participant quota or fixed sibling cap. An empty viable position can offer it; a viable existing route blocks redundant generation. Further Variant requires the position's existing routes to be spent in its current exploration epoch and remaining Horizon Potential, subject to schema-authorized exceptions. Only a successful Variant advances its own Horizon/structural-position epoch. Historical failures remain evidence but cease excluding a route when its applicable epoch advances; other positions' epochs remain independent. Receipt creation or consumption and revision do not reopen generation.
PROMPT;

    private const RECLASSIFY = <<<'PROMPT'
proposition.reclassify takes proposition_id and receipt_id, preserves the exact source statement and creates one hard causal reclassify edge. It requires an effective source Attestation, a completed Receipt and the selected available same-Horizon Receipt under the schema's full prerequisites; Reinforcement is not a prerequisite. Attachment atomically consumes that shared Receipt once and retains it by reference. Votes and Phase touches start fresh; old Attestations are not copied. There is no second source or authored statement. Each source/destination classification is created once globally; existing local Writes keep their schema-authorized actions.
PROMPT;

    private const FINAL_ATTEMPT = <<<'PROMPT'
Each new Variant needs its own completed Instrument and effective Attestation. A Write without its own completed Instrument can expose Instrument even if other shared evidence exists; its own Instrument plus available shared evidence unlocks Attestation. Further Variant requires every current route to meet those evidence prerequisites as well as current spentness. A schema-authorized final attempt may use zero Potential with those prerequisites intact; only a successful Variant claims the shared allowance.
PROMPT;

    private const REST = <<<'PROMPT'
Rest ends the Cycle; the server derives rest_reason. no_available_move never creates Tragedy. The first no_remaining_potential Rest creates one shared Tragedy Receipt and opens one final evidence-bearing attempt. Its metadata holds reference IDs, not rewritten historical statements. Exhaustion requires consumption of that Receipt by Reclassify followed by no_remaining_potential Rest on that classification's actual continuation; an unrelated later failure cannot close the attempt.
Recovery considers only ancestral Committals on the recorded backward lineage, with actual backward-distance attenuation. It stays in the current Horizon while that basin is open, including fresh zero-Potential routes; explicit Exhaustion permits crossing backward on that same lineage, never jumping to a Horizon opener or unrelated branch. No global scan proves Exhaustion. A viable destination prepares the next Cycle there; otherwise the current position is retained.
PROMPT;

    public function forPhase(PhaseKind $phase, bool $restAvailable = false): string
    {
        $contract = match ($phase) {
            PhaseKind::Distinguish => <<<'PROMPT'
The current Committal is the Gate. Write contains causally local distinctions in the Domain's Activity or Signal register, including legitimate same-position revisions. proposition.variant creates a structurally distinct continuation when authorized; it starts fresh votes, touches and evidence. Earlier alternatives can remain visible without being eligible actions.
PROMPT
                ."\n".self::GENERATION."\n".self::FINAL_ATTEMPT."\n".self::TRAVERSE,
            PhaseKind::Persist => <<<'PROMPT'
Persist develops the selected distinction from its current causal Gate into the Domain's Frontier or Stance Write position. proposition.variant develops structure when authorized; existing causal Writes retain only their schema-authorized actions. A register label alone cannot supply producing-position identity or promote an unrelated Write. New expressive development starts fresh votes, touches and evidence.
PROMPT
                ."\n".self::GENERATION."\n".self::REVISION."\n".self::TRAVERSE,
            PhaseKind::Constrain => <<<'PROMPT'
The current Persist-produced Frontier/Stance source is being constrained into its causal Canon/Concord classification. Writes are local continuations of this Gate, including legitimate same-position revisions. Instrument observations and Attestations keep their own meanings; neither rewrites the source statement or substitutes for Receipt attachment.
PROMPT
                ."\n".self::RECLASSIFY."\n".self::REVISION."\n".self::TRAVERSE,
            PhaseKind::Disperse => <<<'PROMPT'
Disperse develops plurality from the current Constrain-produced Gate. Its Activity/Signal Writes are causal continuations of that constrained route, including legitimate same-position revisions. proposition.variant creates a structurally distinct continuation with fresh votes, touches and evidence when authorized. This position's exploration epoch is independent of the earlier distinction position; reopening one never reopens the other.
PROMPT
                ."\n".self::GENERATION."\n".self::FINAL_ATTEMPT."\n".self::REVISION."\n".self::TRAVERSE,
            PhaseKind::Mediate => <<<'PROMPT'
The preceding Disperse path in this same Cycle supplies the authoritative Read anchor: that Disperse Phase's recorded Constrain-produced Gate, not the current Mediate Gate or a guessed ancestor. Missing history does not authorize inventing an anchor. read_options contains only that one causal Read anchor. Internally, compatible Constrain-produced Reads elsewhere in the same Commit Region whose retained Correspondence direction matches, including at numeric zero, discover eligible Gate counterparts. Those regional Reads are calculation inputs only, never visible Read alternatives. This is an intentional cross-lineage Homeostatic Read comparison.
gate_options can expose the current Gate and compatible foreign-lineage Disperse Gates that are active immediate children of those Reads. Only schema-authorized other_proposition_id values are reconciliation counterparts. They are operands, never Traverse targets or movement destinations. Gate Correspondence and Attestation lean do not filter the counterparts.
proposition.reconcile takes only other_proposition_id besides action. Reconcile(current Gate, counterpart) copies the current Gate statement exactly and creates a new causal result with explicit hard parent edges from both inputs; there is no authored synthesis. Votes, Receipts and Phase touches are not inherited. Each directed pair may be generated once across all Participants; the inverse remains distinct because dominance differs. Existing Written results can expose evidence and Attestation actions; no preference between authorized actions is implied. Hard supersession does not globally consume inputs for other directed pairs. Mediate does not expose expressive Traverse, even after personal Attestation.
PROMPT,
            PhaseKind::Bias => <<<'PROMPT'
Bias evaluates eligible Mediate-produced Stance/Frontier Gates across the Commit Region internally. Each candidate's retained Chirality direction must agree with its own causal ancestry aggregate, excluding its own votes and loads. Both reconciliation parent paths count once, stop at the governing Committal and exclude unrelated siblings. At zero, the lineage retains its previous nonzero direction, seeded by the governing Committal.
The regional Homeostatic calculation population is not the operational Gate population. The Participant operates from the current causal Gate only. Other regional Stance/Frontier Propositions are calculation inputs, never participant-facing gate_options or operational alternatives. When this Gate meets the Bias prerequisites, schema-authorized Reclassify creates its causal classification continuation. Bias Write and Traverse options are only causal continuations of the current Gate.
A frozen successful classification remains traversable only from the exact Gate whose existing causal continuation produced it, subject to the schema and ordinary traversal prerequisites. Same-region or same-Horizon classifications from another Gate are never Traverse targets. Successful-route continuity preserves an edge; it does not create one. The frozen source stays immutable. Bias Traverse reaches Order through that classification's existing causal edge.
PROMPT
                ."\n".self::RECLASSIFY."\n".self::TRAVERSE,
            PhaseKind::Order => <<<'PROMPT'
Order's selected/current Gate is the Bias-produced classification. Read contains local Mediate-position Stance/Frontier ancestry. Order exposes the causally local Bias-produced expressive Writes associated with that Gate, including legitimate same-position revisions, alongside their own eligible direct Committal continuations. A sibling classification sharing a parent is not local merely for that reason.
Closure, performed during Order — Write, evaluates each causally local expressive Write independently using that Write's own Depth, Cadence, lineage/history and participation/evidence state. Closure is not a region-wide cross-lineage comparison. Local expressive Writes remain visible regardless of Closure admissibility. Closure inadmissibility removes Commit availability only; it does not erase the Write.
proposition.commit is available only where that local Write satisfies its full Commitment prerequisites, including a completed Receipt, fresh effective Attestation on the classification and aligned Depth/Cadence with hysteresis. Reinforcement adds intrinsic weight before attenuation, never a minimum eligibility gate. proposition_id selects that authorized Write. Commit preserves its exact statement and Commit-only participation history by reference, creates an immutable snapshot and consumes the direct source once globally; later evidence cannot rewrite it.
If a local Write already has its own direct Committal child with an existing snapshot, only that exact Committal may expose proposition.traverse. Another Write's or another lineage's Committal is never a Traverse target. Order remains terminal: only schema-authorized Commit or direct Committal Traverse is available, or exclusive Rest when neither remains. Order introduces no new Attestation, Instrument, Revision, Reclassification or Variant actions; there is no receipt.read or receipt.replay. All grounding and attestation happen before Order. Commit and Committal Traverse complete this Cycle at that exact Committal; the next Cycle starts there under its applicable Horizon, without relocation.
PROMPT,
        };

        return "Current Phase: {$phase->value} {$phase->name}\n".$contract
            .($phase !== PhaseKind::Order ? "\n".self::EVIDENCE : '')
            .($restAvailable ? "\n".self::REST : '');
    }

    /** Change any instruction, including a conditional paragraph, and reversion the family. */
    public function fingerprint(): string
    {
        return hash('sha256', json_encode(array_map(
            fn (PhaseKind $phase): string => $this->forPhase($phase, restAvailable: true),
            PhaseKind::cases(),
        ), JSON_THROW_ON_ERROR));
    }
}
