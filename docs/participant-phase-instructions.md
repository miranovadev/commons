# Participant Phase instructions

The current prompt is **a small Commons core plus the current Phase contract**.
It explains the state without preferring an action. Server state, action schemas,
output schemas, visible lineage, evidence and substrate behavior are unchanged.
This supersedes the monolithic instruction section of [#187's caching baseline](participant-prompt-caching.md).

## Baseline and ownership

The baseline is merged #187, `5fc2fb9be8b096a22206fa639edb7fee61cce794`, including
the #185 constitution correction. Its constitution is **17,200 UTF-8 bytes**,
approximately **4,300 tokens** by bytes/4. With the existing layout explanation and
the benchmark's fixed `Preserve uncertainty.` connection instruction, the system
message is **17,593 bytes / approximately 4,398 tokens** on every inference.

The old text was manually classified by semantic scope. These byte counts partition
its sentences, including one separator between sentences. Mixed-rule sentences
make the attribution approximate; this is not a line-count savings estimate.

| Old instruction scope | Bytes | Relevant Phases |
| --- | ---: | --- |
| Universal role, schema, topology, provenance, evidence meaning and lifecycle context | 5,371 | All |
| Shared expressive actions, receipt reading/replay and instruments | 3,674 | 1–6 |
| Structural generation and epochs | 1,473 | 1, 2, 4 |
| Classification/attachment | 905 | 3, 6 |
| Mediate comparison/reconciliation | 1,419 | 5 |
| Bias calculation and successful-route continuity | 1,342 | 6 |
| Local Closure, Commitment and terminal continuation | 2,204 | 7 |
| Conditional Rest/Tragedy details | 601 | When Rest is exposed |
| Mixed scope/Mediate exception sentence | 211 | Shared rule plus Phase 5 exception |
| **Total** | **17,200** | |

About **31.2%** concerned genuinely shared context before consolidation. This
included repetition: schema authority/valid IDs appeared in several places;
Reclassify's exact copying, Receipt prerequisite and fresh Attestations were
explained twice; successful Bias continuity appeared three times; evidence safety,
Receipt persistence and terminal Order policy each appeared repeatedly. These
concepts now have one source location or a fixed shared paragraph.

Approximate old Phase-attributable content includes applicable shared operational
paragraphs, but excludes the all-Phase rows above. It overlaps across Phases and
must not be summed. The new contract includes only its current operational meaning.

| Phase | Old attributable bytes, approximate | New contract bytes, without Rest | Mean full system bytes in corpus |
| --- | ---: | ---: | ---: |
| Distinguish | 5,147 | 4,240 | 7,821 |
| Persist | 5,147 | 4,030 | 7,611 |
| Constrain | 4,579 | 3,982 | 7,733 |
| Disperse | 5,147 | 4,571 | 8,152 |
| Mediate | 5,093 | 3,774 | 7,571 |
| Bias | 5,921 | 4,752 | 8,705 |
| Order | 2,204 | 2,107 | 6,503 |

The new universal core is **3,186 bytes / approximately 797 tokens**, **81.5%
smaller** than the old universal-on-every-call constitution. The old taxonomy
attributes about 6,035 bytes per corpus inference to other Phases; the new
composition includes zero other Phase contracts. The total reduction also includes
consolidation and conditional lifecycle detail, not just removal of other Phases.

## Code ownership and semantic safeguards

- `ParticipantPromptCore::TEXT`: role/agency, schema authority, causal topology,
  historical provenance, shared evidence meaning, polarity and minimal Rest rules.
  It contains no action catalog or detailed Tragedy/recovery procedure.
- `ParticipantPhaseInstructions::forPhase`: seven explicit cases. Mediate owns its
  cross-lineage reconciliation exception; Bias owns its regional calculation/local
  operational distinction; Order owns per-Write Closure and terminal actions.
- The same class owns private, fixed paragraphs for shared pre-Order evidence,
  expressive Traverse, revision, structural generation and reclassification.
  They are reused only by the enumerated Phases that admit those operations.
  There is no registry, capability discovery or general prompt-module framework.
- Detailed Rest, Tragedy and backward recovery are appended only when the supplied
  schema exposes `participant.rest`, regardless of a conflicting rest flag. The
  core retains only the universal availability/topology constraints. Generation
  contracts describe the schema-authorized zero-Potential final-attempt exception
  without teaching the full Tragedy lifecycle at ordinary distinction positions.
- `ParticipantPromptFactory` resolves the recorded Phase, rejects unknown or
  conflicting Phase metadata, and composes the instructions. It does not infer a
  Phase from register labels, inspect graph membership, or change options.

Mediate counterparts are operands with two explicit result edges, never movement
or Traverse destinations. Bias keeps other calculated Gates out of participant
options and preserves only the exact Gate's existing successful edge. Order keeps
local Writes visible through Closure refusal; full prerequisites gate Commit per
Write, and only that Write's own direct Committal can be traversed. Order has no
new mutation, evidence-loading or replay actions.

Custom connection instructions retain their existing text, existing outer `trim`
normalization, `Connection instructions:` label and final position within the same
system message. They remain after Commons instructions and above user evidence/state
in message priority. Configuration is not rewritten. Phase semantics never enter
the JSON action/state packet, and the full contract catalog is never sent to the model.

## Layout and cache-family decision

The selected request retains #187's **three messages and two explicit boundaries**:

1. System: universal core → existing Receipt-layout explanation → current Phase
   contract, with conditional Rest detail → unchanged custom instructions. Boundary.
2. User: unchanged loaded Receipt body block, ordered exactly as in #187. Boundary.
3. User: unchanged dynamic participation state and in-packet action schema.

The strict response schema remains in `text.format`. Earlier/unknown model behavior,
retention settings, telemetry and Receipt loading/clearing behavior stay unchanged.

[OpenAI's cache documentation](https://developers.openai.com/api/docs/guides/prompt-caching)
requires a matching rendered prefix at eligible boundaries; output schemas can
change that prefix. Explicit caching can exclude a changing suffix from writes.
GPT-5.6+ has a 1,024-token minimum, and the documented relative rates are 1.25 for
writes and 0.1 for reads against ordinary input at 1. The model below uses those
ratios as sensitivity assumptions, not observed usage or a price quote.

The initial temptation was to move Phase instructions after loaded evidence.
That preserves more visible text across transitions, but the schema-aware corpus
comparison favors Phase-first. It keeps a reusable instruction-only boundary when
receipts change and caches the recurring Phase text. A core-only boundary can fall
below minimum size; moving instructions after evidence also makes their reuse depend
on that evidence staying identical. Neither layout can bypass schema differences.

`commons-prefix-v2` deliberately invalidates the #187 family. The routing key hashes:

```
model + layout version + instruction-family fingerprint + substrate
```

The fingerprint covers the core, layout explanation, all seven contracts including
conditional paragraphs, and normalized custom instructions. **The selected Phase
is not a key component.** Changing any instruction rehashes the family automatically;
changing Phase, schema, evidence or Participant identity does not. Direct calls retain
the existing connection-ID fallback when substrate metadata is absent. The opaque
fingerprint is internal request-building metadata, never model-visible cognition.
A future Phase semantic edit therefore has one obvious text location and cannot
silently keep an old instruction family. Actual prefix equality still determines reuse.

## Corpus recurrence

The existing solo and collective captures provide **1,230 inference calls**, four
Participant streams and **1,226 adjacent same-Participant pairs**. A production wake
can contain several calls; these counts are not completed wakes.

| Observation | Count |
| --- | ---: |
| Adjacent calls in the same Phase | 96 / 1,226 (7.8%) |
| Adjacent calls changing Phase | 1,130 / 1,226 (92.2%) |
| Cross-Phase pairs with identical loaded evidence | 1,118 / 1,130 (98.9%) |
| Adjacent pairs with identical strict response schema | 19 / 1,226 (1.5%) |
| Returns to a previously visited Phase after another Phase | 1,108 |
| Calls with any prior same-Phase call | 1,204 |
| Calls whose preceding same-Phase call had identical evidence | 629 |
| Phase previously encountered by another Participant in that capture | 582 |
| Same Phase and identical evidence previously encountered by another Participant | 38 |

Phase contracts recur frequently, but shared contract text alone does not imply a
shared evidence prefix or output schema. The model reports separate Participant-local
and same-substrate scenarios; the latter assumes identical custom instructions.
Adding Phase to the routing key produced **no modeled reuse improvement** for the
selected layout. It would partition routing without adding any matching prefix.
These captures cannot predict real routing affinity, TTL, eviction or concurrent load.

## Measured prompt economy

All **1,230 packets reconstruct exactly**, including types and list order. Both
schemas remain identical. The benchmark verifies that the Receipt serializer,
context serializer and output-schema source are still #187's implementations,
allowing only the layout version constant to differ. It refuses to compare against
a silently changed data baseline.

Bytes are measured. Tokens below are **bytes/4 estimates**, covering visible input
text plus response-format JSON; they exclude hidden rendering and are not tokenizer
or billed token counts. The fixed test model name only selects request shape.

| Mean per inference | Merged #187 | Core + Phase | Change |
| --- | ---: | ---: | ---: |
| System instructions, bytes | 17,593 | 7,839 | −55.4% |
| System instructions, rough tokens | 4,398 | 1,960 | −55.4% |
| Complete HTTP request, bytes | 48,170 | 38,400 | −20.3% |
| Total input, rough tokens | 11,282 | 8,844 | −21.6% |
| Adjacent shared visible prefix, bytes | 33,242 | 4,850 | Smaller across Phase changes |
| Same-Phase adjacent visible prefix, bytes | 29,297 | 20,181 | Fewer instruction bytes |
| Cross-Phase adjacent visible prefix, bytes | 33,578 | 3,548 | Stops at Phase contract |

This is primarily an **instruction reduction**, not an increase in the absolute
cached prefix. A smaller prefix can accompany a lower total modeled input cost.
There are 197 observed Cycle fragments: mean instruction totals are 109,845 →
48,943 bytes. Captures can start/end mid-Cycle, so these are not complete-wake or
production billing measurements.

For the **932 receipt-heavy calls** with at least ten loads, requests shrink from
54,047 to 44,259 bytes and rough input from 12,624 to 10,181 tokens. Their same-Phase
visible prefix is 34,834 → 25,827 bytes; identical-schema retained-cache modeling
still finds 500 matching calls in both layouts. Receipt bodies themselves are unchanged.

## Ordering and write/read tradeoff

This deliberately conservative model requires exact response-format equality and
complete ordered message-prefix equality at one of that layout's actual boundaries.
It checks a rough 1,024-token threshold. It retains earlier prefixes for each
Participant indefinitely, so it excludes TTL/eviction/routing loss. Writes count
incremental unique tokens through the longest eligible boundary, without double
counting overlapping prefixes; real provider accounting can differ.

Costs are illustrative input-only **relative units per call**, using ordinary=1,
read=0.1 and write=1.25. Output is excluded. The zero-reuse column assumes caching
is enabled but all reusable prefixes are cold; cache writes can therefore cost more
than processing the same input with no caching. These are not realized savings.

| Layout | Boundaries | Modeled reusable calls | Mean read / write rough tokens | Zero-reuse cost units | Retained-prefix cost units |
| --- | ---: | ---: | ---: | ---: | ---: |
| #187 monolith → evidence → state | 2 | 601 | 3,652 / 4,867 | 13,412 | 9,212 |
| **Core + Phase + custom → evidence → state** | **2** | **589** | **2,293 / 3,788** | **10,364** | **7,727** |
| Core → evidence → Phase + custom → state | 2, before Phase | 498 | 1,854 / 3,151 | 10,094 | 7,963 |
| Core → evidence → Phase + custom → state | 3, including Phase | 498 | 2,127 / 3,954 | 10,363 | 7,918 |
| Selected layout with Phase added to routing key | 2 | 589 | 2,293 / 3,788 | 10,364 | 7,727 |

The selected layout costs about **16.1% less than #187 in this retained-prefix
scenario**, despite fewer absolute cached tokens. It beats the two evidence-first
alternatives in that scenario and preserves the existing transport structure.
Evidence-first with two boundaries is cheaper when every prefix is cold because
it writes less. Production telemetry must establish which regime actually occurs.
No production usage logs were available for this comparison; the existing telemetry
continues to report actual cached, ordinary, written and output tokens for validation.

## Reproduce and validate

These are historical #189 measurements. After Stage A, run this guarded comparator
at #189; see [Stage A context measurements](participant-context-stage-a.md) for the
intentional packet corrections.

[Machine-readable measurements](phase-prompt-measurements.json) include all ordering
alternatives, both sharing scopes, Phase sizes and receipt-heavy results.

```bash
git show 5fc2fb9be8b096a22206fa639edb7fee61cce794:app/Services/ParticipantPromptFactory.php > /tmp/participant-187.php
php scripts/measure-phase-prompts.php /tmp/participant-187.php > /tmp/phase-prompt-report.json
```

The script reads the existing two compressed captures. It needs `xz`, the normal
PHP dependencies and the exported baseline; it uses no APIs, database or lifecycle
replay and does not add those long captures to mandatory CI.

Core tests cover all seven ownership contracts, critical semantic fragments,
absence of unrelated contracts and action prescriptions, conditional Rest, unknown
Phase rejection, custom instruction preservation, packet/evidence/schema equality,
deterministic serialization and cache-family changes. Existing persisted evidence
and Integration source-equivalence tests remain authoritative; no new lifecycle
journey is added. Core and Integration are the gates; Query/Load are unnecessary
because persistence, queries and concurrency do not change. Final counts and
healthy-budget runtimes are recorded in the PR.

Deploy through the existing release/worker restart procedure. Existing Participants
receive their current contract on the next inference. No migration, substrate reset,
participant relocation or historical rewrite is required. A rollback is ordinary
code rollback. Model choices can change when irrelevant instructions disappear,
so observe natural production behavior and existing usage telemetry after rollout;
packet equality does not promise identical stochastic choices. Domain lens and
Perspective remain explicitly deferred.
