# Chirality v3 first-rhythm formation diagnostic

Diagnostic-only continuation of the Origin calibration and strict-Cadence analyses. Production application source is unchanged.

## Candidate semantics

Origin:
- creator choice establishes polarity/direction, not an ordinary empirical +1 lean sample;
- Orientation numeric contribution = 0;
- Attestation Cadence = constitutional -1;
- numeric Chirality = (0 + -1) / 2 = -0.5;
- Canonical Correspondence +1 and Closure -1 therefore give negative Tension and naturally open Settlement.

First developed Settlement Domain:
- previous empirical lean baseline = 0;
- Orientation = (current empirical lean - 0) / 2, hence [-0.5,+0.5];
- fewer than two usable Attestation intervals -> formation Cadence 0;
- once at least two intervals exist with nonzero mean:
  - CV = population standard deviation / mean;
  - formation Cadence = (1 - CV) / (1 + CV), bounded [-1,+1].

Later developed Domain comparisons retain the proposed mature semantics:
- signed-displacement Orientation between adjacent empirical Domain leans;
- comparative CV similarity between the two adjacent Domain Attestation rhythms.

No shared composite-direction hysteresis is used in this numeric reachability run.

## Fixed regular-rhythm protocol

The positive witness does not inject interval values.

It uses genuine ParticipationService operations. Before every ordinary Attestation after the first, the driver adds only enough genuine idle Cycle entries to make the occurrence spacing exactly five Cycle entries since the preceding Attestation.

The rule is fixed before execution and never reads Correspondence, Chirality, Closure, Tension, or the previously derived +0.575 C1 landmark.

Thus the first empirical rhythm is regular:

```
[5,5,...]
CV = 0
formation Cadence = +1
```

The ordinary action policy otherwise remains the existing deterministic diagnostic policy and prefers effective +1 evidence in the target Region polarity frame.

## Result

The full production-service trajectory crossed on the first ordinary Settlement Commit for both Origin polarity labels.

```
Correspondence       -0.5276331
Orientation          +0.5000000
Attestation Cadence  +1.0000000
attenuated Chirality +0.6925818
Closure               0.0000000
Tension              +0.0549830

Settlement -> Emergence
```

Support-Origin and Refute-Origin produced identical numeric results.

The diagnostic suite passed:

```
2 tests
40 assertions
```

The run used real PostgreSQL, ParticipationService actions, production Correspondence v2, production Closure v2, production inverse-square lineage attenuation, ordinary Bias/Order machinery and ordinary field projection. No ordinary Domain transition was forced.

## Contrast with the existing noisy first-Settlement trajectory

Replaying the formation function over the previously recorded first-Settlement interval histories gives approximately:

| Commit | CV | Formation Cadence |
|---:|---:|---:|
| C1 | 1.0770 | -0.0371 |
| C2 | 0.8355 | +0.0897 |
| C3 | 0.7689 | +0.1307 |

Those values are far below the regular-rhythm witness. The formation function therefore does not monotonically or inevitably drive the bootstrap toward Emergence.

## C1 sensitivity landmark

On the recorded C1 morphology, the earlier exact attenuation calculation found that with maximal first-Settlement Orientation +0.5, a common ordinary-position Cadence of roughly +0.5748229 is the zero-Tension landmark.

Under:

```
Cadence = (1 - CV) / (1 + CV)
```

that corresponds to approximately CV < 0.2699841 for the simplified common-Cadence sensitivity model.

| intervals | CV | formation Cadence |
|---|---:|---:|
| [2,2] | 0 | +1 |
| [2,3] | 0.2 | +0.6667 |
| [1,2] | 0.3333 | +0.5 |
| [0,1] | 1 | 0 |

This landmark is diagnostic, not a threshold encoded by the candidate semantics.

## What this establishes

1. The developmental first-rhythm formulation restores a genuine positive numeric degree of freedom without changing Correspondence v2 or Closure v2.
2. A coherent first empirical Attestation rhythm can naturally produce a finite Settlement -> Emergence crossover.
3. The witness survives production ancestry attenuation: local +0.75 becomes attenuated Chirality +0.6925818 and still crosses.
4. Origin Support/Refute polarity labels remain numerically equivalent after calibration.
5. A noisy first rhythm does not automatically cross merely because formation semantics exist.
6. The strict Closure-style -1 -> 0 -> comparative CV bootstrap remains impossible; the formation state is doing necessary semantic work, not cosmetic work.

## Still unresolved

- Whether (1-CV)/(1+CV) is the canonical first-rhythm formation transform rather than merely a successful candidate.
- The all-zero interval population ([0,0,...]) remains semantically unresolved because CV has zero mean.
- Production still needs a durable total ordering between Cycle entry and Attestation occurrence before individual-act intervals are replay-safe.
- Shared composite-direction hysteresis is a separate hypothesis and was deliberately not required for this numeric crossover witness.
- Mature post-crossover Chirality still needs a dedicated trajectory confirming the transition from formation Cadence to adjacent-Domain comparative CV semantics.

No production implementation is proposed by this diagnostic.
