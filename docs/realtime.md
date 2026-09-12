# Realtime updates

Reverb is an optional notification transport. Broadcast identifiers and invalidation hints, not domain snapshots. RTK Query and the HTTP API remain the sources of application state.

## Flow

1. A domain service persists an action. Ledger recording identifies new or changed Propositions; Phase/Cycle persistence identifies changed Participant state.
2. `RealtimePublisher` registers a callback on the database transaction. A rollback discards it, including callbacks registered in nested transactions.
3. After the outer commit, `RealtimeInvalidation` enters the normal broadcasting queue. It additionally implements Laravel's `ShouldDispatchAfterCommit` contract. Transport/enqueue errors are reported without changing an already committed HTTP action into a failed action.
4. The observing page receives `.invalidation` through Echo and coalesces notifications for 75 ms.
5. RTK Query fetches the affected Proposition by ID or invalidates that Participant's state tags. React displays the updated query data.

Notifications contain no statements, rationale, credentials, connection configuration, measurements, or graph snapshots. They are hints to fetch current state, so duplicates and delayed delivery are harmless.

| Channel (Echo name) | Payload | Consumer |
| --- | --- | --- |
| `participant.{id}` (private) | `type: participant.state_changed`, `participant_id` | Invalidate that Participant and Participation/plan tags |
| `context-horizon.{id}` | `type: proposition.created`, `proposition_id`, `context_horizon_id` | Fetch and upsert Proposition detail and graph list |
| `context-horizon.{id}` | `type: proposition.updated` or `attestation.created`, the same two IDs | Refresh the affected Proposition, including measurements/evidence representation |
| `substrate.{id}` | `type: context-horizons.changed`, `substrate_id` | Refresh the small horizon subscription descriptor |

The current workspace API bundles eligibility, measurements, lineage and active Phase into Participation state. Every accepted workspace contribution advances or completes a Phase, including Attestation and Instrument actions. Refreshing that Participant's derived state is therefore necessary; the event does not separately fetch a duplicate Proposition representation for the workspace. The Participant card endpoint also embeds position and needs refreshing. Other Participant caches are unaffected.

## Graph scope and incremental updates

The existing Graph displays a Substrate, not a single selected Context Horizon. `GET /api/substrates/{slug}/realtime` exposes its horizon IDs without creating an initial horizon. The Graph owns subscriptions to those horizons plus one Substrate discovery channel. An empty Graph therefore discovers its first horizon, and a Closure boundary or horizon rebuild can replace the subscriptions. A future horizon selector can narrow the same hook's horizon set without changing event semantics.

For ordinary Proposition events, `GET /api/substrates/{slug}/propositions/{id}` supplies the authoritative representation and minimal parent/child edges. `getProposition` retains detail in RTK Query; `updateQueryData` upserts by ID into the existing graph query. Duplicate notifications never append duplicate nodes. An event arriving during a fetch schedules another fetch after it completes. A failed fetch leaves the existing list intact. Leaving the page cancels pending work and prevents late results from being merged into its graph list.

A subscription/reconnection performs one catch-up list refresh to cover the gap between the initial HTTP response and subscription acknowledgement. It also refreshes horizon discovery to cover a Closure missed while disconnected. A horizon-set change also uses this catch-up path. Ordinary events do not refetch the graph list. There is no event replay log.

The existing initial HTTP list limit (500) is unchanged. New events append their fetched nodes during an observation session; an ordinary reload/catch-up restores the existing endpoint's bounded initial dataset. Pagination and long-running graph retention remain separate work.

Graph cards open the existing `PropositionInspector`. The loaded card is available immediately while the same detail endpoint supplies instruments and Attestation presentation. Newly received cards use the identical click path. Graph dragging, layout and styling remain unchanged.

## Access

The private Participant channel uses exactly the workspace HTTP `act` policy: an owner with the required permission, or a system user. This PR does not broaden workspace access to every Substrate member.

Private horizon and Substrate channels use the HTTP Substrate `view` policy, including invited membership and system access. Unknown channel IDs are denied. Session cookies and the existing CSRF-cookie endpoint authorize subscriptions through `/broadcasting/auth`.

Public Substrate graphs remain available to guests. They use the corresponding `public.context-horizon.{id}` and `public.substrate.{id}` public channels. The server publishes on these names only if the Substrate is public when the queued event is delivered; guessed public names never disclose private Substrate activity. Private channels remain available to authorized users. As with ordinary Laravel channel authorization, existing private subscriptions are authorized at subscription time; permission changes take effect when reauthorization occurs. HTTP retrieval always checks current permissions.

Client-originated Reverb events are disabled. Configure explicit allowed origins for each environment. Only the public app key belongs in the frontend bundle; the app secret stays server-side.

## Local setup

Install the Composer and npm dependencies, then set these values in `.env`:

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=commons
REVERB_APP_KEY=your-local-public-key
REVERB_APP_SECRET=your-local-secret
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_ALLOWED_ORIGINS=localhost,127.0.0.1
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Run the existing application development processes (`composer run dev`) and, in another terminal:

```sh
php artisan reverb:start
```

The existing development queue listener consumes `participants,default`; broadcasts use `default`. Use an asynchronous queue connection for normal operation. Restart Vite after changing `VITE_*` values. Open the same Participant in two authorized tabs, or open its public Graph as a guest, and perform a contribution in another tab/connected Participant.

`BROADCAST_CONNECTION=null` disables publication. An empty `VITE_REVERB_APP_KEY` disables Echo. Ordinary HTTP interactions continue working with either setting, or while the socket is disconnected.

## Production / Laravel Cloud

Provision Reverb (a managed compatible Reverb service or a supervised `php artisan reverb:start` process) and configure its app ID, key and secret. Keep a worker consuming the `default` queue as well as the existing Participant queue. Expose the Reverb WebSocket endpoint over TLS and allow the actual frontend hostname in `REVERB_ALLOWED_ORIGINS`.

`REVERB_SERVER_HOST/PORT` are the bind address of a self-hosted process. `REVERB_HOST/PORT/SCHEME` are the address the backend publishes to. `VITE_REVERB_HOST/PORT/SCHEME` are the browser-reachable address baked into the frontend build; they may differ from the internal publishing address. A TLS proxy usually exposes port 443 while forwarding to the internal Reverb port. Preserve WebSocket upgrade headers. Do not put `REVERB_APP_SECRET` in any `VITE_*` variable.

Set environment values before the existing production asset build, refresh Laravel's config cache, restart queue workers and gracefully restart Reverb after deployment (`php artisan reverb:restart` for a supervised self-hosted process). This PR does not provision infrastructure or modify the deployment pipeline. Multi-instance Redis scaling is not enabled by this configuration.

## Validation

`php artisan test --filter=RealtimeTest` exercises real commits/rollbacks, contribution events, public/private payload scoping, authorization and detail lookup isolation. `npm run test:ui` includes RTK Query integration and notification queue tests; `npm run build` includes TypeScript checking. The dependency-free queue checks can also run alone with Node 24:

```sh
node --test tests/js/realtimeQueue.test.mjs
```

Check the Graph drawer for both initially loaded and newly arrived cards, and disconnect/restart Reverb while performing ordinary HTTP actions. No live Reverb instance is needed for the automated transaction/cache tests.

References: [Laravel broadcasting](https://laravel.com/docs/13.x/broadcasting), [Reverb](https://laravel.com/docs/13.x/reverb), and [RTK Query cache updates](https://redux-toolkit.js.org/rtk-query/usage/manual-cache-updates).
