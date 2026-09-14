# Evidence and commitment flow integration

This integration branch contains seven ordered review units. Main is the release boundary; intermediate branches are not deployment candidates.

1. Exact Reclassify with referenced evidence and non-duplicated reinforcement.
2. Instrument/replay paths on expressive Writes throughout phases 1–6.
3. Receipt existence before Attest; personal effective Attestation before applicable Traverse, including Bias.
4. Any participant may reclassify a receipted, attested Gate; one result per source/destination globally.
5. Bias compares each candidate with causal ancestry excluding itself, retaining hysteretic direction and deduplicating shared ancestry.
6. Optional reinforcement; receipt, fresh Attestation and Depth/Cadence qualify Commit. One Commit per source globally. Committal results are frozen.
7. Order reads Stance/Frontier, commits admissible expressive Gates or traverses Committal Writes, then continues from that exact result.

Receipt existence does not imply reading, truth or reinforcement. Old content-changing Reclassify edges are not backfilled. Original evidence observations remain immutable. Reclassification does not copy votes or phase touches. Historical counts remain visible without multiplying active reinforcement.

All seven PRs target this integration branch. Later heads contain prior changes; review the individually linked commit while predecessors are open, and merge in numerical order. The diff narrows as earlier PRs merge. A final release PR to main remains draft until the complete stack is assembled and validated.

Validation must cover a legitimate Rest and return, negative Attestations, evidence reuse, lineage branching/reconciliation, duplicate generation attempts and continuity across Order. Commitment is permitted, never guaranteed on every cycle.

## Evidence continuity

Reclassify takes no authored statement. Persist, Constrain and Bias copy the exact source text. New classifications snapshot references to completed proposition Receipts; evidence attached to old Attestations is not inherited. Later receipts added to the old source do not retroactively appear on the classification. No legacy edge is backfilled. Commit retains its separate participation-history inheritance.

Loaded evidence contributes at most once per Participant to a classification, regardless of repeated links or receipts. The replaced source retains its public count, but its reinforcement does not also contribute as an active copy after reclassification. No Receipt or receipt-context load is duplicated. Replay always retains its original target eligibility.

## Grounding paths

Distinguish and Mediate now expose Instruments and Replay on their eligible existing Writes. All phases before Order can therefore ground fresh expressive propositions on subsequent encounters. Executing/replaying advances the Phase; receipt.read leaves it open. Gate neighbours and unrelated propositions remain ineligible. Variant children do not by themselves block instrument eligibility. Reused evidence never silently changes a Replay target.

## Evidence-first interaction

An existing Write requires a completed Receipt before Attest or expressive Traverse. Receipt existence suffices: no participant read or truth check is required. Both Attestation directions remain available as judgments, and further Instruments remain available after evidence exists. The rule is per target and does not suppress unrelated eligible actions or generation.

Personal effective Attestation unlocks Traverse in Distinguish, Persist, Constrain and Bias. Bias Traverse advances to Order. Reading never advances a Phase. Rest remains the fallback after all actions are specialized.

## Shared classification

Reclassify requires both a completed Receipt and an effective Attestation on the source classification, from any Participant and in either direction. Ownership is not a prerequisite. Bias applies the same gate. Existing generation limits still apply. One source/destination classification is recorded globally under the Substrate lock and a database unique key. Existing results are offered as Writes, never another generation action; personal Attestation is required before expressive Traverse. Different sources with identical statements remain distinct.

## Bias lineage comparison

Each active Stance/Frontier candidate is compared with its own causal ancestors, excluding the candidate's votes and loads. Both reconciliation parent paths contribute; shared nodes and Attestations are counted once. Traversal stops at the governing Committal boundary, whose retained direction seeds an empty/tied lineage. Unrelated siblings and other regions cannot set the comparison. Zero retains the most recent nonzero direction through ledger replay. Superseded ancestors still provide historical judgments; this does not reactivate their reinforcement in Closure calculations. Receipt and effective-Attestation requirements independently filter the resulting Gate options.

## Commitment boundary

Commit requires a completed Receipt, an effective Attestation on the selected classification, and aligned Depth/Cadence with hysteresis. Loaded Reinforcement remains a displayed weight, never a minimum gate. Each direct expressive source can be committed once globally, enforced inside the Substrate transaction and a unique source record. Earlier Commit edges also consume their direct sources; no historical records are rewritten. Alternative branches and contextual ancestors remain navigable. Consumed sources leave all contribution registers.

Committal results receive no Attestations, Instruments, Replay or revisions. Subsequent development is a Variant from the Committal. Recorded measurements are restored from the immutable snapshot on recalculation, so later evidence loads cannot rewrite the commitment evaluation. Origin bootstrap alone records its initial evidence before freezing.
