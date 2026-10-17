# The Commons: Participant Context C2

## Receipt Visibility, Memory and Cognitive Minimalism

Heya Work Nova! 💚🌌

Claire and Chat Nova have completed another design iteration concerning how autonomous Participants encounter information within The Commons.

Following the successful implementation and deployment of Participant Context C1, we would like you to implement **Participant Context C2: Receipt Visibility, Memory and Cognitive Minimalism**.

You have Claire's permission to access the repository and perform safe development actions, including creating a feature branch, modifying code, running tests and GitHub Actions, pushing commits, and opening a pull request.

**Do not merge. Claire will perform final review.**

**Repository:** `miranovadev/commons`
**Base:** `main`
**Suggested branch:** `feat/participant-context-c2`
**Delivery:** One PR, four staged commits

This is primarily a participant-facing cognitive-context refinement, with limited, explicitly authorized changes to Receipt loading behaviour.

It must not alter the substrate's mathematical, causal, admissibility or lifecycle semantics.

---

# 1. Background and motivation

Participant Context C1 introduced five stable cognitive components:

1. Participant
2. Read
3. Gate
4. Write
5. Actions

It also introduced seven approved register questions and substantially reduced the operational instructions supplied to autonomous Participants.

C1 was successful as a structural simplification.

However, inspection of actual production inference requests revealed several problems:

- Participants could see Receipt references without seeing the underlying evidence.
- Even loaded Web Search Receipts could be absent from cognitive content because evidence activation was tied to the operational Read rather than the new cognitive Read/Gate projection.
- Receipt metadata was duplicated between Proposition objects and a separate Receipt catalogue.
- A Participant performing an Instrument did not automatically load the Receipt it created.
- The cognitive context continued to expose observables, Attestation aggregates, Reinforcement counts and structural-position metadata that the Participant does not need.

These issues may contribute to repetitive or unproductive Proposition content.

For example, Participants sometimes acknowledge that a Web Search was performed but continue declaring that information is unavailable because the actual results were not included in their prompt.

C2 addresses these problems without changing how the substrate measures or governs participation.

The guiding principle remains:

**The substrate owns its dynamics. The Participant encounters propositions, evidence and available actions.**

---

# 2. Approved C2 cognitive contract

The target top-level inference context is:

```typescript
type ParticipantContext = {
  participant: {
    id: string;
    callsign: string;
    designation: string;
  };

  receipts: ReceiptMeta[];

  read: CognitiveRegister;
  gate: CognitiveRegister;
  write: CognitiveRegister;

  actions: ActionSchema;
};

type CognitiveRegister = {
  question?: string;
  propositions: Proposition[];
};

type CognitiveProposition = {
  id: string;
  statement: string;

  participant: {
    id: string;
    callsign: string;
  };

  receipts: string[];

  // Preserve existing individual Attestation data for C2.
};

type ReceiptMeta = {
  id: string;
  type: string;
  reference: string;
  loaded: boolean;

  loaded_inline_content?: unknown;
};

```

This is the approved conceptual contract.

Preserve additional Proposition fields only where genuinely needed for cognitive interpretation or correct action selection.

Existing server-side schemas and validation remain authoritative.

## Required property order

The serialized inference JSON should follow this order:

```text
participant
receipts
read
gate
write
actions

```

This order is intentional.

Receipt information is introduced once, before the propositions that reference it.

Read, Gate and Write retain the shared C1 structure:

```typescript
{
  question?: string;
  propositions: Proposition[];
}

```

When a question is absent, omit the property entirely.

Do not serialize absent questions as `null`.

Preserve all seven approved C1 questions and their existing Phase/Register mapping.

Do not reintroduce a separate `lineage` property.

---

# 3. Stage 1: Normalize Receipt presentation

The first stage establishes a single authoritative Receipt metadata collection.

## 3.1 Proposition Receipt references

Currently, individual Propositions can contain Receipt objects similar to:

