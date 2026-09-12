import { test, after } from 'node:test';
import assert from 'node:assert/strict';
import { build, stop } from 'esbuild';
import { createRequire } from 'node:module';
import { setTimeout as wait } from 'node:timers/promises';
import { Window } from 'happy-dom';

const window = new Window({ url: 'http://localhost' });
after(async () => {
  await window.happyDOM.abort();
  await window.happyDOM.close();
  stop();
  console.log('DOM test resources after cleanup:', process.getActiveResourcesInfo());
}, { timeout: 5000 });
for (const key of ['window', 'document', 'navigator', 'HTMLElement', 'Element', 'Node', 'ShadowRoot', 'MutationObserver', 'ResizeObserver']) {
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
    assert.deepEqual(requests, ['/api/substrates/test/propositions/New']);
    assert.equal(document.querySelectorAll('.substrate-graph-edges path').length, 1);
    for (const id of ['Origin', 'New']) {
      await act(async () => cards().find(card => card.textContent.includes(id)).click());
      await until(() => document.querySelector('[role="dialog"]')?.textContent.includes(`${id} authoritative detail`));
      assert.match(document.querySelector('[role="dialog"]').textContent, /Proposition inspector/);
      assert.equal(cards().length, 2);
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
