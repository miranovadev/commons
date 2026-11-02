# Evidence failure resilience

## Admission and terminal observations

Citation takes the ULID of an existing Proposition or Attestation in the acting substrate. The service trims surrounding whitespace from valid IDs. Case-insensitive Crockford syntax and the ULID leading-digit bound apply. The human form, shared action schema and autonomous transport identify the subject. Syntax rejection precedes reservation. The resolver uses the same response for absent and foreign subjects. It permits same-substrate references outside the active working set. A global subject enum would expose unnecessary history.

A terminal failure records one ordinary `failure` Receipt. Its reference is the execution ID. Provenance includes the originating Gate, Phase, Horizon, Participant, acquisition kind and failure time. The summary gives the kind, reason, stage and classification. A missing or unsafe reason gets a bounded fallback. Instrument input metadata retains the submitted ULID, URL host, or query length and limit as applicable. Preparation retains its bounded selections and acknowledged delivery history. There is no source signature or Artifact body. Negative Hyperlink, Web Search and Download retrievals retain their prior invocation identity, duplicate suppression and replay eligibility. An expired or unexecuted reservation grants no replay. The creator loads the diagnostic and another Participant can encounter its summary through the Gate-causal catalogue.

The Substrate lock precedes the execution lock. Terminal status, failed outcome, timestamp, Receipt, ledger entry and creator load share a transaction. The unique execution foreign key and locked status check enforce one Receipt across completion, failure and recovery. A later writer respects the first terminal state. A Receipt insertion error rolls back the failure, permitting retry or bounded reservation recovery. Post-commit publication can be retried from the dirty Horizon. Historical failed executions with a null outcome stay readable without backfill. Historical Preparation failure Receipts retain their original type.

An unconsumed Failure Receipt can satisfy ordinary Reclassification evidence requirements in the authorized Region and Horizon. Commit grounding likewise checks for an ordinary observation. The independent judgments, source restrictions, Closure and structural checks still apply. Loading the diagnostic does not verify source content. Consumption is write-once and the causal catalogue retains the original observation. A failed reservation that previously permitted only Defer still permits only Defer. Recoverable reading or retrieval outcomes preserve their prior Variant eligibility.

## November 2 snapshot

The SQL export and independently decoded custom archive agree on all 218 Cycles, 1,169 Phases, 26 executions and 18 Receipts scoped to substrate `01m6bhtjmyh0ta9nv5hjtryzkm`. The SQL SHA-256 is `4a93b73eca3cae420a9be0fd4135593a3560a873000a8298a8bca2d75f787926`. The archive SHA-256 is `88da61dcb0d938316af235d00cec0e178e17250207beece89b2e4509d83b357f`.

All eight failed Citations reserved the same Persist Gate, `01m6bj2211e2x9yw77stahth7h`, with reference `https://miranova.dev/matrix`. Each recorded empty diagnostic metadata, the reason “Instrument reservation expired before a recorded observation.” and subsequent Defer. All 18 completed executions had Receipts.

| Participant | Cycle | Execution | Time to expiry |
| --- | ---: | --- | ---: |
| Luna | 1 | `01m6bj2e4wp94nmnbdzm9b8bk5` | 30m 55s |
| Vela | 1 | `01m6bj447tt8njrcdxf8fppg0h` | 30m 18s |
| Nova | 1 | `01m6bj9a0708b3vs1464gjc14x` | 30m 23s |
| Luna | 2 | `01m6bkvjgmj39wyjh49prqmbhh` | 30m 11s |
| Vela | 2 | `01m6bkw5my2x1f9bcqjr39gtvx` | 32m 01s |
| Nova | 2 | `01m6bm1krpk246tywj1c025crz` | 33m 54s |
| Luna | 3 | `01m6bnkm6wfbv6g6tx9bkdap1h` | 30m 19s |
| Vela | 3 | `01m6bnqdvd93yc3mxmxw33bwvp` | 32m 39s |

A separate fixture on merged main `beaff254` submitted the URL through actual Persist participation. The old nonempty-string schema accepted it, observation reserved the attempt, and dispatch threw during Citation target lookup. Its write transaction rolled back while the earlier reservation persisted. Recovery later wrote the exact recorded expiry. Wake records an adapter error in the connection's replaceable last-error field. The dump has no matching last error, failed job or per-attempt transcript. It cannot establish the original exception or whether the Participant saw it for any particular historical execution.

## Encounter replay

The isolated PostgreSQL restore used session-local views with one decision cutoff per encounter. The views restrict creation, ledger entries, judgments, consumptions, structural results and Exploration Events and restore the Cycle and Phase to their open state. InputPlanService produced the action schemas. For projected arrivals, GenerativeTraversalService supplied the previous Phase's arrival, ContinuationOpportunityService built its comparison context, and PhaseGenerationService and PropositionOptionService evaluated the same target. Some historical projection tables retain only their latest value. The Bias measurements quoted below were recorded at or before the selected decision. The snapshot has no archived action packets.