```json
{
  "receipts": [
    {
      "id": "R1",
      "type": "web_search",
      "reference": "example query",
      "loaded": true
    }
  ]
}

```

Replace this inference representation with Receipt ID arrays:

```json
{
  "receipts": ["R1"]
}

```

The Proposition records which Receipts are associated with it.

It does not repeat their metadata.

Preserve the existing canonical Receipt associations and attachment semantics.

This is a serialization change, not a database change.

## 3.2 Top-level Receipt metadata

Introduce one top-level `receipts` array.

Each Receipt ID must appear at most once.

Example:

```json
{
  "receipts": [
    {
      "id": "R1",
      "type": "web_search",
      "reference": "Claire Mira Shaw",
      "loaded": true
    },
    {
      "id": "R2",
      "type": "hyperlink",
      "reference": "https://example.com",
      "loaded": false
    }
  ]
}

```

Receipt metadata must accurately describe the current Participant's loaded state.

A Receipt loaded by Participant A is not automatically loaded for Participant B.

Deduplicate by Receipt ID, including cases where the same Receipt is referenced by Propositions in multiple cognitive registers.

Use stable deterministic ordering to preserve reproducible prompts and support caching.

## 3.3 Receipt catalogue scope

The primary Receipt collection should contain the Receipts associated with propositions visible in:

- Read
- Gate
- Write

Additionally, preserve independently eligible scoped cognitive memory, where existing semantics require it.

Do not indiscriminately serialize every historical Receipt belonging to the Substrate.

A Receipt's inclusion in the cognitive catalogue does not grant actionability.

The action schema remains the authority for loading, unloading, replay, attachment and other operations.

Preserve the ability to identify Receipts that are available but not currently loaded.

## 3.4 Preserve distinct meanings of attachment

Do not confuse:

- A Receipt's association with a Proposition.
- A Receipt's consumption or attachment through an existing substrate operation.
- A future provider-native evidence file attachment.

C2 implements the first two only as required by existing semantics.

Provider file attachments are explicitly deferred.

Do not introduce a misleading `attached` flag whose meaning could be confused with existing Receipt attachment or consumption.

---

# 4. Stage 2: Cognitive evidence visibility and loading

This is the central behavioural change in C2.

We want to distinguish:

**Receipt discovery:** The Participant knows a Receipt exists.

**Receipt loading:** The Participant has loaded that Receipt into its own context.

**Receipt content visibility:** The Participant can inspect the Receipt's evidence in the current cognitive position.

These concepts must remain independent.

## 4.1 Visibility rules

Apply the following contract:

| Receipt association    | Metadata     | Loaded content |
| ---------------------- | ------------ | -------------- |
| Read, loaded           | Visible      | Visible        |
| Read, unloaded         | Visible      | Hidden         |
| Gate, loaded           | Visible      | Visible        |
| Gate, unloaded         | Visible      | Hidden         |
| Write, loaded          | Visible      | Hidden         |
| Write, unloaded        | Visible      | Hidden         |
| Read and Write, loaded | Visible once | Visible once   |
| Gate and Write, loaded | Visible once | Visible once   |
| Read and Gate, loaded  | Visible once | Visible once   |

A Receipt's content is visible when:

```typescript
const contentVisible =
  receipt.loaded &&
  (
    referencedByRead(receipt.id) ||
    referencedByGate(receipt.id)
  );

```

Write never independently grants content visibility.

If a Receipt is referenced by both Write and Read/Gate, it remains visible through Read/Gate.

## 4.2 Inline evidence content

For C2, preserve inline evidence transport.

When a loaded Receipt is cognitively visible, expose its existing immutable evidence through:

```json
{
  "id": "R1",
  "type": "web_search",
  "reference": "Claire Mira Shaw",
  "loaded": true,
  "loaded_inline_content": {
    "results": []
  }
}

```

The example content is illustrative.

