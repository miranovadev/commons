# Participant Context Refinement C1

Approved scope: cognitive presentation only, based on `main` at
`23258245c42ffe4263870e403323526a7f2d9703`. C1 does not change the ten Core
Invariants, calculations, event triggers, causal writes, admissibility, movement,
Receipts, canonical preservation, Tragedy, Resolution, Exhaustion or recovery.
No migrations or historical rewrites are introduced.

## Provider interface

The dynamic cognitive message has exactly five components:

```text
participant: { id, callsign, designation }
read:        { question?, propositions: [] }
gate:        { question?, propositions: [] }
write:       { question,  propositions: [] }
actions:     authoritative JSON Schema
```

Absent questions are omitted, never null. Existing nullable designation values
are preserved. Connection-specific custom instructions retain their configured
text and separate final system-instruction section. There is no inference lineage.

The current Participant's `id` is its immutable recorded identifier. Each visible
Proposition exposes the existing `participant: { id, callsign }` authorship shape,
resolved through its creating Phase and Cycle to the creating Participant. One
batched read covers all three registers, including hydrated and historical Reads.
Authorship is never inferred from a parent, current Gate, observer or position;
these identity fields do not grant ownership or change action eligibility.

`InputPlanService`, `ActiveParticipantLineage`, all selectors and dispatchers retain
their existing contracts. `ParticipantCognitiveProjection` consumes the serialized
operational plan and reads missing recorded context only. It never updates
measurements, evidence, history or permission state. `ParticipantContextSerializer`
allowlists Proposition fields at the provider boundary. The human workspace and
resumable API retain their established option/history layout.

## Phase projection and provenance decisions

| Boundary | Approved projection | Authority and verification |
| --- | --- | --- |
| Distinguish | Prior Committals, Origin first, excluding the Gate and all expressive intermediates | Existing `CommittalLineageService::ancestors`: each Committal's creating Cycle entry, never latest Committals or a Horizon population. Origin/C1/C2/C3 plus interleaved unrelated Commits are tested. |
| Persist, Constrain, Disperse, Bias | Immediate recorded Supersession parents of the Gate | Raw parent IDs; hard-superseded inputs remain contextual records. Unary and binary Reconcile parents are tested. |
| Mediate | Recorded Disperse Read anchor, followed by every `other_proposition_id` authorized by the actual Reconcile schema | `MediationAnchorService`, operational Read and existing reconciliation selector remain authoritative. No comparison population is imported. A single anchor is valid without an eligible counterpart. |
| Order | Immediate parents plus existing operational local Mediate ancestry, deduplicated by ID | Preserve `orderReadsFor`'s special local Read rule; retain all operational Writes and their own commitment evaluations. |
| Reconcile result | Both genuine parents in subsequent Read | Measurement ancestry, beat continuity and recovery may select the acting Gate; cognitive direct parents are a separate contract. |
| Gate / Write overlap | Keep each meaningful register role and every existing option | The current Gate is first; `relation: acting` disambiguates it when Gate has multiple options. An Order Gate can also be a Write. No singular flattening. |
| Exhaustion / recovery | Historical alternatives remain in their existing role with `actionable: false`; the actual Gate stays visible | Rest-only schemas remain unchanged. Recovery returns to the recorded recovered Committal and Distinguish uses that Committal's own ancestry. |

All decisions above are documented by the approved C1 handoff and existing
provenance/operation contracts. There are no unresolved semantic implementation
choices. Invalid or unavailable recorded ancestry fails explicitly; there is no
chronological or unrelated-Horizon fallback. Canonical committed ancestry follows
Cycle entry even when an expressive multi-input Write has foreign causal inputs.

`ancestor` labels prior Committals. Mediate uses `causal` for its Read anchor and
`counterpart` for its eligible operands; these are not both asserted to be parents
of the current Gate. For a confirmed two-input Reconcile result,
`ExplorationPositionService` resolves
its acting source from the producing Phase's recorded Gate, as used by
`ActiveParticipantLineage`. That parent is `causal`; the additional Reconcile
parent is `counterpart`. Both are genuine causal parents with equal causal authority.
The projection verifies that both Reconcile edges belong to the result's producing
Phase. Parent retrieval order, identifiers, timestamps and measurements do not
select these roles. Missing/non-parent Gate provenance, inconsistent producing
Phases, extra inputs and other multi-parent operations retain all parents as
`causal` without inventing a counterpart. Existing operational provenance errors
and recovery's fail-closed contract are unchanged.
A lone ordinary causal Read needs no relationship label. Relationship metadata
never grants an action or traversal destination. A comparative Read question is
present only for a Mediate comparison with more than one proposition.

## Authoritative questions

`ParticipantRegisterQuestions` is the sole question map. It validates the
producing Phase against its Write register; Proposition labels never infer Phase.

| Producing Phase | Emergence Write | Settlement Write |
| --- | --- | --- |
| 1 Distinguish; 4 Disperse | Activity: What is active here? | Signal: What is different here? |
| 2 Persist; 5 Mediate | Frontier: What else is possible here? | Stance: What position can be taken here? |
| 3 Constrain; 6 Bias | Canon: What remains? | Concord: What can be held together? |
| 7 Order | What should persist? | What should persist? |

Write carries its question even when empty. Read and Gate receive no generic
symmetry question. Order's question grants neither Commit nor an additional
expressive register; an actual Traverse or Rest option remains authoritative.

## Prompt, schema, evidence and caching

