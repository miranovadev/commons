# Homeostatic populations — epic Stage 2

This stage adds shared measurement machinery only. It does not connect that
machinery to participant options, action authorization, movement, Receipt scope,
Commit admissibility or recovery. Mediate retains its existing selected-Read
direction rule; Bias and Order retain their existing local behavior.

## Structural membership

`HomeostaticPopulationService::forPosition()` asks the pure
`HomologousPositionService` for propositions with the acting position's substrate,
creating-Horizon Domain, canonical producing Phase/register and committed depth.
Different Horizons and Commit Regions are allowed. The acting proposition is
included exactly once when valid. Expressive and committed tiers are separate;
committed Write positions must be canonical Order results.

Committed depth counts immutable Cycle-entry predecessor edges from Origin,
with Origin at depth zero. Expressive positions use their Cycle entry's committed
depth; a committed position uses its own committed depth. Supersession distance,
Horizon/Cycle sequence, Closure Depth, ledger order and time do not set it.
Complete ancestry must validate to the same Origin; missing snapshots, cycles,
disconnected roots, foreign substrates and invalid Horizon links fail closed.
Nearest shared ancestor/distance remain diagnostic output only.

The proposition's own Domain is authoritative. Two expressions can share a Domain
and depth even when one entry Committal was created in that Domain and the other
opened it from the opposite Domain. The general population includes both. Existing
Mediate discovery retains its prior entry-creating-Domain filter through the
default `sameEntryDomain` option until the separate Stage 3 selection change.

Membership is structural, not actionability. Superseded or exhausted members are
not silently removed from this definition. Operational consumers must apply their
own availability rules before exposing an option. Recognition grants no action.

## Independent measurements and aggregate

`HomeostaticDirectionService::forPosition()` consumes Correspondence, Chirality or
Closure values for those members. Current expressive projections are reused;
missing/obsolete projections are derived through the existing lineage calculators
in one shared dependency context. Committals supply frozen snapshot values.
No projection, retained direction, snapshot or participant state is written.

`ParticipationContext` has an explicit measurement-only opt-in for hydrating
unavailable additional members. Its default operational working set is unchanged.
The new service owns this context privately and never returns it as an input plan.
Hydrated Receipts remain calculation evidence, not an expanded Receipt catalogue.

Each distinct measured proposition contributes its signed value once with equal
weight. The arithmetic mean uses the existing seven-decimal measurement precision.
Magnitude matters: two values of +0.1 and one of −0.9 produce a negative aggregate.
Zero values contribute denominator mass; unavailable/null values do not. An empty
or entirely unmeasured population has no mean or direction. The result preserves
the per-member values, including nulls, so missing data stays explicit.

Direction uses `ObservableDirection::after()`. At aggregate zero, an explicitly
supplied prior **population** direction is retained. Without prior population
history the tie is unresolved (`null`); neither the acting member's sign nor the
enum's default supplies a new field direction. This stage adds no persisted field
history. Nonzero aggregates establish their own direction. This aggregate is not
a new measurement of any proposition and is never fed back into its calculators.

Closure here is the generic numeric measurement aggregation surface. Resolving
Depth/Cadence agreement for Order candidates and selecting operational members
belong to Stage 5; this stage does not equate a numeric Closure aggregate with
Commit admissibility or a Traverse grant.

## Validation boundary

Focused coverage checks cross-Horizon/Region membership, self-inclusion, committed
and expressive coordinates, Domain/Phase/register/depth/substrate isolation,
disconnected/missing/cyclic ancestry, positive/negative aggregation, neutral and
hysteretic behavior, frozen snapshots, observation-only fallback, and constant
query counts across one and eight foreign Horizons with missing projections.
The existing homology fixtures and assertions are retained through a shared test
fixture. Existing Mediate and operational suites remain regression gates.

Stage 3 and later action integration are deliberately not implemented here.