Use the existing canonical Receipt evidence representation rather than inventing a new search-results format.

When a Receipt is unloaded:

```json
{
  "id": "R1",
  "type": "web_search",
  "reference": "Claire Mira Shaw",
  "loaded": false
}

```

When a Receipt is loaded but referenced only by Write:

```json
{
  "id": "R1",
  "type": "web_search",
  "reference": "Claire Mira Shaw",
  "loaded": true
}

```

In both cases, omit `loaded_inline_content`.

Never serialize it as `null`.

Preserve distinctions between:

- Successful observations with results.
- Successful observations with empty results.
- Failed observations that produced legitimate Receipts.
- Unavailable or missing evidence.

A failed Web Search Receipt should expose its recorded failure information when otherwise eligible for content visibility.

It must not be represented as verified evidence.

## 4.3 Existing evidence transport

Inspect:

- `ParticipantPromptLayout`
- `ParticipantPromptFactory`
- `ParticipantContextSerializer`
- `ReceiptContextService`
- Receipt evidence serializers
- Provider request construction

C1 currently uses a separate `loaded_receipts` message and a supplementary Receipt catalogue.

C2's approved logical interface instead places inline content on the unique top-level Receipt metadata entry.

Avoid simultaneously supplying the same evidence in both locations.

The final provider-visible prompt must have one authoritative representation of each Receipt's metadata and at most one copy of its visible evidence body.

Preserve immutable Receipt content and evidence integrity.

Keep the existing provider transport and caching mechanisms wherever feasible.

If the existing separate evidence-message boundary is important for caching, investigate whether it can be retained without duplicating model-visible Receipt content or violating the approved logical cognitive contract.

Do not introduce an elaborate new caching architecture during C2.

## 4.4 Automatic loading after Instrument execution

Currently, Instrument execution creates a Receipt without automatically creating the performing Participant's loaded Receipt context.

Change this.

When a Participant successfully executes an Instrument and a Receipt is created:

1. Persist the Receipt normally.
2. Automatically load that Receipt for the performing Participant.
3. Preserve the existing Participant-specific loading representation.
4. Make the loaded state available to the next Phase.

The Receipt creation and automatic loading must occur atomically.

If the operation fails before producing a Receipt, do not create a loaded state.

If an Instrument legitimately produces a Receipt describing a failed observation, automatically load that Receipt as well.

The Participant should be able to inspect the recorded failure when that Receipt becomes cognitively visible.

## 4.5 Reinforcement

Do not add special Reinforcement exceptions.

Preserve existing Reinforcement behaviour exactly.

If an automatically loaded Receipt qualifies for Reinforcement under the current implementation, allow that existing behaviour to occur.

If it does not qualify, do not manufacture Reinforcement.

Do not modify Correspondence, Chirality, Closure or any measurement calculation to accommodate automatic loading.

**Automatic loading is not a new source of mathematical authority.**

## 4.6 Loading from Write

Participants must be able to request loading of eligible Receipts associated with propositions visible in Write.

This is important because Write may contain propositions the Participant is considering selecting or traversing.

The intended sequence is:

```text
Phase N
  Write contains Proposition P
  P references Receipt R
  R is unloaded

Participant submits:
  Valid action
  receipt_options.load = [R]

Contribution accepted
  R becomes loaded for this Participant

Phase N+1
  P appears in Read or Gate
  R remains loaded
  R's evidence becomes visible

```

Preserve the existing atomic contribution and Receipt desired-state mechanisms where possible.

Do not change movement eligibility or assume a loaded Receipt makes its Proposition traversable.

Only server-authorized Receipt IDs may be loaded.

Visibility alone does not grant permission.

## 4.7 Unloading

Allow Participants to unload any Receipt currently loaded in their own context, even if its Proposition is no longer visible in the current Read, Gate or Write.

This is an explicit C2 decision.

Unloading is a Participant-memory operation.

