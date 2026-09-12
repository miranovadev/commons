# Participant workspace presentation

The Phase view keeps the existing action schema and model rules. Its layout is
organized around three stable regions: Read, Gate and Write.

## Layout and navigation

- At desktop widths, the operational board fills the available viewport height.
  Each region has its own vertical overflow for short windows or enlarged text.
  Proposition collections occupy one horizontal row, with scroll snapping,
  keyboard arrows and previous/next controls that move approximately one visible
  group. Controls appear only when the collection overflows, and hide at ends.
  The snap viewport has no horizontal padding, so the first snap point is zero;
  a layout effect resets the scroll position before paint when the Phase changes.
- Notices sit inside Write, above generation and the card navigator, at every
  viewport size. Compact full-title chips scroll horizontally and open their
  content in the existing viewport-constrained Mantine Modal. No status shell
  column or extra shell row is reserved. Commitment and Closure chips independently
  use grey for unavailable/unsatisfied and Indigo for available/satisfied state.
  Commitment uses an open lock until the action schema offers a valid Commit.
- Below 48em, all three operational regions remain expanded in an internally
  scrolling workspace. Cards snap individually and leave a small next-card peek.
  The document itself does not become the scrolling container.
- Lineage opens separately so expanding history cannot resize the operational
  regions. Inspecting a lineage card closes that Modal before opening the Drawer.

The short-window design deliberately allows vertical scrolling **inside** a
region instead of making text and action targets arbitrarily small. At tablet
widths the Phase progress list is hidden to leave enough room for identity and captions.

## Directions and evidence display

`PropositionPresentationService` adds this read-only field to every Proposition
serialized in a participation plan:

```json
"attestation_summary": {
  "support_direction": "negative",
  "retained_direction": "positive",
  "positive": 2,
  "negative": 5
}
```

`support_direction` comes from the immutable Commit Region polarity service,
including Origin's special initial label choice. It does not come from a local
measurement, local directional flip or instantaneous Chirality. Invalid legacy
ancestry can expose a null frame; the UI then avoids assigning invented signs.

The counts are unweighted effective Attestations, deduplicated through existing
Commit-only history sources. Each Attestation is signed using its **original**
region, including evidence inherited across a Commit into an opposite frame.
The existing `attestation_counts` payload remains unchanged for compatibility.

`directionalPresentation` supplies the same left/negative, right/positive mapping
to card controls and summaries. Violet travels with Support; teal travels with
Refute. The combined summary uses one whirl: count majority determines its
rotation. A tie (including zero) stops the animation but keeps the orientation
and Support/Refute colour of `retained_direction`. That additive value comes
directly from `AttestationEvidenceService::directionFor`, including its historical
replay and governing-direction fallback; the client never infers it from a tied
count, Chirality or another measurement. Existing evidence rules are unchanged.
A missing retained value on an older payload stays neutral rather than being guessed.

`DirectionalWhirl` separates animation from SVG mirroring. Positive uses clockwise
rotation and the native Tabler geometry; negative mirrors the geometry and rotates
counter-clockwise. Reduced-motion settings suppress animation. Traverse arrows
sit inward from the corner whirls and point outward into them.

## Actions, metadata and identity

Variant and Bias appear in a generative Write subsection. Reconcile appears on
each schema-authorized Mediate Gate counterpart, opening the existing form with
`other_proposition_id` selected. The current Gate is explicitly labelled as the
retained statement. Hover or keyboard focus on a counterpart's Reconcile control
subtly dims that card. Gate cards still show the same Mediate/Bias populations. Reclassify stays on its current Gate card; Revise,
Instrument and Commit stay on eligible Write cards. Attest/Traverse retain the
existing direct-submission contract and phase concurrency guard.

Compact cards group tier, register and Git-style operation badges above the
statement. Supersession badges use completed-history wording (Reclassified, Revised,
Committed, Reconciled) and subdued borders/text; action buttons retain imperative
labels. Register-transition prose remains in detailed inspection. Automatic Gate
selection and committal border highlighting are removed; neutral borders are the
default, while explicit selection, hover and keyboard focus retain their states. Reconcile display metadata
chooses its source by Supersession creation time and ID, rather than unspecified
database row order; this does not change any ancestry or measurement traversal. Measurement
readouts use darker/lighter Indigo for negative/positive numbers and neutral text
for zero or unavailable values. No formulas change.

Participant initials use a shared deterministic callsign hash into the Mantine
palette, also applied to the participant list. Phase identity sits left and
Participant identity right in one row. Below 48em, designation is hidden and the
Phase caption is available through a tap/click Info Popover. The Participant
Drawer shows full designation and identity details. At 75em and wider, a compact,
non-interactive seven-Phase progress list highlights the current Phase in Indigo.
Metadata/icons are shared from `resources/js/phases.ts`.

The Participant Drawer lists owned Participants (or all for an existing system
user), switches routes directly, and checks entry only for the waiting Participant
selected with **Check Cycle entry**. It reuses the existing `/participate` read
and `/participate/enter` mutation, with no client eligibility calculation or
collection-wide plan fetching. The shared `CycleEntryControl` preserves Origin
forms, Potential selection, recovery labels and unavailable reasons. Server
authorization remains authoritative. Participant data is invalidated after entry
and contribution, and route changes remount the page's pending action/Phase guards.

## Validation and review

```sh
npm ci
npm run build
npm run test:ui
php artisan test
npm run preview:workspace
```

The local preview at `http://localhost:4173` uses synthetic data and the real
ParticipantPage/components. It sends no API requests. Its controls cover all seven
Phases, both Support frames, empty/single/dense collections, both themes, and
1920×1080, 1366×768, 1024×768, 390×844 and 320×568 viewports. Simulated contributions
are displayed in its header and never persisted. The Participant Drawer includes
active, recovery-eligible, blocked and Origin-entry examples; entry changes only
the preview fixture in memory. The preview is a development
entry point, excluded from the production application bundle.

Automated coverage checks both polarity mappings, portal arrow ordering, signed
counts/ties, colour association, deterministic avatars, navigation boundaries and
step sizing, contextual versus generative actions, metadata and status structure.
Backend tests cover Origin's label exception, inherited evidence across opposite
frames, replacement freshness, retained direction at zero/ties and plan serialization. The existing 43-Proposition
performance fixture keeps its optimized counts: 210 Mediate, 196 Bias, 194 Order;
warm counts remain 54, 48 and 46.

**Visual review remains required before merge.** The session's browser security
policy blocked local-server and shared-file previews. No rendered screenshots,
computed-layout assertions or real-browser interaction checks were completed.
The SSR, pure-function and stylesheet checks are not substitutes for that review.
Use the dense fixture to check scroll boundaries, card/button clipping, both
whirl rotations, light/dark contrast, keyboard navigation, focus return, and
Modal/Drawer close controls at the listed sizes, including a short laptop window.
