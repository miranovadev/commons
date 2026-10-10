# Phase Profile C1: proposed structural observation

> **Preferred diagnostic target; not deployed.** The current implementation
> uses [Cardinal Position](correspondence-v2-cardinal-semantics.md).
> This proposal retains Shape Topology, replaces only the second
> Correspondence component, and requires a separate implementation review.
> It does not change causal topology, participant movement or Commit ownership.

## Measurement frame

`Correspondence_local = (ShapeTopology + PhaseProfile) / 2`.

The seven Phase positions are Distinguish, Persist, Constrain,
Disperse, Mediate, Bias and Order. Phase Profile compares selected
causal-lineage development against the completed preceding complementary
Domain, using only genuine recorded observations. **A Horizon, Domain,
Region or homologous population is not a measurement lineage.**

Each expressive Proposition records the current Cycle-entry count for
its **own immutable governing Committal** at birth. The clock counts
genuine entries into that basin, including other participants' Cycles
that created no Proposition in this measured ancestry. This does not
select their separate expressive Propositions.

## C1 interval contract

1. The first expressive Proposition on the selected lineage records its
   Cycle coordinate, but **no interval** (`null`).
2. A subsequent expressive Proposition measures the difference between
   its birth coordinate and the **selected preceding Phase's** birth
   coordinate in the same lawful causal lineage.
3. A same-Phase Revision creates a new birth coordinate but keeps the
   relevant preceding-Phase observation as its interval reference.
   It does **not** subtract the previous same-Phase Revision's stamp.
4. A genuine interval of zero is valid when both stamps coincide.
   `null` is never inserted as an observed zero.
5. Intervals use the selected preceding-Phase causal observation **within
   the measured Proposition's governing-basin coordinate system**. Selection
   is operation-aware, as specified below; shared Region membership never
   imports siblings.
6. Phase 7 is the prospective interval from the final expressive
   observation to the proposed Commit boundary. It informs prospective
   Historical Phase Profile / Closure, **not** the already-born
   Phase-6 Correspondence. Commit itself preserves resolved measurements.

For example, selected P1 at Cycle 10 (Phase 1), P2 at 11 (Phase 2),
and Phase-2 revisions P2' at 12 and P2'' at 13 give Phase-2
intervals 1, 2, 3 respectively, all relative to P1 at 10.
The P1 interval is `null`. Other lineages can advance the shared
basin clock without becoming observations.

## Causal ancestry and selected temporal references

Genuine causal ancestry, selected temporal reference, governing Committal
ownership and governing-basin Cycle clock are distinct. A temporal reference
must be a genuine causal observation in the measured Proposition's own basin.
An operational Gate without a genuine causal relationship is not measurement
ancestry. No cross-basin coordinate subtraction is defined.

| Operation | Temporal reference |
| --- | --- |
| Ordinary expressive progression | Selected preceding-Phase causal observation |
| Same-Phase Revision | Retain the selected preceding-Phase observation, not the previous Revision |
| Multi-parent Reconcile | Acting local Gate |
| Foreign-source Bias without eligible local causal predecessor | Unavailable (`null`) |
| Subsequent lawful local continuation of an imported Proposition | Eligible preceding-Phase observation in the local basin |

### Multi-parent Reconcile

A Reconcile uses its acting Phase's local Gate as its preceding-Phase temporal
reference. The secondary operand is not an interval reference. Do not average
parent intervals, choose the newest parent or combine Cycle coordinates.

For example, Phase-4 P1 in basin A has coordinate 12 and is the secondary
operand; Phase-4 P2 in basin B has coordinate 10 and is the acting Gate.
Their Phase-5 result P3 belongs to basin B and is born at coordinate 15.
P3's interval is **15 - 10 = 5**, referencing P2.

Both real Reconcile Supersessions, P1 → P3 and P2 → P3, remain intact.
P1 remains genuine ancestry for measurements selecting all-parent ancestry,
including Shape Topology. That does not make P1 P3's temporal reference.
Reversing the acting Gate changes temporal selection and the result's creating
context accordingly; it does not erase either causal parent.

### Foreign-source Bias and independently governed clocks

An expressive result remains owned by its creating Cycle's entry Committal,
even when its genuine causal source is foreign. It records that local basin's
birth coordinate. If no eligible preceding-Phase observation exists within
that basin on its selected causal lineage, its interval is **`null`**.

For example, foreign source P1 in basin A has coordinate 27. The acting local
Gate P2 in basin B has coordinate 10. A Bias Reclassification P3, owned by
basin B, is born at coordinate 15 with the sole genuine parent P1.
P3 records coordinate **15** and interval **`null`**. P2 is operational context,
not a causal parent; do not add a P2 → P3 Supersession or use P2 as a temporal
substitute. Do not subtract 27 from 15, import basin A's Cycle population,
substitute zero, or treat the Tragedy Receipt as an observation. That Receipt
retains only its existing narrowly scoped Bias authority.

### Missing reference and subsequent local development

An unavailable interval does not invalidate the birth coordinate. If subsequent
lawful local development selects P3 at coordinate 15 as its preceding-Phase
reference and is born at coordinate 19, that continuation has interval **4**.
This example requires the operation's existing Phase/causal eligibility; it
creates no new expressive operation after Bias. The Phase-7 Commit boundary
can likewise use the eligible final expressive observation diagnostically.
There is no special maturity threshold and no invented first interval.