It should not require the originating Proposition to remain the acting Gate.

Preserve transaction safety, Participant ownership, Substrate isolation and current-phase validation.

Unloading must not rewrite or delete the Receipt itself.

Existing memory, evidence and Reinforcement consequences of unloading remain unchanged.

Avoid introducing additional restrictions based on assumed cognitive preferences.

Whether Participants unload too frequently is an experimental question for later observation.

## 4.8 Loading timing

Preserve this ordering:

```text
Observe or select Receipt
        ↓
Accepted contribution
        ↓
Receipt load/unload state persisted
        ↓
Next Phase
        ↓
Updated cognitive context

```

Do not expose newly loaded evidence earlier in the same inference request that initiated loading.

The current Phase's action must complete before its resulting Receipt state affects the next Phase's prompt.

---

# 5. Stage 3: Remove unnecessary cognitive metadata

C1 substantially reduced operational instructions.

C2 should further simplify the Proposition data supplied to autonomous Participants.

Remove the following properties from inference-visible Proposition objects:

```text
observables
attestation_summary
reinforcement_count
structural_position

```

Specifically, do not expose:

- Correspondence values.
- Chirality values.
- Closure values.
- Aggregate Attestation counts.
- Retained aggregate directions.
- Reinforcement counts.
- Internal structural-position identifiers.

These remain available to the substrate's calculations, operational planning, diagnostics and human workspace.

Do not remove them from canonical storage.

Do not change the calculations that produce them.

## 5.1 Individual Attestations

**Preserve individual Attestations and their rationales in C2.**

We have deliberately deferred experimenting with their cognitive visibility.

Do not remove or restructure individual Attestation information as part of this task unless required to maintain the existing serialization contract.

A later controlled experiment may investigate whether removing Attestations improves cognitive independence.

That is not part of C2.

## 5.2 Preserve necessary Proposition identity

Keep:

- Proposition ID.
- Proposition statement.
- Creating Participant ID.
- Creating Participant callsign.
- Receipt ID references.
- Existing individual Attestation context.
- Other minimal metadata genuinely required for meaningful interpretation or valid action selection.

Do not infer authorship from causal position.

The Participant must be able to compare its own ID against a Proposition's recorded creator ID.

## 5.3 Avoid replacing removed metadata with explanations

Do not replace observables or aggregate metadata with narrative summaries such as:

- "This proposition has positive Chirality."
- "This proposition is highly reinforced."
- "This proposition is close to Commitment."

That would reintroduce the same cognitive influence in natural language.

The substrate should resolve those conditions into the available action schema.

---

# 6. Stage 4: Integration, validation and diagnostics

Complete the implementation with focused regression coverage.

## 6.1 Receipt normalization tests

Verify:

1. The top-level order is Participant, Receipts, Read, Gate, Write, Actions.
2. Each Receipt ID appears at most once in the top-level catalogue.
3. Proposition Receipt fields contain arrays of IDs only.
4. Multiple propositions referencing the same Receipt do not duplicate metadata.
5. Receipt metadata accurately reflects the current Participant's loaded state.
6. Different Participants can observe different load states for the same Receipt.
7. Legitimately scoped cognitive memory remains available.
8. Unrelated historical Receipts are not unnecessarily exposed.
9. No dangling Receipt references are introduced.

## 6.2 Evidence visibility tests

Cover every combination of:

- Read.
- Gate.
- Write.
- Loaded.
- Unloaded.
- Multiple-register references.

Specifically verify:

- Loaded Read content is visible.
- Loaded Gate content is visible.
- Loaded Write-only content is hidden.
- Unloaded content is hidden everywhere.
- A Receipt shared between Write and Gate is visible through Gate.
- Each visible evidence body appears once.
- Empty results and failed observations remain distinguishable.
- No evidence body leaks through supplementary provider messages.

## 6.3 Automatic loading tests

Verify:

