# Implementation Readiness Protocol

## Purpose

The Commons must not proceed from semantic design to implementation merely because a proposed architecture appears internally consistent.

**Implementation readiness means that an independent implementer can translate the approved specification into working behaviour without inventing, guessing, or silently altering semantic rules.**

This protocol applies to every implementer, whether a human engineer, automated agent, or other contributor. Its purpose is to prevent established decisions from being lost between investigation, design, implementation, and review.

This is an engineering review procedure, subordinate to the [ten Core Invariants](core-invariants.md). It creates no new Core Invariant, measurement, or operational authority. Review effort should be proportionate to the semantic consequences of the change; a straightforward presentation correction does not require the same investigation as an observable replacement.

## 1. Authority and precedence

Follow the authority order defined by the Core Invariants:

1. The ten Core Invariants.
2. Explicitly approved phase, observable, lifecycle, causal, and operational semantics.
3. Implementation representation and historical code behaviour.

Within the second tier, consult approved decision records and their diagnostic evidence to recover the intended rule. **Diagnostics are evidence of a decision, not independent authority to invent or promote a new rule.** Tests and existing code cannot overrule approved semantics.

When sources seem contradictory, establish their scope, chronology, and approval status before drawing conclusions. Do not select the easiest interpretation to implement.

## 2. Retrieve decisions before declaring ambiguity

A missing statement in the immediately available document is not proof that the design question was never resolved.

Before escalating, inspect the relevant accessible sources:

- Normative project documents and approved semantic decisions.
- Earlier implementation specifications, reviews, and acceptance criteria.
- Diagnostic reports, experiments, mathematical derivations, and recorded results.
- Existing tests, implementation contracts, and historical decision discussions where accessible.

Classify findings as:

- **Documented:** The authoritative rule is explicit.
- **Recovered:** An already-approved rule is evidenced elsewhere but inadequately documented in the current specification.
- **Unresolved:** No sufficient approved rule can be recovered, or authoritative sources materially conflict.

For a recovered decision, cite the evidence and restore its precise rule to the implementation specification. Do not treat recovery as permission to approve a new interpretation.

If a relevant source is inaccessible, record that limitation. Never claim an exhaustive investigation when the evidence was incomplete.

## 3. Adversarial semantic completeness review

Review the specification from the perspective of an independent implementer. Test all *applicable* boundaries rather than stopping at one successful ordinary fixture:

- Constitutional Origin and bootstrap; first developed Domain; repeated and alternating Domains.
- Selected causal ancestry, governing Committal, Horizon, Region, and their distinct scopes.
- Multi-parent operations, Reconcile, Revision, foreign-source ancestry, and independent siblings.
- Temporal reference selection, governing Cycle clocks, historical aggregation, and comparison populations.
- Genuine zeros, unavailable values, maturity, fixed-weight neutral contributions, and hysteresis.
- Measurement initialization, authorised triggers, event ordering, transactional updates, and stale-state conflicts.
- Canonical Commit, immutable observations, historical preservation, and fresh descendants.
- Attenuation, derived fields, Domain transitions, actionability, movement, and recovery where relevant.

A first-Domain test does not establish later-Domain history. A constitutional constant does not imply an ordinary developmental observation. A measurement's update *recipients* need not be the population supplying its values.

Use representative worked cases where different plausible interpretations could produce different results.

## 4. Explicit ambiguity register

Before a semantic implementation is declared ready, provide a concise ambiguity register with these fields:

| Field | Requirement |
| --- | --- |
| Boundary | Exact situation the implementer may encounter |
| Question | Missing rule or competing interpretations |
| Resolution | Approved behaviour, stated precisely |
| Authority | Normative document, approved decision, or supporting diagnostic |
| Status | Documented, recovered, or unresolved |
| Verification | Worked example or testable invariant |

A recovered entry must be backed by evidence of a **previous** decision, not inference presented as history. An unresolved entry should name the smallest missing decision, such as a specific bootstrap value, reference population, temporal boundary, or event trigger.

No unresolved item may remain if it is necessary to implement the proposed scope correctly.

## 5. Constitutional and developmental semantics

Constitutionally established values can be genuine and authoritative without arising from the ordinary developmental event-storage mechanism.

Do not:

