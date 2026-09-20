# Stage A: participant context boundaries

Stage A separates calculation inputs and evidence existence from participant-visible content.

- Mediate exposes only its same-Cycle causal Read anchor. The original regional Read pool still discovers reconciliation counterparts. Directed pair exclusion, inverse eligibility, schemas and historical context retain their existing calculation paths.
- The shared Horizon catalogue contains only available, unattached Receipt metadata. Unread evidence, signatures and observation errors do not enter that catalogue.
- Exposed propositions carry lightweight `receipts` references: identity, type, reference and originating Horizon. The existing evidence-history service supplies direct evidence, retained classification links and Commit inheritance; references do not load content.
- Existing loaded-state entries remain in `plan.receipts`, even when consumed. They are personal loaded context, not shared available catalogue entries. The prompt layout still moves their full bodies into `loaded_receipts` exactly once and joins dynamic state by ID.
- Human Receipt controls also recognize proposition references, with Read/Replay eligibility coming exclusively from the supplied schema. Evidence renders only when loaded.

Receipt discovery, qualification, Read/Replay targets, admission of historical loads, Reinforcement and phase progression remain unchanged. No same-phase second inference, retention policy, truncation, Commit Region discovery or unloading flow is added.

## Cache contract

The three-message layout and two cache boundaries are unchanged. `commons-prefix-v2` remains appropriate: corrected format/Mediate instructions automatically change the existing instruction-family fingerprint. No old instruction family is forced warm. Loaded bodies retain identical serialization and ordering.

## Measurements

The persisted receipt-heavy fixture creates twelve verified observations and explicitly loads three. Its catalogue contains nine unread observations. Bytes use compact JSON and exclude system/transport overhead:

| Measurement | Before | Stage A |
| --- | ---: | ---: |
| Unread catalogue | 35,549 | 3,765 |
| Loaded evidence section | 11,367 | 11,367 |
| Evidence + dynamic packet | 63,239 | 31,455 |
| Visible Mediate Reads | 1 | 1 |
| Reconciliation counterparts | 3 | 3 |

This is a controlled database fixture, not a fresh production dump. It demonstrates a 50.3% packet reduction from removing unread bodies; no provider savings are inferred.

The [offline report](stage-a-context-measurements.json) projects the two existing replay captures (1,230 packets). It retains exact schemas and loaded evidence, and projects the documented anchor-first Read list to one anchor. Across 182 Mediate packets, visible Reads fall from 665 total to 182. Mean evidence+dynamic packet size changes from 26,044.19 to 25,785.81 bytes: these captures already have mostly loaded evidence. The captures cannot reconstruct newly exposed attachment references; persisted tests cover those separately.

Reproduce with `php scripts/measure-stage-a-context.php`. The receipt-heavy measurement runs in `ParticipantPromptPersistenceTest`. The earlier phase-prompt comparator remains a historical #189 measurement with its original source guards; run it at that revision rather than weakening its equivalence checks for Stage A.

## Regression boundaries

Both Domains cover anchor-only visibility, preserved foreign Gate discovery, directed A→B exclusion, inverse B→A availability, and schema counterpart identity. Real Receipt actions cover unread search/excerpt absence, attachment references, retained consumed Read eligibility, exactly-once loaded evidence, and deterministic clear/reload ordering.

Archived HTTP transcripts retain their authored inputs and action expectations. A named Stage A expectation projection removes only excess Mediate Reads and unavailable/attached unread catalogue entries. Newly bound historical Receipt identities come from the declared proposition's reference rather than the old catalogue position. Actual response assertions are not filtered to conceal extra content.
