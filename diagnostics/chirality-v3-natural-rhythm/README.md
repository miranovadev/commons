# Fixed-population natural rhythm diagnostic

Diagnostic only. Base: c83a48b441f08177f60c7e85968ce0f703af11dd.

The candidate measurement service is copied from the existing formation diagnostic;
only namespace and trace fields change. Origin calibration, signed Orientation,
formation transform, immature numeric-zero/maturity distinction, later comparative
Cadence, and zero-mean stop remain exactly as found. No gate overlays are loaded.
Production Correspondence, Closure, attenuation, projection, and operation services
remain authoritative.

Two separate panels use fixed populations of one and three authors, all created and
entered before the first ordinary Attestation. No later participants are created.
Fair cyclic scheduling offers every actor genuine work. Available action selection
preserves the original Write/Traverse priority. At an Attestation opportunity,
it permits that act once the requested spacing is reached; otherwise it first
tries available ordinary work across the fixed actors. If all ready actors require Attestation, it records the spacing
shortfall and performs the lawful act. It never invents a Rest or Cycle entry.

Requested policies (not injected interval values): noisy 1,5,2,6 repeating;
regularising 5,4,3,3 then 3; regular 2; regular 2 for three gaps then disrupted 6;
sparse regular 5; boundary probes regular 1 and regular 3. Targets apply to global
ordinary-act scheduling; actual per-DomainLineage represented intervals are always
reported separately and are the only intervals used for measurement.

Each case stops on first Emergence, three ordinary Commits, 100 actions, no lawful
continuation, or unresolved zero-mean CV. These are bounded scheduling experiments,
not an exhaustive proof over all lawful histories or populations. Requested shapes
that cannot be realised are findings, not reasons to add population.

Topology and Phase progression may diverge if different lawful action ordering
changes available operations. Such cases cannot support a timing-only causal claim
without matching their traces. Origin polarity is fixed Support throughout.

Run in a disposable testing database:

```
vendor/bin/phpunit --bootstrap diagnostics/chirality-v3-natural-rhythm/bootstrap.php diagnostics/chirality-v3-natural-rhythm/NaturalRhythmTest.php
php diagnostics/chirality-v3-natural-rhythm/zero-mean.php
python3 diagnostics/chirality-v3-natural-rhythm/summarize.py
```

Tests reuse the repository's deterministic external hyperlink evidence verifier.
Measurement values, topology, participants' Cycle entries, and transition outcomes
are not mocked. Evidence URLs are test inputs, not network observations.

The final controlled run uses a 100-action bound per case. Earlier scheduling
probes that promoted Attestation above Write are exploratory and are not pooled
with the final matrix, because early stance replacement changes the control.
