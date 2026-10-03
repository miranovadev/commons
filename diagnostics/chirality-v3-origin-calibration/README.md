# Chirality v3 Origin calibration diagnostic

Diagnostic continuation of `diagnostic/chirality-v3-origin-reference`. Production source remains unchanged.

## Hypothesis

Separate the Origin creator choice into **polarity calibration / attestational direction** rather than an ordinary empirical +1 Attestation sample.

- Origin establishes which human-facing label is the substrate's positive attestational direction.
- Origin does not populate an ordinary developed-Domain empirical lean.
- The first developed Settlement Domain therefore compares its empirical lean against a neutral bootstrap empirical baseline 0.
- Candidate signed Orientation is `(current_lean - previous_empirical_lean) / 2`, giving first-Settlement Orientation a true range `[-0.5,+0.5]`.
- For this bound check only, candidate Attestation Cadence is allowed its legal numeric range `[-1,+1]`; no bootstrap Cadence formula is chosen.
- The proposed Origin numeric seed used for attenuation is `chirality = -0.5` (Orientation contributes neutral 0; constitutional Attestation Cadence contributes -1). This is deliberately a conservative negative ancestry contribution.
- Shared composite-direction hysteresis is a separate directional hypothesis and is not used to add numeric field mass.

## Exact recorded C1 morphology

The first actual Commit morphology exported by the previous maturity diagnostic is reused unchanged.

Production reverse-BFS distances from the C1 head are:

| Distance | Positions |
|---:|---:|
| 0 | 1 |
| 1 | 1 |
| 2 | 1 |
| 3 | 2 |
| 4 | 2 |
| 5 | 2 |
| 6 | 1 |
| 7 | 1 Origin |

Production attenuation is `1/(1+d)^2`.

The recorded C1 field inputs are:

- Correspondence: `-0.5276331`
- Closure: `0`

Therefore positive Tension requires attenuated Chirality strictly greater than `+0.5276331`.

## Reachability ceiling

Under the calibration interpretation, an ordinary first-Settlement position can reach:

```
Orientation = +0.5
Cadence     = +1.0
local χ     = (+0.5 + +1.0) / 2 = +0.75
```

Using `+0.75` for every ordinary position and the conservative Origin seed `-0.5`, the exact production attenuation geometry gives:

```
maximum attenuated χ = +0.7382178609
maximum C1 Tension   = (+0.7382178609 - 0.5276331) / 3
                     = +0.0701949203
```

So the previous impossibility result no longer applies. The **actual recorded C1 morphology has sufficient Chirality headroom to enter Emergence**.

This does not establish that a canonical first-Settlement Cadence process naturally reaches that ceiling. It establishes that the door exists before choosing such a process.

## Cadence target on the recorded morphology

As a useful sensitivity check, suppose every ordinary position is maximally aligned in Orientation (`+0.5`) and shares a common candidate Cadence `k`, while Origin remains `χ=-0.5`.

Solving the exact attenuated C1 field for zero Tension gives:

```
k = 0.5748228815
```

Therefore C1 would enter Emergence for:

```
Cadence > +0.5748228815
```

under those deliberately optimistic/equal-position assumptions.

This is a diagnostic target, **not a proposed Cadence bootstrap rule**.

## What this establishes

1. Treating Origin's creator choice as polarity calibration rather than empirical lean restores first-Settlement Orientation freedom `[-0.5,+0.5]`.
2. The prior proof's `Chirality <= 0` premise is invalid under this interpretation.
3. Canonical Correspondence v2 and Closure v2 do not need to change merely to make first crossover numerically reachable.
4. Production attenuation does not erase the recovered positive degree of freedom.
5. A first-Settlement Attestation Cadence semantics capable of sufficiently positive values could produce a genuine crossover.
6. The next semantic question is therefore narrow: **what does Attestation Cadence mean while the first developed rhythm is forming, before a previous developed Domain rhythm exists?**

No production implementation or canonical Cadence bootstrap is proposed by this diagnostic.