A missing historical stamp remains unavailable; it must not be reconstructed
from today's Cycle count. Missing history, absence of an eligible predecessor
and genuine zero spacing must remain distinguishable in diagnostics.

### Atomic observation ordering

Birth coordinates are immutable event-born facts. A selected reference must
come from the complete already-authorised operation context, or be resolved
after genuine causal relationships are recorded within that same atomic
transaction. Proposition ledger recording can precede Supersession recording;
incomplete edges, Horizon recency and Region recency are not reference selectors.
Existing creating-Cycle ownership remains authoritative without a duplicate
ownership field on the observation.

## Maturity and neutral slots

Phase-local averages include **genuine observed intervals only**.
An unavailable first interval does not increase the denominator.
A single genuine interval is sufficient for a Phase-local mean:
unlike Cadence's variability/CV, the mean is mathematically defined
from one sample. No extra two-interval maturity threshold is added.

For a cross-Domain Phase comparison, if either required Phase mean
is unavailable, that Phase comparison contributes **numeric zero**.
The full Phase Profile retains **all seven fixed slots** in its mean:

`PhaseProfile = (s1 + s2 + s3 + s4 + s5 + s6 + s7) / 7`.

This zero is a *neutral comparison contribution*, not an observed
interval of zero or an assertion of balance. A genuine observed
zero-versus-zero comparison follows the defined similarity function
and is distinct from unavailable comparison.

Origin's constitutional reference profile
`[0, 0, 0, 0, 0, 0, 1]` is not seven invented empirical intervals.
Keep the Origin bootstrap explicitly distinguishable from observed
expressive spacing. Origin's raw Correspondence remains +1;
ordinary comparison begins in the first developed Domain.

The exact interval-to-similarity kernel and retained component
direction/hysteresis must be verified against the accepted diagnostic
before implementation. The C1 diagnostic uses the bounded
comparison family and fixed neutral-slot aggregation; do not silently
substitute Cardinal's row-position formula or invent a missing-data
penalty.

## Historical and causal guarantees

Birth coordinates and causal provenance persist. Correspondence is
resolved at birth and is not recalculated merely because later Cycles
enter the basin or a same-Phase Revision is authored elsewhere.
Only the selected local Correspondence is used for the current head;
any historical attenuation is applied after raw measurement under
the separately chosen field formulation.

B1 (first-Cycle baseline), B2 (Committal-zero baseline), and C2
(two-interval maturity) remain **diagnostic controls**, not target
rules. Do not discard their evidence or present them as approved canon.

## Stage 1 diagnostic implementation boundary

Stage 1 records observations and exposes `commons:phase-profile-inspect
PROPOSITION_ID`; it does not replace production Cardinal Position or change any
production observable, retained direction, actionability or Commit preservation.
The bounded diagnostic kernel is `1 - 2 * abs(a - b) / max(a, b)` for available
nonnegative means, with zero/zero = +1, bounded to [-1,+1]. Either unavailable
mean contributes 0. The independently correct comparison family from historical
PR #249 is retained; its Horizon population, newest-Proposition predecessor and
invented first interval are not.

Implementation responsibilities:

| Requirement | Responsibility |
| --- | --- |
| Basin clock and event ordering | New Proposition ledger birth under the existing Substrate lock counts entries with the exact creating Cycle's entry Committal/Horizon and entry marker before the birth sequence |
| Operation-specific temporal selection | Authorized dispatcher source: unary source, revised Proposition, acting Reconcile Gate, or selected Commit source |
| Same-Phase Revision | Reuse the source's durable preceding-Phase reference; never reconstruct a missing historical reference |
| Immutable evidence | `phase_profile_observations` stores coordinate, preceding-Phase Proposition ID, interval and unavailability reason; PostgreSQL/SQLite update guards; no historical backfill |
| Ownership | Derived through the creating Phase/Cycle; no duplicated basin or Horizon columns |
| Profile population | The inspected head's genuine all-parent ancestry within its creating Horizon, deduplicated; every node supplies only its own operation-selected interval |
| Completed comparison reference | Genuine ancestry ending at the Committal that opened the current Horizon; later siblings do not extend that reference |
| Missing history | Explicit reason and `history_complete=false`; missing intervals supply no sample, while genuine recorded intervals still form the Phase mean with explicit sample counts |
| C1 unavailable interval | No sample for first expressive birth or no eligible local predecessor; a recorded zero is a genuine sample |
| Phase 7 | Read-only preview from a Bias head, plus durable actual Commit-boundary observation; neither rewrites the source's profile nor recalculates Closure |

All genuine selected parent branches remain profile ancestry; selecting a local
Gate for one Reconcile interval does not erase the secondary parent's own
historical observations when that parent belongs to the selected Domain lineage.
Source-less direct ledger callers retain an explicit `missing_source_context`
interval rather than guessing a causal source. Re-recording a ledger entry
cannot manufacture a missing birth record. Diagnostics are read-only and may
observe concurrent committed activity; they are not an admissibility preview.
