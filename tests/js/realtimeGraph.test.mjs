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

// Replace only the transport. React, Mantine, RTK Query and the page hooks are real.
const connections = [];
globalThis.__createTestEcho = () => {
  const channels = new Map();
  const connection = {
    channels, disconnected: false,
    channel(name) {
      const channel = { callback: null, subscribedCallback: null,
        listen(_event, callback) { this.callback = callback; return this; },
        subscribed(callback) { this.subscribedCallback = callback; queueMicrotask(callback); return this; },
      };
      channels.set(name, channel); return channel;
    },
    private(name) { return this.channel(`private-${name}`); },
    leave(name) { channels.delete(name); channels.delete(`private-${name}`); },
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
  import { useParticipantRealtime } from './resources/js/realtime/useRealtime';
  export { act } from 'react';
  export { commonsApi };
  function ParticipantProbe({ id }) {
    useGetParticipantQuery({ slug: 'test', participantId: id });
    const { data } = useGetParticipationQuery({ slug: 'test', participantId: id });
    useParticipantRealtime('test', id, Boolean(data));
    return <div>{data?.status}</div>;
  }
  export function mount(container) {
    const store = configureStore({ reducer: { [commonsApi.reducerPath]: commonsApi.reducer },
      middleware: getDefault => getDefault().concat(commonsApi.middleware) });
    const root = createRoot(container);
    return { store,
      graph() { root.render(<Provider store={store}><MantineProvider env="test"><MemoryRouter initialEntries={['/substrates/test']}>
        <Routes><Route path="/substrates/:slug" element={<SubstratePage />} /></Routes>
      </MemoryRouter></MantineProvider></Provider>); },
      participant(id) { root.render(<Provider store={store}><ParticipantProbe id={id} /></Provider>); },
      close() { root.unmount(); store.dispatch(commonsApi.util.resetApiState()); },
    };
  }
`, loader: 'tsx', resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic',
  loader: { '.css': 'empty' }, plugins: [{ name: 'test-transport', setup(builder) {
    builder.onResolve({ filter: /\/echo$/ }, () => ({ path: 'test-transport', namespace: 'test' }));
    builder.onLoad({ filter: /.*/, namespace: 'test' }, () => ({ contents: 'export const createEcho = () => globalThis.__createTestEcho();', loader: 'js' }));
  } }],
});
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const { act, mount } = module.exports;
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
test('Graph fetches new nodes incrementally and both original and realtime nodes open the existing drawer', { timeout: 30000 }, async () => {
  const requests = [];
  const origin = proposition('Origin');
  globalThis.fetch = async request => {
    const path = new URL(request.url).pathname; requests.push(path);
    if (path.endsWith('/graph-participants')) return json({ server_time: new Date().toISOString(), participants: [] });
    if (path.endsWith('/graph-metrics')) return json([]);
    if (path.endsWith('/realtime')) return json({ substrate_id: 'substrate', public: true, context_horizon_ids: ['horizon'] });
    if (path.endsWith('/propositions')) return json([origin]);
    if (path.includes('/propositions/')) return json(proposition(path.split('/').pop(), path.endsWith('/Origin') ? [] : ['Origin']));
    return json({ id: 'substrate', slug: 'test', title: 'Test', visibility: 'public' });
  };
  const el = container(); const page = mount(el);
  try {
    await act(async () => page.graph());
    await until(() => cards().length === 1 && live()?.channels.size === 2);
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
      await until(() => document.querySelector('[role="dialog"]')?.textContent.includes(`${id} authoritative detail`));
      assert.match(document.querySelector('[role="dialog"]').textContent, /Proposition inspector/);
      assert.equal(cards().length, 2);
      const edgesBefore = document.querySelector('.substrate-graph-edges').innerHTML;
      const select = document.querySelector('.substrate-graph-controls select');
      await act(async () => { select.value = 'register'; select.dispatchEvent(new window.Event('change', { bubbles: true })); });
      assert.equal(document.querySelector('.substrate-graph-edges').innerHTML, edgesBefore);
      assert.equal(cards().find(card => card.dataset.propositionId === id).getAttribute('aria-pressed'), 'true');
      assert.ok(document.querySelector('[role="dialog"]').textContent.includes(`${id} authoritative detail`));
      await act(async () => document.querySelector('[role="dialog"] button').click());
      await until(() => !document.querySelector('[role="dialog"]'));
    }
    await act(async () => channel.callback(event));
    await settle();
    assert.equal(cards().length, 2);
  } finally { await act(async () => page.close()); el.remove(); }
  assert.equal(live(), undefined);
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
    await act(async () => { for (const channel of live().channels.values()) channel.subscribedCallback(); });
    await until(() => cards().length === 2);
  } finally { await act(async () => page.close()); el.remove(); }
  assert.equal(live(), undefined);
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
    assert.equal(first.disconnected, true);
    assert.equal(first.channels.size, 0);
  } finally { await act(async () => page.close()); el.remove(); }
  assert.equal(live(), undefined);
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
    await act(async () => { for (const channel of live().channels.values()) channel.subscribedCallback(); });
    await until(() => position() === 'Origin' && nova()?.getAttribute('data-active') === 'true');
  } finally { await act(async () => page.close()); el.remove(); }
  assert.equal(live(), undefined);
});

test('an expired activity snapshot deactivates locally even if the end event never arrives', { timeout: 15000 }, async () => {
  const server = Date.parse('2050-01-01T00:00:00Z');
  globalThis.fetch = async request => {
    const path = new URL(request.url).pathname;
    if (path.endsWith('/realtime')) return json({ substrate_id: 'substrate', public: true, context_horizon_ids: [] });
    if (path.endsWith('/graph-participants')) return json({ server_time: new Date(server).toISOString(), participants: [
      { id: 'nova', callsign: 'Nova', current_proposition_id: 'Origin', is_active: true, activity_expires_at: server + 1500 },
    ] });
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
