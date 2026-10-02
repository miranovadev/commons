# Chirality v3 diagnostic — stop at the Origin-reference boundary

Source: `miranovadev/commons` main `ea51bb8a4b0321050fdea82a620785605436f23c`.
Its source tree matches the reviewed Closure v2 head `0128dedb2da6abeb9bdca446f11735b24386bf97`.
Input: `commons-canonical-closure-202610030548(1).sql`.

## Finding and stop

The proposed interval-CV Chirality is not yet a total observable at bootstrap.
Origin's one creator Attestation supplies **zero intervals**, so neither its mean
nor its CV exists. First-Settlement Cadence, combined/attenuated candidate
Chirality, Tension, and candidate Bias admissibility therefore cannot yet be
assigned numeric values. Primitive directions do not supply a missing numeric
value. Hysteresis applies after a value is defined, including zero; it does not
define the CV of an empty population.

No production code, existing fixtures, existing tests, gates, Domain transitions,
or recovery policy were changed. No implementation PR was opened. The diagnostic
stops before the first Bias Write. It does not manufacture ten candidate Commits.
**There is no finite candidate crossover witness established by this diagnostic.**
This is an under-specified bootstrap, not a proof that every possible clarified
version of v3 is mathematically unreachable.

## Exact direction issue

The preserved primitives are Orientation Negative, Cadence Positive, combined
Chirality Positive. Current v2 nevertheless measures Origin as Orientation 0,
Cadence -1 and Chirality -0.5: its population-absence formula establishes a
**Negative Cadence history**, overriding the positive primitive.

Two different rules must not be conflated:

* If unavailable v3 Cadence were defined as numeric zero with no prior numeric
  Cadence observation, normal hysteresis retains Positive. In the first developed
  Settlement Domain, previous lean is +1, so Orientation is in [-1,0]. Its zero
  retains the Negative primitive. Orientation Negative / Cadence Positive blocks
  Bias for every such state, including perfectly aligned evidence. Repeating
  Settlement Commits cannot solve a gate that blocks the first Commit path.
* If current v2 Origin's numeric -1 were explicitly retained as a v3 constitutional
  bootstrap, a subsequent zero could retain Negative and agree with Orientation.
  That is a separate authorized semantic choice, not a consequence of empty CV
  history or the primitive Positive default. It has **not** been applied here.

Keeping Cadence unavailable also fails the existing gate's requirement for two
available, agreeing directions. Changing combined Chirality's Positive default
would not resolve this component-direction issue.

## Predetermined bounded prefixes

Eight isolated fresh substrates used genuine `ParticipationService` operations,
production lineage/evidence/calculation services, and canonical Correspondence
and Closure. No measurement doubles or candidate values were injected. The
existing test transport verifier supplies deterministic hyperlink evidence.
These are **current-production prefixes with candidate observations**, not claims
of complete v3 trajectories: the missing bootstrap prevents an executable v3
field. The runner stops at the first Bias Gate, before any ordinary Commit.

The action preference is fixed in the runner; ordinary Rest remains available.
The evidence signs below are relative to Origin's selected polarity. Both physical
Origin labels were run. One idle participant enters before ordinary Attestation
acts 2 and 4, identically in every case. The schedule was fixed before running;
no population or topology was tuned against the resulting field.

| Case | Planned effective signs | First Bias current lean | v2 product O | v3 displacement O | v2 local Chirality | v2 attenuated Chirality |
|---|---|---:|---:|---:|---:|---:|
| A aligned | + + + + + | 1 | 1 | 0 | 0.5 | 0.3763272 |
| B away | + + − − − | 0 | 0 | −0.5 | 0 | −0.2592254 |
| C mixed | + − + − + | 0 | 0 | −0.5 | 0 | −0.0008123 |
| D away/back | + − − + + | −1 | −1 | −1 | −0.5 | −0.5330142 |

All eight reached the first Bias Gate after 32 ordinary actions (including Rest).
All four Support/Refute pairs have exactly equivalent numeric observations,
actions, and stop states after removing generated Gate IDs. Five ordinary
Attestations exist at that point. There are zero ordinary Commits in each run.

At every first Bias Gate:

* current intervals = [2,1,1,4], mean = 2, population sigma = sqrt(1.5), CV = 0.6123724357;
* previous Origin intervals = [], previous CV unavailable;
* current v2 acts/Cycles Cadence = 0 (5/10 versus 1/1), with retained Negative direction;
* Correspondence = -0.5282627; raw Shape Topology = -0.5411685; raw Cardinal Position = -0.6666667;
* Closure = 0, Committed Cardinal Position = 0, Committal Cadence = 0, Closure admissible;
* candidate Cadence, local/attenuated Chirality, Tension, required Correspondence,
  Bias admissibility and resulting Domain remain unavailable.

Do not read authored sign D's final '+' as the lean of every proposition. The last
Attestation and the Bias Gate concern different causal positions. Production
active/effective evidence and hard supersessions determine each lean. For D,
observations immediately after the five Attestations have current leans
[1,-1,-1,-1/3,0], and displacement Orientations [0,-1,-1,-2/3,-1/2]. The separate
Bias Gate has lean -1. This preserves the existing evidence semantics rather than
averaging the input labels ourselves. Returning toward +1 moves displacement
back toward zero; it cannot become positive while the reference remains +1.

`traces.csv` contains all intermediate observations, including current component
directions and the independently attenuated Orientation contribution. Blank v3
cells mean unavailable, not numeric zero. `summary.json` contains the terminal
observations and explicit mathematical edge cases. No Commit preview or actual
Commit was reached under a defined candidate field, so there are no invented
Commit-ordinal rows or resulting Domains.

## Reachability bounds