- Instrument execution automatically loads its resulting Receipt for its creator.
- Another Participant does not inherit that load state.
- Failed transactions do not leave loaded Receipt records.
- Legitimate failed-observation Receipts are loaded.
- Load state is visible in the next Phase.
- Ordinary Receipt loading and Reinforcement semantics remain unchanged.

## 6.4 Receipt options tests

Verify:

- Eligible Read Receipt loads remain supported.
- Eligible Gate Receipt loads remain supported.
- Eligible Write Receipt loads become supported.
- Unrelated Receipt IDs remain rejected.
- Already-loaded IDs remain idempotent.
- Unload accepts any currently loaded Receipt belonging to the acting Participant.
- Unload rejects IDs not loaded by that Participant.
- Load/unload overlap remains invalid.
- Actions and Receipt options remain atomic.
- Unloading does not delete or mutate immutable Receipt content.

## 6.5 Cognitive minimalism tests

Verify removed fields are absent from inference:

```text
observables
attestation_summary
reinforcement_count
structural_position

```

Verify individual Attestations remain.

Verify Participant and Proposition authorship IDs remain.

Verify all seven approved register questions remain unchanged.

Verify the C1 Read/Gate/Write projection and recorded Reconcile relations remain unchanged.

## 6.6 Semantic invariance tests

C2 must preserve:

- Correspondence.
- Chirality.
- Closure.
- Tension.
- Charge.
- Potential.
- Supersession creation.
- Reconcile semantics.
- Causal movement.
- Admissibility.
- Commit semantics.
- Tragedy, Resolution and Exhaustion.
- Recovery.
- Attenuation.
- Historical records.

Automatic Receipt loading and expanded load/unload eligibility are the only approved behavioural changes.

Their existing downstream effects must remain lawful and unchanged.

Run focused before/after comparisons using identical substrate states wherever appropriate.

## 6.7 Performance and caching

Compare C1 and C2 under identical participation states.

Report:

- Total prompt bytes.
- Approximate or actual input tokens.
- Receipt metadata bytes.
- Evidence bytes.
- Number of duplicated Receipt entries eliminated.
- Provider message structure.
- Prompt-cache implications.
- Additional query counts where applicable.

Do not assume that immutable Receipt evidence automatically produces cache hits.

Preserve stable ordering and existing caching behaviour where feasible.

If moving evidence into the top-level Receipt array materially reduces cache reuse, document the trade-off.

Do not implement provider-native file uploads during C2.

## 6.8 CI

Run focused tests, Core, Integration and applicable frontend/provider tests.

Inspect GitHub Actions results on the final PR head.

The repository has historically had some Core and Integration baseline failures.

Report exact results and compare failing test identities against the current main baseline.

Do not weaken tests or change mathematical semantics merely to restore green CI.

---

# 7. Architectural safeguards

Preserve the separation between:

**Canonical substrate state**

The actual immutable history, Receipts, Propositions and causal records.

**Operational planning**

The authoritative actions, targets, constraints and eligibility decisions.

**Measurement context**

The substrate's mathematical inputs and historical calculations.

**Human workspace**

The broader observational interface.

**Participant cognition**

The deliberately limited information supplied to autonomous Participants.

C2 modifies the last category and explicitly extends Participant Receipt loading behaviour.

It must not allow cognitive visibility to redefine operational authority.

In particular:

```text
Cognitive Receipt visibility
    ≠ Operational evidence activation

Receipt loading
    ≠ Automatic admissibility

Receipt metadata visibility
    ≠ Load permission

Proposition visibility
    ≠ Movement permission

```

Preserve existing Receipt identity, consumption, attachment, provenance and memory semantics.

Do not rewrite historical Receipts or migrate existing data.

Existing previously unloaded Receipts should remain unloaded until explicitly loaded. Automatic loading applies prospectively to newly executed Instruments.

---

# 8. Implementation strategy

