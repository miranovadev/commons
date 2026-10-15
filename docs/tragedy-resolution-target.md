# Tragedy, Resolution and recursive Exhaustion

> **Approved Stage 4 contract.** Replaces the first/later fresh-birth predicate.
> Deployment uses a fresh database; no historical proof conversion or compatibility
> layer is required. The [ten Core Invariants](core-invariants.md) always govern.

## Identity and scope

A Commit Region is owned by the immutable entry Committal of each expressive
Proposition's creating Cycle. Shared Region/Horizon membership is **not**
measurement ancestry or permission to move. Genuine causal Supersessions
define lineage continuation, including lawful multi-parent joins. Participants
may author or traverse different branches governed by the same Committal.

Each Region is in one of three operational states:

1. **Exploration:** ordinary action permissions.
2. **Resolution:** the first regional Tragedy has occurred; no further
   `proposition.variant` or `proposition.revise` may be written in that
   Region. Existing lawful continuations remain available.
3. **Exhausted:** all remaining lawful continuations have terminated at the
   authoritative locked evaluation; the governing Committal is no longer an
   entry or traversal destination.

Resolution has no minimum duration. A one-lineage Region may transition from
Exploration through Resolution to Exhausted in the same contribution transaction.

## Boundary and direct encounter

Charge is `1 - abs(Tension)` at the resolved published precision. A
Cycle-entry update can passively move existing expressive Charge to zero.
That **does not** establish Tragedy. Mere occupancy, read-only observation,
projection refresh, and Rest do not establish Tragedy either.

A **qualifying direct expressive Write encounter** is an authorised
contribution directed at a particular expressive Proposition (or a newly
authored expressive result), with **resulting** resolved Charge zero. The
action need not lower Charge from positive to zero: directly encountering an
already-zero target counts. The qualifying set includes expressive birth,
Attestation, Instrument and lawful expressive Traverse, subject to the
precise phase/action schema. These are action-level witnesses, not a claim
that all these operations create new Propositions. The target is the
expressive Proposition directly engaged, never every indirectly affected
Proposition. Rest and passive Cycle entry are excluded.

An Attestation that raises the target's resulting Charge above zero does
not qualify. A hundred further actions on an already witnessed terminal
continuation do not create new regional Tragedies or exhaustion witnesses.

The first qualifying encounter in a Region atomically:

1. Records the **unique regional Tragedy** and its immutable encounter
   provenance (participant, action, target, resolved field, field version).
2. Creates the unique owning-region Tragedy Receipt.
3. Terminates the **encountered expressive continuation**, preserving history.
4. Changes the Region to Resolution; rejects new Variant/Revise actions.
5. Evaluates all remaining lawful continuations under the same substrate
   contribution lock. If none remain, records Region Exhaustion immediately.

Later qualifying zero-Charge encounters in Resolution terminate their
respective continuations but do **not** create another regional Tragedy
or another Tragedy Receipt. Termination persists even if a later field
observation would become positive. This is historical authority, not
numeric Charge hysteresis.

## What continues in Resolution

Only `proposition.variant` (Phases 1, 2, 4) and
`proposition.revise` (Phases 2, 3, 4) become unavailable due to the
regional state. Reclassify, Reconcile, Instrument, Attestation, Traverse
and Commit retain their ordinary scope, evidence and admissibility rules
**on non-terminal continuations**. Their availability is never created
merely by a favourable Charge or by sharing a Region.

The Tragedy Receipt retains its narrow purpose: one eligible foreign
homologous Bias Reclassification from its owning unresolved Region.
It is not ordinary evidence, an Instrument Receipt, Reinforcement, a
generic movement edge, or permission to use an exhausted Region.
It may remain useful to an existing continuing branch during Resolution.
Consumption does not itself establish Exhaustion.

A successful Commit establishes a genuine descendant Committal/basin
continuation. It is **not** a failed lineage and does not, by itself,
close the parent Region. If that descendant basin later exhausts, its
continuation can become terminal in the parent; a live sibling keeps the
parent in Resolution. In a multi-parent history, exhaustion of one
descendant path cannot close another viable causal continuation.

## Authoritative atomic exhaustion evaluation

At a qualifying terminal Write, under the same substrate lock that
serializes field changes and contribution validation, evaluate the
remaining **actual lawful continuation options** in the owning Region,
including descendant committed continuations. Exclude already terminal
paths and apply the post-Tragedy Variant/Revise restriction.

- If any continuation remains available, keep the Region in Resolution.
- If none remains, persist its unique Exhaustion proof immediately.
- Do not add a second-Tragedy threshold, grace period, predictive
  proof of future possibility, or numerical Charge gate on unrelated paths.
- Do not treat a successful Commit as failure merely because its source
  is consumed.
- An exhausted Region cannot be reopened by a later Cycle or
  Attestation. Historical nodes and frozen Committals remain readable.

The proof should identify the governing Committal, creating Horizon,
original Tragedy and Receipt, terminating contribution or descendant
exhaustion event, evaluated field/structural version, remaining
continuation outcome and exhaustion time. This is a **semantic proof
shape**, not an assertion that the deployed schema already has these
columns. Previous experimental databases are retained separately as versioned dumps;
this installation does not import or reinterpret their proofs. All Stage 4
event evidence is immutable.

## Rest and recovery

A participant already positioned in an Exhausted Region has **Rest
only**; ordinary Write and Traverse options are unavailable. Rest itself
does not create Tragedy or Exhaustion. Recovery walks the participant's
recorded backward causal arrival path, stopping at the nearest eligible
non-exhausted ancestral Committal; an exhausted Committal is skipped.
Crossing a Horizon opening boundary follows its existing authority
contract. No sibling, unused Reconcile parent, arbitrary opener or
high-Potential node is an invented recovery destination.

## Worked structural examples

**Immediate exhaustion:** C1 has only P1. A direct zero-Charge Write
encounters P1. P1 becomes terminal; C1 enters Resolution and, with no
other continuation, Exhausted in the same transaction.

**Sibling survival:** C1 contains P1 (terminal) and P2 (viable).
C1 stays in Resolution even if another terminal encounter occurs
elsewhere. P2 may lawfully continue to a Commit.

**Descendant recursion:** C1 contains terminal P1, committed
P2 → C2, and viable sibling P3. When C2 exhausts, P2's route closes;
C1 stays in Resolution while P3 continues. Only P3's eventual
termination exhausts C1. The traversal/recovery path remains causal.

**Passive boundary:** a Cycle changes P4 Charge to zero. No Tragedy.
A lawful direct Attestation/Instrument/Traverse that encounters P4
still at zero qualifies; Rest does not.

## Unchanged contracts

No change to the ten invariants, causal ownership, measurement formulas,
Origin grounding, ordinary Receipt semantics, direction hysteresis,
Commit preservation, inverse-square attenuation or the existing
backward recovery path. Correspondence C1, Chirality vNext, prospective Closure and attenuation A
remain as implemented in Stages 1–3. The separate Rest crossover calculation
is outside Stage 4.