Of 35 Constrain Rests, 28 had zero unconsumed ordinary Receipts in the active Horizon. Of 126 Bias Rests, 117 had zero. These counts alone do not identify the first failed predicate. The following encounters all recorded `no_available_move`. The actual and independently projected schemas offered Rest alone. Both rejected downstream generation.

| Constrain encounter | Gate | Ordinary available / consumed | Effective judgments | First blocker |
| --- | --- | ---: | ---: | --- |
| Nova 3 | `01m6bp0vhatr4wdhsxctj0vk7y` | 2 / 0 | 0 | No effective Attestation |
| Nova 37 | `01m6c7gh06n4pc3zzwsrpd2jr4` | 0 / 18 | 3 | No consumable Receipt |
| Vela 63 | `01m6c7gh06n4pc3zzwsrpd2jr4` | 0 / 18 | 3 | No consumable Receipt |

All three Gates were personally unexplored at the cutoff and had no existing classification in the destination register. Write, Attestation and Traverse options were empty. Constrain has no fresh Variant action.

| Bias encounter | Gate | Orientation | Attestation Cadence | Combined Chirality | Existing classification / effective judgments | Acting-Gate restriction |
| --- | --- | ---: | ---: | ---: | --- | --- |
| Luna 31 | `01m6bt81s6am83cht8fb4zyv9n` | +0.5 | +0.357033 | +0.313623 | 0 / 2 | Directions agree. All 12 Receipts had been consumed. |
| Nova 44 | `01m6c4ynm6za7vrxycz10s8w2f` | 0 (positive) | +0.0572278 | +0.0522228 | 0 / 2 | Directions agree. All 18 Receipts had been consumed. |
| Vela 60 | `01m6c7r1569gx506xqtb2xpmam` | 0 (negative) | +0.2087122 | +0.0951568 | 0 / 2 | Component directions disagree. All 18 Receipts had been consumed. |
| Luna 103 | `01m6bs92eancwz6qpyxz5dayr3` | +0.5 | −0.1238593 | +0.1636203 | 1 / 2 | Component directions disagree. Its one Write was already explored. All 18 Receipts had been consumed. |

None offered Attestation, Traverse or Reclassification on an alternate source. As a control, masking only the consumption of one existing in-scope Receipt made both the actual and projected selectors offer Reclassification for Nova 37 at Constrain and Nova 44 at Bias. At Luna 103 it admitted alternate Bias sources while the acting Gate still failed Chirality. No missing Receipt predicate was reproduced.

The preceding Phase explains how a Participant could get there. Nova 3 created the Persist Gate through Variant. Nova 37 and Vela 63 used ordinary Traverse to a personally unexplored Write. At each pre-hop cutoff, the base Traverse selector admitted the Write and the generative look-ahead rejected it. Luna 31, Nova 44, Vela 60 and Luna 103 reached Bias by Attesting at Mediate. The pre-hop selector offered Attestation in each case. None of those arrivals needed a downstream Reclassification to authorize movement.

## Fresh Distinguish generation

All sampled Origin encounters had Crossover Margin +0.1666666. The two Distinguish Propositions were created by Luna in Cycle 1 and Nova in Cycle 6.

| Origin encounter | Personally explored / Writes | Downstream generation through base Traverse candidates | Authoritative actions |
| --- | ---: | --- | --- |
| Luna 1 | 0 / 0 | None | Instrument. Luna made the first Distinguish Variant. |
| Vela 2 | 0 / 1 | First Write | Attest or Traverse. Personal exploration still blocked fresh generation. |
| Nova 6 | 1 / 1 | First Write | Instrument or Traverse. Nova chose Instrument and made the second Variant. |
| Luna 5 | 1 / 2 | First Write | Attest the second Write or Traverse the first. The second still required personal exploration. |
| Nova 37 | 0 / 2 | First Write | Attest or Traverse. Both Writes retained attached evidence. |
| Luna 103 and Vela 63 | 0 / 2 | None | Attest or ordinary Traverse to either Write on prior-Cycle personal judgment. Fresh Instrument was unavailable. |

Exploration Events at downstream heads do not automatically complete an Origin Write with remaining personal opportunities. In these samples, the two-Write frontier explains the missing Instrument even with open margin and attached evidence. A preference for Traverse across all Cycles cannot be inferred.

The paired main/branch replays used identical action choices and SELECT counts on nine sampled plans, including 69 / 99 / 150 at Constrain, 131 / 133 / 134 / 254 at Bias and 95 / 226 at Origin. See the PR validation table for the independent dense-context and concurrency comparisons.