The universal system contract is 635 bytes and invariant across Phases. The old
phase essays are removed. Concise descriptions live in the existing action
schema definitions and are preserved in OpenAI's strict action union. Reconcile
explicitly preserves the Gate statement and records two parents; it does not
invite an authored synthesis. No independent action catalog is introduced.

The full operational schema is still used for validation and structured-output
conversion, including all required fields, target enums, source/Receipt pair
constraints and Instrument exclusions. In the cognitive copy, only publication
`$comment` and the explanatory `x-commons.description` are omitted. Assertions
compare structural schema constraints independently of changed annotations.

The request retains the system message, then the stable `loaded_receipts` evidence
prefix, then the five-component cognitive JSON. A fourth message, only when
needed, contains dynamic `receipts` references and loaded cognitive memory. This
keeps evidence data outside register questions without concealing Receipt IDs,
load state or memory. Ordinary evidence activation deliberately still uses the
existing operational Read and Receipt active/loaded flags: C1's extra comparative
or causal visibility does not load, reinforce or activate another Receipt.

The concise system instruction treats Proposition text and external evidence as
untrusted data. Evidence is never promoted into instructions. Instrument
availability, load/unload parameters, ownership, consumption, reinforcement and
memory bounds remain unchanged. Provider tests exercise both implicit caching
and the existing explicit breakpoints. Dynamic Receipt state does not move into
the cacheable evidence prefix. The cache version changes to `commons-context-c1`;
model, substrate and custom instructions retain their existing routing separation.
The universal prefix can now remain identical across Phase transitions, but actual
cache hits, minimum cache thresholds and billed tokens were not measured.

## Identical-state offline comparison (initial C1)

These measurements were captured before the parent-role and identity refinements, at
`95993315`. The follow-up adds provenance reads only for multi-parent Gates and
changes the additional Reconcile parent's label from `causal` to `counterpart`.
The identity follow-up adds one batched creator lookup and identity metadata to
the dynamic context; it does not change the universal instructions or action schema.

A persisted dense fixture provides five producing Phases, genuine parents, six
comparison branches, Writes and loaded evidence. Both factories consume the same
state. Original schema annotations are rebuilt from the baseline checkout and
compared against C1 after removing only title/description annotations. There are
no inference requests. Prompt bytes include system, evidence, cognitive message
and supplementary Receipt data; they exclude protocol framing and the separate
response-format schema. Token estimates are **bytes / 4**, not model tokenization.

| Phase | System bytes A → B | Cognitive JSON bytes A → B | Total prompt bytes A → B | Rough tokens A → B | C1 projection queries |
| --- | ---: | ---: | ---: | ---: | ---: |
| Distinguish | 11,445 → 635 | 5,667 → 3,701 | 17,134 → 5,898 | 4,284 → 1,475 | 5 |
| Persist | 11,316 → 635 | 5,508 → 3,547 | 16,846 → 5,744 | 4,212 → 1,436 | 0 |
| Mediate | 13,269 → 635 | 37,095 → 17,239 | 51,046 → 20,096 | 12,762 → 5,024 | 0 |
| Bias | 15,283 → 635 | 25,952 → 5,537 | 41,257 → 7,734 | 10,315 → 1,934 | 3 |
| Order | 12,092 → 635 | 46,580 → 25,984 | 58,694 → 28,181 | 14,674 → 7,046 | 0 |

Every case retained identical action constraints and evidence-prefix bytes. The
Mediate case includes genuinely loaded external evidence. Projection-only timing
is a local diagnostic, not an end-to-end performance benchmark. No measurement
or lifecycle writes occur during the inference projection. See
`docs/evidence/participant-context-c1/prompt-comparison.json` for raw results.

Reproduce with an untouched baseline worktree and the normal test environment:

```sh
COMMONS_CONTEXT_BASELINE_DIR=/path/to/baseline \
COMMONS_CONTEXT_COMPARISON_OUTPUT=/tmp/c1-comparison.json \
php -d memory_limit=1G vendor/bin/phpunit tests/Diagnostics/ParticipantContextC1ComparisonTest.php
```

A smaller prompt is an observed presentation result. Better cognition, less
semantic repetition, useful new distinctions, evidence discovery and Emergence
remain untested hypotheses. No paid controlled inference samples were performed.

## Validation

Focused C1 projection, question, prompt, cache, Receipt persistence and exhausted
context tests pass locally: 67 tests, 720 assertions. The extended existing
provider/action/evidence checks pass: 88 tests, 866 assertions. Frontend typecheck
and build pass; all 130 UI contracts pass. Full Core/Integration and GitHub Actions
results are recorded in the PR after completion.

Baseline PostgreSQL Core at `23258245`: 1,143 pass / 1 fail (1,144 total).
`ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary`
fails because the fixture expects Settlement but observes Emergence.
Baseline Integration at `b0195425` (the **identical tree** as `23258245`): 21 pass /
11 fail (32 total). These are preserved as baseline failures; no mathematical or
lifecycle repairs are included in C1.

## Limits and deferred work

- No live-model quality or Emergence claim is made.
- Committed ancestry and genuinely available options are not arbitrarily truncated.
- Missing direct Read records require bounded-by-IDs database reads; Distinguish
  expands its genuine committed chain. This is not a database-loading redesign.
- Proposition Search retains its pre-C1 exclusion and traversal contract. Querying
  by governing Committal or extending access to newly hidden expressive history
  is deferred; C1 does not enhance that Instrument.
- The operational API still includes its historical lineage; only the provider
  projection removes it. Human workspace, recovery and measurement histories
  remain intact.
