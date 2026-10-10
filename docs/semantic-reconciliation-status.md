# Semantic reconciliation status

> **Review proposal, not deployed behaviour.** This document indexes the
> proposed October 2026 semantics. Existing production rules remain authoritative
> for the running application until implementation and migration are approved.
> The [ten Core Invariants](core-invariants.md) remain superior to both.

## Decision ledger

| Subject | Review status | Authority |
| --- | --- | --- |
| Exactly ten Core Invariants; strict selected causal measurement lineage | Established invariant; unchanged | [Core Invariants](core-invariants.md) |
| Causal relationship vs selected lineage vs governing Committal | Established invariant; unchanged | [Core Invariants](core-invariants.md#5-provenance-defines-identity-and-scope) |
| Phase Profile C1: no invented first interval; preceding-Phase reference | Preferred target candidate; diagnostics, not deployed | [Phase Profile target](phase-profile-target.md) |
| Neutral-slot weighting for unavailable comparisons | Agreed target rule; unavailable is not an observed zero | [Phase Profile target](phase-profile-target.md) |
| Historical Extent / Historical Rhythm and Cycle-responsive prospective Closure | Agreed target candidate; not deployed | [Prospective Closure target](prospective-closure-target.md) |
| Commit preserves resolved expressive measurement state | Established for C/Ch; proposed extension to Closure | [Prospective Closure target](prospective-closure-target.md) |
| First zero-Charge direct expressive Write → Tragedy → Resolution | Agreed target lifecycle; not deployed | [Tragedy/Resolution target](tragedy-resolution-target.md) |
| After first Tragedy, Variant and Revise unavailable in owning Region | Agreed target lifecycle; not deployed | [Tragedy/Resolution target](tragedy-resolution-target.md) |
| Atomic continuation evaluation; recursive Region Exhaustion | Agreed target lifecycle; not deployed | [Tragedy/Resolution target](tragedy-resolution-target.md) |
| Existing C/Ch attenuation with local Closure | Current baseline A | [Observable calculation](observables.md#substrate-level-structural-attenuation) |
| Attenuate local Tension instead (B) | **Unresolved diagnostic alternative**; do not implement by inference | [Prospective Closure target](prospective-closure-target.md#attenuation-is-unresolved) |

## How to read the repository

- [Observable calculation](observables.md) and
  [Charge-boundary Tragedy and exhaustion](branch-tragedy.md) describe
  the currently documented production contracts unless explicitly marked
  as target proposals.
- New target documents are **review specifications**. They do not claim
  implementation, alter migrations or reinterpret frozen snapshots.
- Historical investigation documents remain evidence of earlier decisions.
  A historical document is not promoted to normative authority by its title,
  test numbers or the word *canonical*.
- Changes to the running system require a separate implementation plan,
  tests and human review.

## Reconciliation scope

The documentation review is divided into four commits: constitutional
clarification, catastrophe lifecycle, observable target architecture, and
historical/cross-reference reconciliation. No database or PHP source changes
belong to this PR.