- Confuse a defined constitutional zero with an unavailable observation.
- Create synthetic births, Supersessions, ledger entries, or other events to justify constitutional values.
- Apply ordinary developmental measurement or maturity rules to a constitutional exception unless explicitly authorised.
- Recalculate an immutable constitutional state through a later developmental lifecycle.
- Treat constitutional reference values as extra empirical historical samples unless explicitly specified.

Preserve each rule's actual meaning: a constitutional interval may be an established interval without an ordinary expressive birth record.

## 6. Reference frames and historical meaning

Keep distinct:

- Genuine causal ancestry and the selected measurement lineage.
- Temporal observation predecessors and governing-basin Cycle clocks.
- Governing Committal, Commit Region, Domain, and Context Horizon.
- Historical observation populations and sets of entities receiving state updates.
- Operational visibility, eligibility, movement, and evidence authority.

A shared container or clock does not import unrelated Proposition observations. Select the correct lineage *first*, then apply any authorised clock or population rule.

Preserve original Attestation polarity, historical event boundaries, replacement/supersession semantics, and immutable observations. Do not reconstruct missing historical facts from today's mutable population or rewrite frozen snapshots to recover desired numerical outcomes.

## 7. Event lifecycle and preservation

For every affected measurement or state, specify:

1. Initialization and bootstrap values.
2. Events allowed to update the numeric value.
3. Events allowed to update retained directions.
4. Events that must leave the state unchanged.
5. Immutable evidence, birth observations, and snapshot boundaries.
6. Commit-time validation and preservation.
7. Concurrency, transaction ordering, and stale-state handling.

A new causal Write, projection read, or cache refresh does not independently authorise recalculation. Cached and projected values represent the defined state; they do not define it.

## 8. Self-contained implementation contract

The implementation specification must include the rules needed by a contributor who has not seen earlier conversations:

1. Approved change, authority, and exclusions.
2. Exact equations and causal, temporal, and population selectors.
3. Constitutional and ordinary bootstrap cases.
4. Historical aggregation, maturity, missing-value, and zero-value rules.
5. Authorised event transitions and update ordering.
6. Directional hysteresis and reference frames.
7. Canonical preservation and immutable history.
8. Worked examples and explicit expected outcomes at important boundaries.
9. Verification plan and relevant performance/CI constraints.
10. The ambiguity register with resolved evidence and remaining limitations.

Tests should distinguish lawful semantic changes, obsolete fixture assumptions, and genuine regressions. Do not change authored populations, weaken admissibility, or invent evidence just to restore earlier outcomes.

## 9. Readiness gate

A semantically consequential implementation is **ready** only after:

- Applicable Core Invariants and relevant historical decisions have been examined.
- The needed formulas, selection rules, bootstrap values, and later-history boundaries are explicit.
- Event lifecycles, preservation, authority, and direction rules are specified.
- Potential ambiguities have documented or recovered resolutions, supported by evidence.
- No unresolved entry blocks the requested implementation scope.
- Behaviour can be verified by concrete tests, examples, or observable invariants.
- Relevant inaccessible sources and other limitations are disclosed.

**Readiness is a claim about implementability, not optimism or general architectural coherence.**

## 10. Stop conditions and escalation

Stop at the smallest affected semantic boundary when:

- Authoritative rules materially contradict one another.
- An essential numerical, causal, or temporal rule remains unresolved.
- Correct implementation would require fabricated evidence, provenance, maturity thresholds, or reference frames.
- An optimisation changes the defined observation population or historical meaning.
- The proposed behaviour conflicts with a Core Invariant.

First search the relevant existing evidence; do not repeatedly escalate an already-resolved decision. Preserve lawful completed work where safe. Report the precise question, competing interpretations, evidence examined, and any inaccessible sources.

Do not use silent fallback values, compatibility shortcuts, or implementation convenience to resolve uncertainty.

## 11. Documentation and continuity

When an approved rule is recovered from historical investigation, add a concise authoritative clarification at its proper semantic location. Include the rule, applicable boundary, provenance, representative example, and regression test where practical.

Do not duplicate whole investigations or promote superseded diagnostic hypotheses into live alternatives. Preserve earlier evidence by reference without making it the runtime authority.

The durable repository specification, not a particular conversation or contributor, must be sufficient for future implementation.

---

**Governing principle:** Established semantics must be recovered, not rediscovered. Missing documentation is not necessarily a missing decision. Implementation must not accidentally determine the model's meaning.
