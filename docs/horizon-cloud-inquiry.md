# Horizon cloud inquiry: implementation foundation

## Proposed semantics

A Context Horizon has one immutable Domain (Emergence or Settlement). A
separate **cloud** may have one of two inquiry orientations:

- **Dwell:** the Horizon's own retained aggregate Tension direction agrees with
  its originating Domain (Emergence = Positive, Settlement = Negative).
- **Journey:** the aggregate direction opposes the originating Domain.

A zero Tension reading **retains the Horizon's own previously resolved direction**,
not the current Domain by fiat and not the direction of any local Proposition.
The cloud may reverse repeatedly inside the same Horizon, without creating a new
Domain or new causal boundaries.

Neither mode changes Phase progression, action eligibility, personal Exploration,
Receipt acquisition, Commit admissibility or any underlying observable formula.

## Inquiry lookup

`ParticipantRegisterQuestions::forPhase(phase, writeRegister, cloud)` resolves
questions independently for each Domain × producing Phase × Cloud combination,
except Order which retains the shared **What should persist?** question.

The lookup deliberately uses complete authored sentences instead of a forced
token-substitution grammar: distinct Phase positions can share register names
without collapsing their attention semantics.

An absent cloud observation intentionally retains the approved existing question,
rather than silently assuming Dwell. This preserves compatibility until the live
Horizon measurement lifecycle is implemented.

## Remaining work before enabling live inference

1. **Canonical head population:** Define and implement distinct current
   lineage heads per creating Horizon. Do not use current Gate, selected Region,
   all Horizon propositions, or a participant-specific exploration frontier as a
   substitute. Hard-retired inputs and convergent heads require explicit tests.
2. **Separate Horizon aggregate:** Observe Correspondence, Chirality and Closure
   from each head's already valid lineage-local measurement. Preserve three
   component aggregates and their provenance. This is a descriptive summary, *not*
   an observable population in the existing lineage calculator. See Core Invariant
   7, which forbids averaging unrelated lineages as though they were one
   measurement lineage.
3. **Historical direction:** Store/derive the Horizon's own previous direction
   at ordered, causal measurement mutation boundaries. The existing final
   Proposition measurements cannot reconstruct this from a SQL dump. Zero must
   retain that Horizon history; bootstrap from the originating boundary, not a
   sibling's or latest substrate snapshot.
4. **Publication:** At a consistent participation snapshot, pass the resolved
   `HorizonCloudMode` to the question resolver. Do not add operational actions
   or loosen Generation / Acquisition rules.
5. **Validation:** Cover Origin, child Horizons, backtracked historical Cycles,
   concurrent branches, zero crossings, supersession head replacement, no
   measurements, measurement changes without births, and parallel inference.

## Status

This branch provides the **question vocabulary and pure sign/direction
derivation** with unit tests. It does **not yet activate cloud-selected inference
questions**. It must remain a draft until historical Horizon aggregation and
publication are implemented and verified.

## Invariants

- No fourth observable.
- No aggregation of unrelated lineages **inside** measurement calculators.
- No inferred movement or action authority from Horizon membership.
- No retrospective rewriting of Domain or historical measurement direction.
- No reset of Exploration events or Attestations.
