import { test, after } from 'node:test';
import assert from 'node:assert/strict';
import { build, stop } from 'esbuild';
import { createRequire } from 'node:module';
import { setTimeout as wait } from 'node:timers/promises';
import { Window } from 'happy-dom';

// Browser-scheduled MessagePorts belong to the test realm, too. Node's native
// ports otherwise keep the worker alive after the mounted page is gone.
const messageChannels = [];
const NativeMessageChannel = globalThis.MessageChannel;
globalThis.MessageChannel = class extends NativeMessageChannel {
  constructor() { super(); messageChannels.push(this); }
};
const window = new Window({ url: 'http://localhost' });
after(async () => {
  await window.happyDOM.abort();
  await window.happyDOM.close();
  stop();
  for (const channel of messageChannels) { channel.port1.close(); channel.port2.close(); }
}, { timeout: 5000 });
for (const key of ['window', 'document', 'navigator', 'HTMLElement', 'Element', 'Node', 'ShadowRoot', 'MutationObserver', 'ResizeObserver', 'Event', 'CustomEvent']) {
  Object.defineProperty(globalThis, key, { configurable: true, value: key === 'window' ? window : window[key] });
}
globalThis.getComputedStyle = window.getComputedStyle.bind(window);
globalThis.requestAnimationFrame = window.requestAnimationFrame.bind(window);
globalThis.cancelAnimationFrame = window.cancelAnimationFrame.bind(window);
globalThis.IS_REACT_ACT_ENVIRONMENT = true;
const NativeRequest = globalThis.Request;
globalThis.Request = class extends NativeRequest {
  constructor(input, options) { super(typeof input === 'string' && input.startsWith('/') ? `http://localhost${input}` : input, options); }
};