Create one branch from current `main`:

`feat/participant-context-c2`

Use four reviewable stages.

### Stage 1: Receipt normalization

Introduce the top-level Receipt catalogue and ID-only Proposition references.

### Stage 2: Evidence visibility and loading

Implement Read/Gate content visibility, creator auto-loading, Write load options and Participant-wide unload.

### Stage 3: Cognitive minimalism

Remove unnecessary Proposition metadata while preserving individual Attestations and author identity.

### Stage 4: Integration and validation

Complete regression tests, prompt comparisons, documentation and CI validation.

Suggested commit subjects:

```text
feat(context): normalize cognitive receipt references
feat(context): refine receipt visibility and loading
feat(context): remove substrate measurement metadata
test(context): validate C2 receipt and cognitive contracts

```

Preserve reviewable stage boundaries.

Create **one PR against `main`**.

Do not merge.

Claire will perform the final review.

---

# 9. Required PR report

The PR description should include:

### Implementation summary

Explain the new Participant → Receipts → Read → Gate → Write → Actions interface.

### Receipt normalization

Describe how Receipt IDs, metadata and inline content are represented without duplication.

### Evidence visibility

Demonstrate the Read/Gate/Write visibility matrix.

### Automatic loading

Explain how creator loading occurs atomically and why other Participants remain unloaded.

### Receipt options

Document expanded load eligibility and Participant-wide unloading.

### Cognitive minimalism

List removed inference fields and preserved individual Attestation fields.

### Substrate invariance

Explain why measurements, movement and lifecycle rules remain unchanged.

### Performance and caching

Provide C1/C2 comparisons and any relevant trade-offs.

### Validation

Report focused tests, full suites and GitHub Actions outcomes.

Clearly identify pre-existing failures.

### Deferred work

Document future attachment support and optional Attestation-visibility experiments.

---

# 10. Completion criteria

Consider C2 ready for Claire's final review when:

- The six top-level properties appear in the approved order.
- Receipt metadata is normalized into one top-level collection.
- Proposition Receipt references are ID arrays.
- Loaded Receipt evidence is visible through Read and Gate.
- Write alone does not expose Receipt evidence bodies.
- Duplicate Receipt metadata and evidence bodies are eliminated.
- Instrument creators automatically load resulting Receipts.
- Other Participants do not inherit that load state.
- Eligible Write Receipt loads work.
- Any Participant-loaded Receipt can be unloaded by that Participant.
- Existing Reinforcement behaviour is preserved.
- Observables, Attestation aggregates, Reinforcement counts and structural positions are absent from inference.
- Individual Attestations remain visible.
- All seven register questions remain unchanged.
- Existing C1 causal and Reconcile projections remain unchanged.
- Measurements and lifecycle semantics remain intact.
- Tests and CI results are documented.
- One reviewable PR is open against main.

**Do not merge. Claire will perform final review.**

---

# 11. Design philosophy

The Commons is an exploratory framework for emergence.

Its Participants should not need to understand the calculations that determine whether their actions are lawful.

They should encounter a coherent local situation:

**Participant:** Who am I?

**Receipts:** What evidence exists, and what have I loaded?

**Read:** What relevant history or comparison is available?

**Gate:** What propositions am I considering?

**Write:** What question frames the next contribution?

**Actions:** What can I lawfully do?

The substrate retains the full complexity of its mathematics and history.

The Participant receives only what it needs to participate meaningfully.

C1 simplified the structure of cognition.

**C2 makes evidence accessible within that structure.**

Our intention is not to force Participants toward particular conclusions.

It is to give them access to the information needed to form their own.

Good luck, Work Nova! 💚🌌

Claire and Chat Nova have carefully refined this design together. Please preserve its intent and the ten Core Invariants throughout implementation.

One PR. Four stages. No mathematical changes.

Let's see what happens when our Participants can finally read the evidence they've been collecting. 🐜💚