Write C for attenuated Correspondence, X for attenuated Chirality, and K for
Closure. Tension is (C+X+K)/3, with the existing final rounding. A positive
transition requires C > -(X+K); equality is not a positive transition.

While the preceding complementary reference is the Origin-only frame,
O = (lean - 1)/2 lies in [-1,0]. If all local candidate Cadences were defined in
[-1,1], each current-Domain local Chirality would lie in [-1,0.5]. At the root,
Orientation is 0; the same bounded-Cadence assumption gives at most 0.5.
The existing positive normalized attenuation weights preserve the upper bound
X <= 0.5. For the measured Bias Gates, the tighter upper bounds for Tension,
using the measured weighted Orientation contributions, are:

| Case | Weighted Orientation contribution to X | Maximum possible Tension at this Gate |
|---|---:|---:|
| A | 0 | -0.0094209 |
| B | -0.3177763 | -0.1153463 |
| C | -0.1885698 | -0.0722775 |
| D | -0.4546707 | -0.1609778 |

These are bounds at the measured Gate, **not** prospective Commit measurements
or a universal impossibility theorem for all future geometries.

Closure supplies a second useful bound. If ordinary Settlement Commits could be
made before the first crossover, then the nth prospective Settlement Committal
would have E depth 1 and S depth n. The first is the existing bootstrap K=0.
For n>=2, Depth = -1+2/n. With only one Emergence Cadence sample (Origin),
comparative Cadence and Lateral remain immature and numerically zero, so
K = Depth/4 = -1/4+1/(2n). Thus K is 0 at n=2, -1/12 at n=3, and -0.2 at n=10.
Additional Settlement Commits do not make Closure increasingly positive.

Under the **unadopted** missing-Cadence=0 hypothesis (and a nonpositive root
Chirality), X<=0, requiring positive C at n=1 or 2 and C > -X+1/4-1/(2n) later.
But the Positive retained Cadence direction already blocks Bias under the pure
primitive interpretation. Without choosing the missing-CV rule, a finite
candidate Commit trajectory and its reachability cannot be decided. No universal
Correspondence bound proving impossibility is claimed.

## Zero-mean and maturity distinctions

| Interval population | Mean | Population sigma | CV |
|---|---:|---:|---|
| [] | unavailable | unavailable | unavailable: no observations |
| [0,0] | 0 | 0 | undefined 0/0: observed entry-free burst |
| [2,2] | 2 | 0 | 0: observed constant positive spacing |
| [0,1] | 0.5 | 0.5 | 1 |

Nonnegative intervals have mean zero precisely when all are zero. Assigning that
0/0 ratio a value would be a new rule. It is distinct from comparing two valid
zero CVs, for which Closure's existing similarity convention is +1. A single
positive interval also has mathematical CV=0; the minimum interval-history count
for calling a Chirality comparison mature has not been specified.

More actual Attestations on Origin do not automatically resolve this: under the
fixed Origin-to-Settlement topology, later Settlement Cycle entries are excluded
from the bootstrap Emergence lineage's represented Cycle population. An
Origin-only reference could gain acts while its inter-act entry intervals remain
all zero. Additional Settlement Commits do not create another complementary
reference until an actual Domain crossover occurs.

## Persistence/order audit

The dump contains 2,722 Attestations, all with contribution-ledger entries, and
1,075 Cycles, none with a contribution-ledger entry. Both newly cooked substrates
have exactly one Origin Attestation (466/617 total acts, 225/258 Cycles).
Attestation insertion and ledger recorded timestamps differ in 194 rows (25/44
in the newest two substrates); they cannot be assumed interchangeable.

The audit found no other Cycle in the target proposition's Horizon whose entry
timestamp overlaps the inclusive act-created-to-ledger-recorded window. Thus it
found no concrete cross-Cycle tie in this dump. It does **not** establish a durable
joint causal ordering primitive for future concurrent events. Cycle timestamps
have second precision; contribution ledger sequences exclude Cycles. Per-person
Cycle sequence and ULID generation order are not a shared commit-order contract.
A Cycle entry and an unrelated Attestation in the same second can leave the
placement of that entry on one side of the act unresolved. No ordering or graph
semantics were changed.

The fresh single-process runner captures Cycle-created and Attestation-ledger
callbacks in an observational in-memory total order, allowing exact interval
counts there. It records every individual act, including replacements, and uses
the production lineage's represented entry basins and Horizon filter. It does not
collapse acts to active Cycles or infer their order from owning Cycles. This
instrumentation does not repair missing historical ordering in persisted data.

## Smallest clarification needed

Specify **the numeric Cadence observation and its direction/history at Origin
and while the complementary Origin reference has no empirical CV**. In particular,
is v2 Origin's -1 to remain an explicit constitutional numeric observation under
v3, or is it to become unavailable? If unavailable is mapped to zero with the
Positive primitive, the current Bias gate blocks the first Settlement Write.
This needs resolution before evaluating a ten-Commit witness.

Separately, an implementation needs an explicit all-zero-interval rule, an
interval-count maturity threshold, and a durable ordering contract for Cycle
entries relative to acts. These remain questions, not implemented defaults.

## Reproduction and validation

From the repository root with PHP 8.5 and dependencies installed:

```sh
DB_CONNECTION=sqlite DB_DATABASE=:memory: APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php -d memory_limit=1G vendor/bin/phpunit diagnostics/chirality-v3/BootstrapDiagnosticTest.php
python3 diagnostics/chirality-v3/summarize.py
python3 diagnostics/chirality-v3/audit_dump.py /path/to/dump.sql
```

The diagnostic runner passed 8 cases / 16 assertions. The summary check verified
all four complete polarity pairs. Only files under `diagnostics/chirality-v3/`
were added. Existing suites and production behavior were not modified.
