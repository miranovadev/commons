# Gate-causal Receipt cognition — Stage 1

Authority: Claire's 28 October 2026 implementation handoff. This changes cognitive
presentation, not evidence acquisition, personal Exploration, movement, lifecycle
eligibility, attestation or consumption rules. No schema migration is required.

## Selection and provenance

`EvidenceExecution.proposition_id` remains the exact originating Gate. The
cognitive catalogue discovers its recorded observations and recorded associations
on selected Propositions. Consumption never excludes an otherwise qualifying
observation. `ReceiptConsumption.proposition_id` is the only consumer authority;
the projection exposes it as `consumed_by_proposition_id`.

The existing `CognitiveEvidenceHorizon` still selects the current Gate, its local
ancestors and authorised continuation history, and its governing Committal.
Distinguish retains canonical committed ancestry. Terminal nodes stop forward
expansion; they retain their own evidence. Committals remain hard endpoints, with
only `PropositionStateService`'s canonical source associations inherited.

An encountered genuine Reconciliation now unions both operands' qualifying
backward ancestry. This includes a Reconciliation selected in continuation
history. The operand worklist never descends into siblings or other continuations.
Foreign Commit Region or Context Horizon operands retain the existing direct-leaf
visibility; they do not seed foreign ancestry. Nested and overlapping ancestry is
visited once per traversal mode and Receipts are deduplicated by immutable ID.

This selector supplies no movement, Artifact-read, load, consumption,
Reclassification or Reconciliation authority. Operational services and action
schemas retain their existing selection rules.

## One catalogue, three evidence components

Proposition `receipts` arrays contain IDs. Each ID resolves once in the cognitive
catalogue, ordered by ID. Each catalogue entry has:

| Field | Meaning |
| --- | --- |
| `id`, `type`, `reference` | Existing immutable Receipt identity |
| `summary` | Bounded description, independent of loading |
| `originating_gate_id` | Exact evidence-execution Gate, when one exists |
| `consumed_by_proposition_id` | Actual canonical consumer, when consumed |
| `loaded` | Active personal ParticipantReceiptContext state |
| `loaded_inline_content` | Qualifying recorded observation or scoped memory, when included |
| `load_mode` | `participant` for personal loading, otherwise `read_context` for contextual inclusion |
| `read_context` | Whether direct selected Read consumption also qualifies the included observation |

If both personal loading and Read inclusion apply, `load_mode` is `participant`
and `read_context` is true. There is still only one content entry. These flags
describe presentation, not evidence activation or Reinforcement.

Automatic inclusion only applies to readable, recorded ordinary observations
directly consumed by an actual selected Read Proposition. Merely originating at a
Read, belonging to its ancestry, or appearing in a Committal's inherited references
does not qualify. It creates no load rows, load events, evidence signatures or
field mutations. Removing that Read removes contextual inclusion; an independently
loaded observation still follows ordinary personal-loading rules. Tragedy retains
its separate memory scope and never acquires an ordinary evidence load.

An Artifact remains preserved source material. Neither summary generation nor
Read inclusion reads Artifact bytes. Preparation's loadable observation describes
the recorded reading and outcome; loading it does not replay the source material.

## Summary inventory and limits

`Evidence\ReceiptSummary` derives summaries from original Receipt metadata without
persisting a second copy or loading related source content.

| Receipt type | Summary |
| --- | --- |
| `hyperlink` | URL, recorded title/description and verification flag |
| `web_search` | Query, recorded result count and provider |
| `proposition_search` | Query, result count, recorded domain/head/limit |
| `citation` | Referenced target ID, without expanding the target's evidence |
| `preparation` | Purpose, outcome, verification flag, selection/delivery counts, first three Artifact/read selections and delivered boundaries |
| `load_file` | Acquisition method/source/filename, verification flag, Artifact count and first three hash/media-type/size descriptions |
| `tragedy` | Recorded catastrophe, governing Committal and terminal/opening identities |

Preparation failures retain attempted selections, outcome `failed`, verification
false and bounded failure classification/stage/reader metadata. Arbitrary exception
messages are not summary content. Failed Hyperlink/Search/Download observations
are marked failed without copying unsafe diagnostic payloads. Missing values are
omitted; empty successful searches retain a result count of zero.

Strings are capped at 240 Unicode characters and summaries at 4,096 serialized
JSON bytes. Arrays are capped at three entries. Unknown types use the first three
eligible metadata keys in lexical order with bounded nesting; credential, raw
content, storage-path and diagnostic fields are excluded. Empty/unsafe metadata
falls back to the recorded observation type. Summaries do not include excerpts or
reference collections as a substitute for loading.

## Human and autonomous context

Inference retains its existing top-level packet shape. The human workspace adds
`plan.receipt_cognition`, using the same register selection and Receipt normalizer.
Its reduced register objects contain only Proposition IDs and Receipt IDs. The
Receipt panel renders that catalogue, while the operational plan remains the
source of action options. Legacy workspace fixtures retain their old rendering
fallback. The protected operational `/plan` response is not a cognitive catalogue.

## Validation

Baseline: merged main `5c3569c3f4ee2763f9f8ae7ae198368484ee3da4`.

- PHP 8.5.12 / SQLite focused evidence, C3, consumption, Preparation, serialization
  and summary tests: 118 passed, 1,385 assertions before the additional query test.
- Existing baseline focused set: 51 passed, 878 assertions.
- TypeScript, production Vite build and all 148 frontend tests pass. Receipt-panel
  assertions cover unloaded summaries, contextual content, explicit loading,
  escaping and absence of duplicate observations.
- Overlap regression: 1 to 21 unique Receipts across 21 overlapping joins keeps
  projection SQL at **26 queries**. Catalogue payload grows from **608 to 13,174
  bytes**, once per distinct observation, with one shared observation copy.
- Full Core, Integration, PostgreSQL C3 and pinned main/proposed Query comparisons
  are recorded in the PR checks and the final validation update.

The three older catalogue-shape expectations now name the added summary,
provenance and loading-mode fields explicitly. The former prospective-Reconcile
leaf expectation now requires qualifying backward ancestry while still excluding
secondary siblings, continuations and foreign history. No evidence authority
assertions or numerical measurement tolerances are weakened.

The independent main Core run already fails
`ChargeBoundaryTragedyTest::test_ordinary_unmocked_observables_can_reach_the_boundary`
in the historical Settlement/Emergence fixture; see
[main Core](https://github.com/miranovadev/commons/actions/runs/40198055889).
Broader failures are compared by identity, not assumed to be regressions or waived.

Graph fixtures establish cognitive selection and immutability, not a complete
natural lifecycle cook. No inference-provider calls or new evidence obligations
are introduced by this stage.
