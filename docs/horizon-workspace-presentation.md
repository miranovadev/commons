# Horizon context and participant provenance

This presentation pass builds on Cycle ownership and Commit continuity. It changes
human workspace serialization and rendering; Potential, candidate eligibility,
Reinforcement, Horizon lifecycle and `ActiveParticipantLineage` remain unchanged.

## Separate references

`GET /api/substrates/{slug}/participants/{id}/workspace` adds `domain` and
`opening_proposition` to `plan.workspace.horizon`. The Horizon comes from the
active Cycle's `context_horizon_id`. Existing identity, sequence, parent and
opening IDs remain available. The opening object uses the normal human
Proposition serializer, including its stored register, evidence and observables.
Origin's root has no opener and returns null.

The workspace presents two independent references above Lineage:

- **Horizon:** Domain, sequence, and an inspectable opening Committal. Emergence
  uses a light indigo surface and `IconSunHigh`; Settlement uses a dark indigo
  surface and `IconSunWind`, in either application theme.
- **Provenance:** the actual structural Committal returned for this Participant.

Neither reference is added to `plan.lineage`. Only the backend's resolved actual
ancestry enters the Lineage panel. A Participant at a later non-boundary
Committal can inspect the Horizon opener separately from its current region's
provenance. This presentation never legitimises an Origin/Horizon pairing without
the required ownership or direct opening relationship. Following a real
boundary-producing Commit, the committing Participant's next Cycle naturally
uses the same ID for opener, provenance and Gate. Earlier-Phase previews retain
their existing history-only contract.

An inspectable opener, provenance card or same-Horizon Proposition is not a
movement destination. Commit Region membership defines calculation/context scope
only where required; Homeostatic populations remain separate from movement.
Forward progression needs the current Gate's causal continuation and recovery
walks recorded ancestry backward. No edge means no movement.

The autonomous `/plan` and inference contracts stay allowlisted and unchanged;
the new presentation data is confined to `/workspace`.

## Register identity and roles

Previously `ParticipantWorkspace` rotated Read/Gate/Write headings from the Write
register for all later Phases. Order retains the Bias output as its Gate and
Write, so that rotation put a Canon Gate beneath Frontier (or Concord beneath
Stance). A final `lineage[0]` Read fallback could also place an unrelated stored
register beneath a guessed heading.

`workspaceLanes` now distinguishes role from display identity:

- Order explicitly retains the Bias Read and Write vocabulary, with the actual
  Bias output as Gate. Canon Gate/Write both say Canon; Concord Gate/Write both
  say Concord. It does not rotate the actual Gate into the second register.
- Earlier Phases retain their Read-selection convention; an absent matching Read
  stays empty. Historical provenance cards retain their actual register heading.
- Every supplied role population is grouped by each card's exact stored register.
  Even an explicitly supplied mixed-register population has separate headings.
  This is presentation grouping, not new candidate filtering or authorization.
- Existing action-schema checks still decide which buttons and targets appear.
  Competing reconciliations and Bias populations remain intact.

## Serialization and cache audit

1. `Proposition.register` is serialized verbatim by `PropositionResource` and
   `InputPlanService`; neither translates register positions across Domains.
2. RTK Query's workspace endpoint only unwraps the API envelope. Its cache key
   includes substrate and Participant, and realtime invalidation targets that
   Participant. Graph refreshes do not merge graph cards into workspace plans.
3. `ParticipantPage` passes the returned plan through; its component is keyed by
   substrate/Participant, and its retained completion view keeps the completed
   Cycle's plan.
4. `workspaceDomain` prioritizes the Cycle Horizon's Domain, followed by the
   existing workspace Domain and phase-register compatibility fallback.
   Historical preview uses the same precedence.
5. Lane selectors retain original objects and never rewrite `register`.
   `PropositionCard` and `PropositionContent` use that stored identity for badges,
   color and inspection. Role headings come from the same exact register groups.

## Regression coverage and visual review

`HorizonWorkspaceTest` exercises the authorized HTTP workspace and Proposition
resource with an H2 Settlement Order still active after H3 Emergence opens.
The two workspaces preserve Concord/Canon identity, different opener/provenance
IDs, and actual ancestry. `ParticipantContinuityTest` also checks the serialized
references after a real Commit and new Cycle entry.

`horizonWorkspace.test.mjs` renders the actual React workspace, inspects its DOM,
checks both Domains across all seven Phases, and runs real RTK Query fetch/refresh
and cache selectors for two concurrent Participants. It verifies every rendered
card agrees with its register heading, distinct references, post-Commit
coincidence, empty Reads, mixed-register grouping and explicit Origin handling.
Existing historical preview and schema-authorized action tests remain in place.

Run `npm run preview:workspace` for synthetic visual review. The preview controls
now include Domain as well as theme, Phase and viewport. Direct scenarios:

- `/frame?domain=settlement&phase=order&theme=light&cards=1&status=active`:
  Nova remains in H2, with Concord Gate/Write and dark Horizon context.
- `/frame?domain=emergence&phase=order&theme=light&cards=1&status=active`:
  Vela in H3, with Canon Gate/Write, light context and a separate Concord opener.
- `/frame?domain=emergence&phase=distinguish&continuity=true&theme=dark&cards=1`:
  the boundary, provenance and Gate coincide; the Gate remains Concord while the
  initial Write is Activity.

These fixtures use synthetic data and make no production API calls. Screenshot
capture was attempted, but the Work Mode browser security policy blocked access
to the local preview. No screenshots or manual visual verification are claimed;
reviewers can use the reproducible scenarios above for that remaining check.