// Keep the real singleton, Echo adapter, channel ownership, hooks and RTK Query.
// Model Pusher's ACK/pending-cancellation lifecycle at the network boundary.
const connections = [];
globalThis.__createTestPusher = () => {
  const channels = new Map();
  const connection = {
    channels, disconnected: false, joins: [], leaves: [],
    connection: { state: 'connected', socket_id: 'test.1' },
    subscribe(name) {
      this.joins.push(name);
      let channel = channels.get(name);
      if (!channel) {
        channel = { listeners: new Map(), subscribed: false, subscriptionPending: false, subscriptionCancelled: false,
          bind(event, callback) {
            if (!this.listeners.has(event)) this.listeners.set(event, new Set());
            this.listeners.get(event).add(callback); return this;
          },
          unbind(event, callback) { this.listeners.get(event)?.delete(callback); return this; },
          emit(event, payload) { this.listeners.get(event)?.forEach(callback => callback(payload)); },
          callback(payload) { this.emit('invalidation', payload); },
          subscribedCallback() {
            this.subscriptionPending = false; this.subscribed = true;
            if (this.subscriptionCancelled) connection.unsubscribe(name);
            else this.emit('pusher:subscription_succeeded');
          },
        };
        channels.set(name, channel);
      }
      if (channel.subscriptionPending) channel.subscriptionCancelled = false;
      else if (!channel.subscribed) {
        channel.subscriptionPending = true;
        queueMicrotask(() => channel.subscribedCallback());
      }
      return channel;
    },
    unsubscribe(name) {
      this.leaves.push(name);
      const channel = channels.get(name);
      if (channel?.subscriptionPending) channel.subscriptionCancelled = true;
      else channels.delete(name);
    },
    reconnect() {
      for (const channel of channels.values()) { channel.subscribed = false; channel.subscriptionPending = false; }
      for (const name of channels.keys()) this.subscribe(name);
    },
    disconnect() { this.disconnected = true; },
  };
  connections.push(connection); return connection;
};
const result = await build({ stdin: { contents: `
  import React from 'react';
  import { createRoot } from 'react-dom/client';
  import { MantineProvider } from '@mantine/core';
  import { Provider } from 'react-redux';
  import { configureStore } from '@reduxjs/toolkit';
  import { MemoryRouter, Routes, Route } from 'react-router-dom';
  import { SubstratePage } from './resources/js/pages/SubstratePage';
  import { commonsApi, useGetParticipantQuery, useGetParticipationQuery } from './resources/js/services/commonsApi';
  import { useParticipantRealtime, useContextHorizonRealtime } from './resources/js/realtime/useRealtime';
  export { act } from 'react';
  export { commonsApi };
  export { getEcho } from './resources/js/realtime/echo';
  export { subscribeInvalidations } from './resources/js/realtime/subscriptions';
  function ParticipantProbe({ id }) {
    useGetParticipantQuery({ slug: 'test', participantId: id });
    const { data } = useGetParticipationQuery({ slug: 'test', participantId: id });
    useParticipantRealtime('test', id, Boolean(data));
    return <div>{data?.status}</div>;
  }
  function GraphProbe({ slug, enabled = true }) { useContextHorizonRealtime(slug, enabled); return null; }
  export function mount(container) {
    const store = configureStore({ reducer: { [commonsApi.reducerPath]: commonsApi.reducer },
      middleware: getDefault => getDefault().concat(commonsApi.middleware) });
    const root = createRoot(container);
    return { store,
      graph() { root.render(<Provider store={store}><MantineProvider env="test"><MemoryRouter initialEntries={['/substrates/test']}>
        <Routes><Route path="/substrates/:slug" element={<SubstratePage />} /></Routes>
      </MemoryRouter></MantineProvider></Provider>); },
      participant(id) { root.render(<Provider store={store}><ParticipantProbe id={id} /></Provider>); },
      probes({ graphSlugs = [], participantIds = [], enabled = true } = {}) {
        root.render(<React.StrictMode><Provider store={store}>
          {graphSlugs.map((slug, i) => <GraphProbe key={'graph-' + i} slug={slug} enabled={enabled} />)}
          {participantIds.map((id, i) => <ParticipantProbe key={'participant-' + i} id={id} />)}
        </Provider></React.StrictMode>);
      },
      close() { root.unmount(); store.dispatch(commonsApi.util.resetApiState()); },
    };
  }
`, loader: 'tsx', resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic',
  loader: { '.css': 'empty' }, plugins: [{ name: 'test-transport', setup(builder) {
    builder.onResolve({ filter: /^pusher-js$/ }, () => ({ path: 'test-transport', namespace: 'test' }));
    builder.onLoad({ filter: /.*/, namespace: 'test' }, () => ({ contents: 'export default class Pusher { constructor() { return globalThis.__createTestPusher(); } }', loader: 'js' }));
  } }], define: { 'import.meta.env': JSON.stringify({ VITE_REVERB_APP_KEY: 'test' }) },
});
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const { act, mount, getEcho, subscribeInvalidations, commonsApi } = module.exports;
const proposition = (id, parent_ids = []) => ({ id, title: id, statement: `${id} authoritative detail`, parent_ids,
  register: 'activity', tier: parent_ids.length ? 'expressive' : 'committal', is_origin: !parent_ids.length, instruments: [] });
const json = data => new Response(JSON.stringify({ data }), { headers: { 'Content-Type': 'application/json' } });
const live = () => connections.findLast(connection => !connection.disconnected);
const cards = () => [...document.querySelectorAll('.substrate-graph-node .proposition-card')];
async function settle() { await act(async () => { await wait(140); }); }
async function until(condition) {
  for (let i = 0; i < 30; i++) { await settle(); if (condition()) return; }
  assert.fail(`UI did not settle: ${document.body.textContent}`);
}
function container() { const el = document.createElement('div'); document.body.append(el); return el; }

// Each test owns a mounted page and disposes every query/subscription afterwards.
test('Graph fetches new nodes incrementally and both original and realtime nodes fill the persistent inspector rail', { timeout: 30000 }, async () => {
  const requests = [];
  const origin = { ...proposition('Origin'), field: { tension: null, charge: null, potential_charge: null } };
  globalThis.fetch = async request => {
    const path = new URL(request.url).pathname; requests.push(path);
    if (path.endsWith('/graph-participants')) return json({ server_time: new Date().toISOString(), participants: [] });
    if (path.endsWith('/graph-metrics')) return json([]);
    if (path.endsWith('/realtime')) return json({ substrate_id: 'substrate', public: true, context_horizon_ids: ['horizon'] });
    if (path.endsWith('/propositions')) return json([origin]);
    if (path.includes('/propositions/')) return json({ ...proposition(path.split('/').pop(), path.endsWith('/Origin') ? [] : ['Origin']), field: { tension: -.5, charge: .5, potential_charge: .42 } });
    return json({ id: 'substrate', slug: 'test', title: 'Test', visibility: 'public' });
  };
  const el = container(); const page = mount(el);
  try {
    await act(async () => page.graph());
    await until(() => cards().length === 1 && live()?.channels.size === 2);
    assert.equal(document.querySelector('.mantine-Breadcrumbs-root'), null);
    assert.ok(document.querySelector('.substrate-view__header [aria-label="0 Participants"]'));
    assert.equal(document.querySelector('.substrate-view__header h3').textContent, 'Test');
    await settle(); requests.length = 0;
    const channel = live().channels.get('public.context-horizon.horizon');
    const event = { type: 'proposition.created', proposition_id: 'New', context_horizon_id: 'horizon' };
    await act(async () => { channel.callback(event); channel.callback(event); });
    await until(() => cards().length === 2);
    await until(() => requests.includes('/api/substrates/test/graph-metrics'));
    assert.deepEqual(requests, ['/api/substrates/test/propositions/New', '/api/substrates/test/graph-metrics']);
    assert.equal(document.querySelectorAll('.substrate-graph-edges path').length, 1);
    for (const id of ['Origin', 'New']) {
      await act(async () => cards().find(card => card.textContent.includes(id)).click());
      await until(() => document.querySelector('.graph-workspace__inspector')?.textContent.includes(`${id} authoritative detail`));
      assert.match(document.querySelector('.graph-workspace__inspector').textContent, /Proposition inspector/);
      assert.ok(document.querySelector('.graph-workspace__inspector [aria-label="Potential Charge: 0.42"]'), 'fresh detail is not hidden by an older unavailable graph field');
      assert.equal(cards().length, 2);
      const edgesBefore = document.querySelector('.substrate-graph-edges').innerHTML;
      const select = document.querySelector('.substrate-graph-controls select');
      await act(async () => { select.value = 'register'; select.dispatchEvent(new window.Event('change', { bubbles: true })); });
      assert.equal(document.querySelector('.substrate-graph-edges').innerHTML, edgesBefore);
      assert.equal(cards().find(card => card.dataset.propositionId === id).getAttribute('aria-pressed'), 'true');
      assert.ok(document.querySelector('.graph-workspace__inspector').textContent.includes(`${id} authoritative detail`));
      const requestCount = requests.length;
      await act(async () => document.querySelector('input[value="topology"]').click());
      assert.equal(requests.length, requestCount, 'density uses existing batched graph data');
      assert.equal(cards().find(card => card.dataset.propositionId === id).getAttribute('aria-pressed'), 'true');
      await act(async () => document.querySelector('input[value="detailed"]').click());
      await act(async () => document.querySelector('[aria-label="Collapse graph workspace"]').click());
      await until(() => !document.querySelector('.graph-workspace__inspector'));
    }
    await act(async () => channel.callback(event));
    await settle();
    assert.equal(cards().length, 2);
  } finally { await act(async () => page.close()); el.remove(); }
  assert.equal(live().channels.size, 0);
  assert.equal(connections.length, 1);
  assert.equal(live().disconnected, false);
});

test('an empty Graph discovers its first Horizon and a reconnect catches up missed nodes', { timeout: 30000 }, async () => {
  let horizons = []; let nodes = [];
  globalThis.fetch = async request => {
    const path = new URL(request.url).pathname;
    if (path.endsWith('/graph-participants')) return json({ server_time: new Date().toISOString(), participants: [] });
    if (path.endsWith('/graph-metrics')) return json([]);
    if (path.endsWith('/realtime')) return json({ substrate_id: 'substrate', public: true, context_horizon_ids: horizons });
    if (path.endsWith('/propositions')) return json(nodes);
    return json({ id: 'substrate', slug: 'test', title: 'Test', visibility: 'public' });
  };
  const el = container(); const page = mount(el);
  try {
    await act(async () => page.graph());
    await until(() => live()?.channels.has('public.substrate.substrate'));
    horizons = ['first']; nodes = [proposition('Origin')];
    await act(async () => live().channels.get('public.substrate.substrate').callback({ type: 'context-horizons.changed', substrate_id: 'substrate' }));
    await until(() => live()?.channels.has('public.context-horizon.first') && cards().length === 1);
    nodes = [...nodes, proposition('Missed', ['Origin'])];
    await act(async () => { live().reconnect(); });
    await until(() => cards().length === 2);
  } finally { await act(async () => page.close()); el.remove(); }
  assert.equal(live().channels.size, 0);
  assert.equal(connections.length, 1);
  assert.equal(live().disconnected, false);
});

test('Participant hook refreshes its state and releases the previous channel when switching or leaving', { timeout: 30000 }, async () => {
  const requests = [];
  globalThis.fetch = async request => {
    const path = new URL(request.url).pathname; requests.push(path);
    return json(path.endsWith('/participate') ? { status: 'waiting' } : { id: path.split('/').pop() });
  };
  const el = container(); const page = mount(el);
  try {
    await act(async () => page.participant('a'));
    await until(() => live()?.channels.has('private-participant.a'));
    await settle(); requests.length = 0;
    await act(async () => live().channels.get('private-participant.a').callback({ type: 'participant.state_changed', participant_id: 'a' }));
    await until(() => requests.length === 2);
    assert.deepEqual(requests.sort(), ['/api/substrates/test/participants/a', '/api/substrates/test/participants/a/participate']);
    const first = live();
    await act(async () => page.participant('b'));
    await until(() => live()?.channels.has('private-participant.b'));
    assert.equal(live(), first);
    assert.equal(first.disconnected, false);
    assert.deepEqual([...first.channels.keys()], ['private-participant.b']);
  } finally { await act(async () => page.close()); el.remove(); }
  assert.equal(live().channels.size, 0);
  assert.equal(connections.length, 1);
  assert.equal(live().disconnected, false);
});

test('graph activity follows canonical positions through a wake, keeps prepared P1, and reconnects authoritatively', { timeout: 30000 }, async () => {
  const requests = [];
  let nodes = [proposition('Origin')];
  let current = 'Origin'; let active = false;
  globalThis.fetch = async request => {
    const path = new URL(request.url).pathname; requests.push(path);
    if (path.endsWith('/realtime')) return json({ substrate_id: 'substrate', public: true, context_horizon_ids: ['horizon'] });
    if (path.endsWith('/graph-participants')) return json({ server_time: new Date().toISOString(), participants: [
      { id: 'nova', callsign: 'Nova', current_proposition_id: current, is_active: active, activity_expires_at: active ? Date.now() + 60000 : null },
      { id: 'vela', callsign: 'Vela', current_proposition_id: 'Origin', is_active: false, activity_expires_at: null },
    ] });
    if (path.endsWith('/graph-metrics')) return json([]);
    if (path.endsWith('/propositions')) return json(nodes);
    if (path.includes('/propositions/')) return json(nodes.find(n => n.id === path.split('/').pop()));
    return json({ id: 'substrate', slug: 'test', title: 'Test', visibility: 'public' });
  };
  const el = container(); const page = mount(el);
  const nova = () => document.querySelector('[data-participant-id="nova"]');
  const position = () => nova()?.closest('.substrate-graph-node').querySelector('.proposition-card').dataset.propositionId;
  const notify = () => live().channels.get('public.substrate.substrate').callback({ type: 'graph.participant_changed', substrate_id: 'substrate', participant_id: 'nova' });
  try {
    await act(async () => page.graph());
    await until(() => position() === 'Origin' && live()?.channels.size === 2);
    assert.equal(nova().getAttribute('data-active'), null);
    assert.equal(document.querySelectorAll('.graph-participant').length, 2);
    await settle(); requests.length = 0;
    active = true;
    await act(async () => notify());
    await until(() => nova()?.getAttribute('data-active') === 'true');
    assert.deepEqual(requests, ['/api/substrates/test/graph-participants']);
    nodes.push(proposition('Move', ['Origin'])); current = 'Move';
    await act(async () => notify());
    await until(() => position() === 'Move');
    assert.equal(nova().getAttribute('data-active'), 'true');
    nodes.push(proposition('Prepared', ['Move'])); current = 'Prepared'; active = false;
    await act(async () => notify());
    await until(() => position() === 'Prepared' && !nova()?.getAttribute('data-active'));
    assert.equal(nova().querySelector('.graph-participant__activity'), null);
    // Missed start and position events are recovered by subscription acknowledgement.
    current = 'Origin'; active = true;
    await act(async () => { live().reconnect(); });
    await until(() => position() === 'Origin' && nova()?.getAttribute('data-active') === 'true');
  } finally { await act(async () => page.close()); el.remove(); }
  assert.equal(live().channels.size, 0);
  assert.equal(connections.length, 1);
  assert.equal(live().disconnected, false);
});

test('an expired activity snapshot deactivates locally even if the end event never arrives', { timeout: 15000 }, async () => {
  const server = Date.parse('2050-01-01T00:00:00Z');
  globalThis.fetch = async request => {
    const path = new URL(request.url).pathname;
    if (path.endsWith('/realtime')) return json({ substrate_id: 'substrate', public: true, context_horizon_ids: [] });
    if (path.endsWith('/graph-participants')) return json({ server_time: new Date(server).toISOString(), participants: [
      { id: 'nova', callsign: 'Nova', current_proposition_id: 'Origin', is_active: true, activity_expires_at: server + 1500 },
    ] });
    if (path.endsWith('/graph-metrics')) return json([]);
    if (path.endsWith('/propositions')) return json([proposition('Origin')]);
    return json({ id: 'substrate', slug: 'test', title: 'Test', visibility: 'public' });
  };
  const el = container(); const page = mount(el);
  const avatar = () => document.querySelector('[data-participant-id="nova"]');
  try {
    await act(async () => page.graph());
    await until(() => avatar()?.getAttribute('data-active') === 'true');
    await until(() => avatar() && !avatar().getAttribute('data-active'));
    assert.equal(cards().length, 1);
    assert.match(avatar().getAttribute('aria-label'), /inactive/);
  } finally { await act(async () => page.close()); el.remove(); }
});

test('horizon growth, shrinkage and reordering preserve retained channels and pending invalidations', { timeout: 30000 }, async () => {
  let horizons = ['A', 'B'];
  const requests = [];
  globalThis.fetch = async request => {
    const path = new URL(request.url).pathname; requests.push(path);
    if (path.endsWith('/realtime')) return json({ substrate_id: 'substrate', public: true, context_horizon_ids: horizons });
    if (path.endsWith('/graph-participants')) return json({ server_time: new Date().toISOString(), participants: [] });
    if (path.endsWith('/graph-metrics')) return json([]);
    if (path.endsWith('/propositions')) return json([proposition('Origin')]);
    if (path.includes('/propositions/')) return json(proposition(path.split('/').pop(), ['Origin']));
    return json({ id: 'substrate', slug: 'test', title: 'Test', visibility: 'public' });
  };
  const el = container(); const page = mount(el);
  const describe = () => page.store.dispatch(commonsApi.util.upsertQueryData('getGraphSubscriptions', 'test', {
    substrate_id: 'substrate', public: true, context_horizon_ids: horizons,
  }));
  try {
    await act(async () => page.graph());
    await until(() => live()?.channels.size === 3);
    await settle();
    const connection = live(); const original = new Map(connection.channels);
    connection.joins.length = 0; connection.leaves.length = 0; requests.length = 0;
    horizons = ['A', 'B', 'C'];
    await act(async () => {
      original.get('public.context-horizon.A').callback({ type: 'proposition.created', proposition_id: 'Pending', context_horizon_id: 'A' });
      await describe();
    });
    await until(() => requests.includes('/api/substrates/test/propositions/Pending'));
    assert.equal(live(), connection);
    assert.deepEqual(connection.joins, ['public.context-horizon.C']);
    assert.deepEqual(connection.leaves, []);
    for (const [name, channel] of original) assert.equal(connection.channels.get(name), channel);
    const c = connection.channels.get('public.context-horizon.C');
    // A descriptor with different ordering/duplicates is the same subscription set.
    horizons = ['C', 'B', 'A', 'A'];
    await act(async () => describe()); await settle();
    assert.deepEqual(connection.joins, ['public.context-horizon.C']);
    horizons = ['A', 'C'];
    await act(async () => describe()); await settle();
    assert.deepEqual(connection.leaves, ['public.context-horizon.B']);
    assert.equal(connection.channels.get('public.context-horizon.A'), original.get('public.context-horizon.A'));
    assert.equal(connection.channels.get('public.context-horizon.C'), c);
    requests.length = 0;
    original.get('public.context-horizon.B').callback({ type: 'proposition.updated', proposition_id: 'Removed', context_horizon_id: 'B' });
    await settle();
    assert.deepEqual(requests, [], 'removed horizons cannot enqueue stale work');
    for (const channel of connection.channels.values()) {
      assert.equal(channel.listeners.get('invalidation').size, 1);
      assert.equal(channel.listeners.get('pusher:subscription_succeeded').size, 1);
    }
    assert.equal(connections.length, 1);
    assert.equal(connection.disconnected, false);
  } finally { await act(async () => page.close()); el.remove(); }
  assert.equal(live().channels.size, 0);
});

test('Strict Mode Graph and Participation consumers share channels and survive independent teardown and reconnect', { timeout: 30000 }, async () => {
  const requests = []; let status = 'waiting';
  globalThis.fetch = async request => {
    const path = new URL(request.url).pathname; requests.push(path);
    if (path.endsWith('/realtime')) return json({ substrate_id: 'substrate', public: false, context_horizon_ids: ['horizon'] });
    if (path.endsWith('/propositions') || path.endsWith('/graph-metrics')) return json([]);
    return json(path.endsWith('/participate') ? { status } : { id: path.split('/').pop() });
  };
  const el = container(); const page = mount(el);
  try {
    await act(async () => page.probes({ graphSlugs: ['test', 'test'], participantIds: ['a', 'a'] }));
    await until(() => live()?.channels.size === 3 && el.textContent === 'waitingwaiting');
    await settle();
    const connection = live(); const original = new Map(connection.channels);
    assert.equal(getEcho().connector.pusher, connection);
    assert.equal(connections.length, 1);
    for (const channel of original.values()) assert.equal(channel.listeners.get('invalidation').size, 1);
    // Remove one owner of each channel. The surviving hooks must still receive events.
    connection.leaves.length = 0;
    await act(async () => page.probes({ graphSlugs: ['test'], participantIds: ['a'] }));
    await settle();
    assert.deepEqual(connection.leaves, []);
    for (const [name, channel] of original) assert.equal(connection.channels.get(name), channel);
    for (let i = 0; i < 3; i++) {
      status = `reconnected-${i}`; requests.length = 0;
      await act(async () => connection.reconnect());
      await until(() => el.textContent === status && requests.includes('/api/substrates/test/graph-metrics'));
      assert.equal(requests.filter(path => path.endsWith('/participate')).length, 1);
      assert.equal(requests.filter(path => path.endsWith('/realtime')).length, 1);
      assert.equal(requests.filter(path => path.endsWith('/propositions')).length, 1);
      for (const channel of connection.channels.values()) assert.equal(channel.listeners.get('pusher:subscription_succeeded').size, 1);
    }
    await act(async () => page.probes({ participantIds: ['a'] })); await settle();
    assert.deepEqual([...connection.channels.keys()], ['private-participant.a']);
    requests.length = 0; status = 'still live';
    await act(async () => original.get('private-participant.a').callback({ type: 'participant.state_changed', participant_id: 'a' }));
    await until(() => el.textContent === status);
    assert.deepEqual(requests.sort(), ['/api/substrates/test/participants/a', '/api/substrates/test/participants/a/participate']);
    assert.equal(connection.disconnected, false);
  } finally { await act(async () => page.close()); el.remove(); }
  assert.equal(live().channels.size, 0);
});

test('reconnect discovers missed horizons and catches up metrics as well as graph nodes', { timeout: 30000 }, async () => {
  let horizons = ['A']; let potential = .1;
  const requests = [];
  globalThis.fetch = async request => {
    const path = new URL(request.url).pathname; requests.push(path);
    if (path.endsWith('/realtime')) return json({ substrate_id: 'substrate', public: true, context_horizon_ids: horizons });
    if (path.endsWith('/graph-participants')) return json({ server_time: new Date().toISOString(), participants: [] });
    if (path.endsWith('/propositions')) return json([proposition('Origin')]);
    if (path.endsWith('/graph-metrics')) return json([{ id: 'Origin', field: { tension: 0, charge: 1, potential_charge: potential }, participation_count: 7 }]);
    return json({ id: 'substrate', slug: 'test', title: 'Test', visibility: 'public' });
  };
  const el = container(); const page = mount(el);
  const field = () => commonsApi.endpoints.getPropositions.select('test')(page.store.getState()).data?.[0].field;
  try {
    await act(async () => page.graph());
    await until(() => field()?.potential_charge === .1);
    const a = live().channels.get('public.context-horizon.A');
    horizons = ['B']; potential = .8; requests.length = 0;
    await act(async () => live().reconnect());
    await until(() => field()?.potential_charge === .8 && live().channels.has('public.context-horizon.B'));
    await settle();
    assert.equal(live().channels.has('public.context-horizon.A'), false);
    assert.equal(a.listeners.get('invalidation').size, 0);
    assert.ok(requests.some(path => path.endsWith('/graph-participants')));
    assert.equal(connections.length, 1);
    assert.equal(live().disconnected, false);
  } finally { await act(async () => page.close()); el.remove(); }
});

test('channel ownership handles pending remounts, late observers, and stale callbacks without listener leaks', async () => {
  const counts = { first: 0, second: 0, firstAck: 0, secondAck: 0 };
  const first = { invalidated: () => counts.first++, subscribed: () => counts.firstAck++ };
  const second = { invalidated: () => counts.second++, subscribed: () => counts.secondAck++ };
  let release = subscribeInvalidations('participant.owned', false, first);
  const pending = live().channels.get('private-participant.owned');
  const stale = [...pending.listeners.get('invalidation')][0];
  release(); release();
  // Pusher retains cancelled pending subscriptions until acknowledgement.
  release = subscribeInvalidations('participant.owned', false, first);
  assert.equal(live().channels.get('private-participant.owned'), pending);
  await Promise.resolve();
  assert.equal(counts.firstAck, 1);
  const releaseSecond = subscribeInvalidations('participant.owned', false, second);
  await Promise.resolve();
  assert.equal(counts.secondAck, 1, 'late observer catches up an already acknowledged channel');
  pending.callback({}); stale({});
  assert.equal(counts.first, 1); assert.equal(counts.second, 1);
  release(); pending.callback({});
  assert.equal(counts.first, 1); assert.equal(counts.second, 2);
  live().reconnect(); await Promise.resolve();
  assert.equal(counts.firstAck, 1); assert.equal(counts.secondAck, 2);
  releaseSecond();
  assert.equal(pending.listeners.get('invalidation').size, 0);
  assert.equal(pending.listeners.get('pusher:subscription_succeeded').size, 0);
  pending.callback({}); pending.subscribedCallback();
  assert.equal(counts.second, 2); assert.equal(counts.secondAck, 2);
  assert.equal(live().channels.size, 0);
  assert.equal(live().disconnected, false);
});

test('releasing one channel never removes the public or private channel variant owned by another consumer', async () => {
  let publicEvents = 0; let privateEvents = 0;
  const leavePublic = subscribeInvalidations('scope', true, { invalidated: () => publicEvents++, subscribed() {} });
  const leavePrivate = subscribeInvalidations('scope', false, { invalidated: () => privateEvents++, subscribed() {} });
  await Promise.resolve();
  leavePublic();
  assert.equal(live().channels.has('scope'), false);
  live().channels.get('private-scope').callback({});
  assert.equal(publicEvents, 0); assert.equal(privateEvents, 1);
  leavePrivate();
  assert.equal(live().channels.size, 0);
});

test('subscription catch-up waits for an older HTTP list then fetches a post-ACK snapshot', { timeout: 30000 }, async () => {
  let releaseInitial; let listRequests = 0;
  globalThis.fetch = async request => {
    const path = new URL(request.url).pathname;
    if (path.endsWith('/realtime')) return json({ substrate_id: 'substrate', public: true, context_horizon_ids: [] });
    if (path.endsWith('/graph-participants')) return json({ server_time: new Date().toISOString(), participants: [] });
    if (path.endsWith('/graph-metrics')) return json([]);
    if (path.endsWith('/propositions')) {
      listRequests++;
      if (listRequests === 1) return new Promise(resolve => { releaseInitial = () => resolve(json([proposition('Origin')])); });
      return json([proposition('Origin'), proposition('DuringHandshake', ['Origin'])]);
    }
    return json({ id: 'substrate', slug: 'test', title: 'Test', visibility: 'public' });
  };
  const el = container(); const page = mount(el);
  try {
    await act(async () => page.graph());
    await until(() => releaseInitial && live()?.channels.get('public.substrate.substrate')?.subscribed);
    await settle();
    assert.equal(listRequests, 1, 'the pre-ACK request is still running');
    await act(async () => releaseInitial());
    await until(() => cards().length === 2);
    assert.equal(listRequests, 2, 'catch-up must start a fresh request after the old one');
  } finally { releaseInitial?.(); await act(async () => page.close()); el.remove(); }
});

test('leaving while catch-up waits for HTTP cancels follow-on requests and pending callbacks', { timeout: 30000 }, async () => {
  let releaseInitial; let listRequests = 0; let metricRequests = 0;
  globalThis.fetch = async request => {
    const path = new URL(request.url).pathname;
    if (path.endsWith('/realtime')) return json({ substrate_id: 'substrate', public: true, context_horizon_ids: [] });
    if (path.endsWith('/graph-participants')) return json({ server_time: new Date().toISOString(), participants: [] });
    if (path.endsWith('/graph-metrics')) { metricRequests++; return json([]); }
    if (path.endsWith('/propositions')) {
      listRequests++;
      return new Promise(resolve => { releaseInitial = () => resolve(json([proposition('Origin')])); });
    }
    return json({ id: 'substrate', slug: 'test', title: 'Test', visibility: 'public' });
  };
  const el = container(); const page = mount(el);
  try {
    await act(async () => page.graph());
    await until(() => releaseInitial && live()?.channels.get('public.substrate.substrate')?.subscribed);
    await settle();
    const channel = live().channels.get('public.substrate.substrate');
    await act(async () => page.probes());
    await act(async () => {
      channel.callback({ type: 'graph.metrics_changed', substrate_id: 'substrate' });
      releaseInitial();
    });
    await settle();
    assert.equal(listRequests, 1);
    assert.equal(metricRequests, 0);
    assert.equal(live().channels.size, 0);
    assert.equal(live().disconnected, false);
  } finally { releaseInitial?.(); await act(async () => page.close()); el.remove(); }
});

test('history visibility keeps exhausted paths without changing the live cache or realtime transport', { timeout: 30000 }, async () => {
  const origin = proposition('Origin');
  const historical = { ...proposition('Exhausted', ['Origin']), active: false,
    exhaustion: { exhausted: true, basin_exhausted: false, exhausted_at: '2026-09-17T00:00:00Z' } };
  let extra = [];
  globalThis.fetch = async request => {
    const url = new URL(request.url); const path = url.pathname;
    if (path.endsWith('/graph-participants')) return json({ server_time: new Date().toISOString(), participants: [] });
    if (path.endsWith('/graph-metrics')) return json([]);
    if (path.endsWith('/realtime')) return json({ substrate_id: 'substrate', public: true, context_horizon_ids: ['horizon'] });
    if (path.endsWith('/propositions')) return json(url.searchParams.has('view') ? [origin, historical, ...extra] : [origin]);
    if (path.includes('/propositions/')) return json(historical);
    return json({ id: 'substrate', slug: 'test', title: 'Test', visibility: 'public' });
  };
  const el = container(); const page = mount(el);
  try {
    await act(async () => page.graph());
    await until(() => cards().length === 1 && live()?.channels.size === 2);
    const transport = live(); const joins = transport.joins.length;
    const select = document.querySelector('select[aria-label="Graph visibility"]');
    assert.ok(select);
    await act(async () => { select.value = 'history'; select.dispatchEvent(new Event('change', { bubbles: true })); });
    await until(() => cards().length === 2);
    assert.equal(live(), transport);
    assert.equal(transport.joins.length, joins);
    assert.deepEqual(commonsApi.endpoints.getPropositions.select('test')(page.store.getState()).data.map(node => node.id), ['Origin']);
    extra = [{ ...historical, id: 'Another', title: 'Another' }];
    await act(async () => transport.channels.get('public.substrate.substrate').callback({ type: 'context-horizons.changed', substrate_id: 'substrate' }));
    await until(() => cards().length === 3);
    await act(async () => { select.value = 'active'; select.dispatchEvent(new Event('change', { bubbles: true })); });
    await until(() => cards().length === 1);
    assert.equal(live(), transport);
  } finally { await act(async () => page.close()); el.remove(); }
});
