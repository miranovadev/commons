# Substrate presentation

System administrators can set a Substrate's presentation in the existing **Settings** navigation entry, or when creating it. Settings are stored under `configuration.presentation`:

```json
{
  "primary_color": "indigo",
  "positive": { "label": "Support", "color": "violet" },
  "negative": { "label": "Refute", "color": "blue" }
}
```

Missing settings resolve to these defaults, including for existing Substrates. Colours accept the standard Mantine palette names: dark, gray, red, pink, grape, violet, indigo, blue, cyan, teal, green, lime, yellow and orange. Labels are non-empty strings of at most 40 characters.

`POST /api/substrates` accepts these settings with the existing creation fields. `PATCH /api/substrates/{slug}` accepts a partial `configuration.presentation`; it merges under a Substrate lock and preserves other configuration. Editing is restricted to system administrators. Public readers receive the resolved presentation through the normal Substrate resource, including `can_update` for the current viewer.

The primary palette supplies the existing shade indices throughout the Substrate UI. Emergence keeps its light background (shade 0) and Settlement its dark background (shade 9). Register colours and participant identity colours remain independent. Labels, tooltips, attestation controls and counts use the Substrate's positive/negative presentation. Layout and direction-dependent button placement are unchanged.

## Canonical directions and migration

Attestation direction values are now `positive` and `negative` in storage, action payloads, Origin input, personal attestations and aggregate count keys. `attestation_summary.positive_direction` replaces `support_direction` in the public summary; it identifies the canonical measurement direction assigned to a positive Attestation in that fixed Commit Region. Signed summary counts still describe measurement directions, which may differ from the chosen Attestation side. The underlying legacy `commit_region_polarities.support_direction` storage column is retained for compatibility with the earlier historical backfill migration.

The migration maps existing `support` to `positive` and `refute` to `negative`, including superseded history. It preserves IDs, timestamps, rationale, receipts, ledger references and fixed region frames. Its rollback reverses the mapping. Changing display labels or colours never alters evidence, polarity, measurement values, or revision/traversal eligibility.

The action schema accepts only canonical values. Per-Substrate display names are exposed as `schema.x-commons.attestation_labels`; they are attached after shared schema caching and treated as presentation data by the participant prompt. Schema cache version 19 prevents reuse of the old direction enum.

Deploy the migration and application/assets together, restart long-running workers, and reload existing browser tabs so all writers use the new action contract. A rolling deployment must not leave old workers writing `support`/`refute` after the migration.
