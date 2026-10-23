# Stage 3B validation

Base: merged Stage 3A (`a336aa80dbfaf293823fe1309cd588d4c2dd64a9`).

Previously, HTTP acquisition offered Hyperlink's descriptive observation only.
Load File now offers explicit Download intent through the same Persist Instrument
lifecycle, preserving exact bytes and an ordinary non-loadable Receipt. Existing
Hyperlink identity, reference extraction and seven-day version behavior are retained.

Focused acquisition/lifecycle/context, frontend and full CI results are being
collected for this PR. The completed report will compare actual failure causes
against the Stage 3A baseline, including NaturalBoundary and FreshBootstrap.

The migration extends existing enum/check constraints and preserves SQLite partial
allowance indexes. No production/shared database records are modified.
