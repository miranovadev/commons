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


const result = await build({ stdin: { contents: `
  import React from 'react';
  import { createRoot } from 'react-dom/client';
  import { MantineProvider } from '@mantine/core';
  import { Provider } from 'react-redux';
  import { configureStore } from '@reduxjs/toolkit';
  import { MemoryRouter, useLocation, Link } from 'react-router-dom';
  import { MainNavigation } from './resources/js/components/MainNavigation';
  import { PropositionGraph } from './resources/js/components/PropositionGraph';
  import { commonsApi } from './resources/js/services/commonsApi';
  export { act } from 'react';
  function NavProbe() {
    const location = useLocation();
    return <><MainNavigation hasUser substrateTitle="Test Substrate" /><output>{location.pathname}</output><Link to="/substrates/test">Enter substrate</Link></>;
  }
  export function mountNavigation(container, path) {
    const root = createRoot(container);
    return { render() { root.render(<MantineProvider env="test"><MemoryRouter initialEntries={[path]}><NavProbe /></MemoryRouter></MantineProvider>); }, close() { root.unmount(); } };
  }
  export function mount(container, propositions, participants = []) {
    const store = configureStore({ reducer: { [commonsApi.reducerPath]: commonsApi.reducer },
      middleware: getDefault => getDefault().concat(commonsApi.middleware) });
    const root = createRoot(container);
    return {
      render(nodes = propositions, positions = participants) {
        root.render(<Provider store={store}><MantineProvider env="test"><PropositionGraph propositions={nodes} participants={positions} /></MantineProvider></Provider>);
      },
      close() { root.unmount(); store.dispatch(commonsApi.util.resetApiState()); },
    };
  }
`, loader: 'tsx', resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false,
  jsx: 'automatic', loader: { '.css': 'empty' } });
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const { act, mount, mountNavigation } = module.exports;
const node = (id, parent_ids = []) => ({ id, title: id, statement: `${id} full statement`, parent_ids,
  register: 'activity', tier: parent_ids.length ? 'expressive' : 'committal', is_origin: !parent_ids.length,
  participation_count: id.length * 2, instruments: [], field: { tension: -.75, charge: .25, potential_charge: .42, status: 'current', potential_status: 'current' } });
const nodes = [node('origin'), node('a', ['origin']), node('b', ['origin']), { ...node('c', ['a', 'b']), tragedy: true, renewal: true }, node('end', ['c'])];
const card = id => document.querySelector(`.substrate-graph-node [data-proposition-id="${id}"]`);
const ids = () => [...document.querySelectorAll('.substrate-graph-node [data-proposition-id]')].map(el => el.dataset.propositionId);
const selected = () => document.querySelector('.substrate-graph-node [aria-pressed="true"]')?.dataset.propositionId;
const button = name => document.querySelector(`button[aria-label="${name}"]`);
async function click(element) { assert.ok(element, 'control exists'); await act(async () => element.click()); }
async function lens(value) {
  const control = document.querySelector('.substrate-graph-controls select');
  await act(async () => { control.value = value; control.dispatchEvent(new window.Event('change', { bubbles: true })); });
}
async function withGraph(run) {
  window.localStorage.clear();
  const el = document.createElement('div'); document.body.append(el);
  const page = mount(el, nodes);
  try { await act(async () => page.render()); await run(page); }
  finally { await act(async () => page.close()); el.remove(); }
}

test('isolation filters nodes and connecting edges in memory and restores selection and overlays', async () => withGraph(async () => {
  const isolate = () => document.querySelector('input[type="checkbox"]');
  assert.equal(isolate().disabled, true);
  await click(card('a'));
  await lens('potential-charge');
  await click(isolate());
  assert.deepEqual(ids(), ['origin', 'a']);
  assert.equal(document.querySelectorAll('.substrate-graph-edges path').length, 1);
  assert.equal(selected(), 'a');
  assert.ok(card('a').style.getPropertyValue('--graph-overlay-strength'));
  await click(isolate());
  assert.equal(ids().length, 5);
  assert.equal(document.querySelectorAll('.substrate-graph-edges path').length, 5);
  assert.equal(selected(), 'a');
}));

test('selection and deselection leave Workspace, viewport and lenses independent', async () => withGraph(async () => {
  const rail = () => document.querySelector('.graph-workspace');
  const canvas = document.querySelector('.substrate-graph-canvas');
  const viewport = document.querySelector('.substrate-graph-viewport');
  Object.defineProperties(viewport, { clientWidth: { configurable: true, value: 1000 }, clientHeight: { configurable: true, value: 700 } });
  viewport.setPointerCapture = () => {};
  assert.equal(rail().dataset.state, 'open');
  assert.equal(document.querySelector('.graph-inspector'), null);
  await click(card('a'));
  assert.match(document.querySelector('.graph-inspector').textContent, /a full statement/);
  assert.equal(rail().querySelector('.graph-inspector'), null);
  assert.equal(viewport.querySelector('.graph-inspector'), null, 'Inspector does not inherit the drag surface touch-action');
  assert.equal(document.querySelector('.graph-workspace__inspector'), null);
  await lens('tension');
  await click(document.querySelector('input[type="checkbox"]'));
  await click(button('Collapse graph workspace'));
  await click(card('a'));
  assert.equal(rail().dataset.state, 'collapsed');
  assert.ok(document.querySelector('.graph-inspector'));
  await act(async () => {
    viewport.dispatchEvent(new window.PointerEvent('pointerdown', { bubbles: true, button: 0, clientX: 200, clientY: 200, pointerId: 1 }));
    viewport.dispatchEvent(new window.PointerEvent('pointermove', { bubbles: true, clientX: 400, clientY: 320, pointerId: 1 }));
    viewport.dispatchEvent(new window.PointerEvent('pointerup', { bubbles: true, pointerId: 1 }));
  });
  const pan = canvas.style.transform;
  await click(button('Deselect proposition'));
  assert.equal(document.querySelector('.graph-inspector'), null);
  assert.equal(selected(), undefined);
  assert.equal(document.activeElement, card('a'), 'deselect restores keyboard focus without scrolling');
  assert.equal(document.querySelector('.substrate-graph-canvas'), canvas, 'canvas is not replaced');
  assert.equal(canvas.style.transform, pan, 'deselect does not move camera to Origin');
  assert.equal(rail().dataset.state, 'collapsed');
  await click(button('Open graph workspace'));
  assert.equal(document.querySelector('.substrate-graph-controls select').value, 'tension');
  assert.equal(document.querySelector('input[type="checkbox"]').checked, true);
  assert.equal(document.querySelector('input[type="checkbox"]').disabled, true);
  assert.equal(ids().length, 5);
  await click(card('c'));
  const beforeOrigin = canvas.style.transform;
  await click([...document.querySelectorAll('button')].find(el => el.textContent === 'Show Origin'));
  assert.notEqual(canvas.style.transform, beforeOrigin);
  assert.equal(canvas.dataset.animating, 'true');
  assert.equal(selected(), 'c');
  assert.equal(rail().dataset.state, 'open');
  assert.equal(document.querySelector('.substrate-graph-controls select').value, 'tension');
  assert.equal(document.querySelector('input[type="checkbox"]').checked, true);
}));

test('inspector arrows preserve the selected branch through isolation and live dataset reordering', async () => withGraph(async page => {
  await click(card('c'));
  await lens('tension');
  await click(document.querySelector('input[type="checkbox"]'));
  await click(button('Previous proposition'));
  assert.equal(selected(), 'a', 'first ordered primary parent');
  assert.deepEqual(ids(), ['origin', 'a']);
  await click(button('Previous proposition'));
  assert.equal(selected(), 'origin');
  assert.equal(button('Previous proposition').disabled, true);
  await act(async () => page.render([...nodes].reverse()));
  await click(button('Next proposition'));
  assert.equal(selected(), 'a');
  await click(button('Next proposition'));
  assert.equal(selected(), 'c', 'remembered forward branch survives stepping back');
  await click(button('Next proposition'));
  assert.equal(selected(), 'end');
  assert.equal(button('Next proposition').disabled, true);
  assert.equal(document.querySelector('.substrate-graph-controls select').value, 'tension');
  assert.equal(document.querySelector('input[type="checkbox"]').checked, true);
  assert.match(document.querySelector('.graph-inspector').textContent, /end full statement/);
}));


test('direct substrate routes, context exit and re-entry expose the correct complete menu', async () => {
  for (const path of ['/substrates/test', '/substrates/test/', '/substrates/test/participants', '/substrates/test/participants/nova']) {
    const el = document.createElement('div'); document.body.append(el);
    const page = mountNavigation(el, path);
    try {
      await act(async () => page.render());
      let nav = document.querySelector('nav');
      assert.equal(nav.getAttribute('aria-label'), 'Substrate navigation');
      assert.deepEqual([...nav.querySelectorAll('a')].map(el => el.getAttribute('href')), ['/', '/substrates/test', '/substrates/test/participants', null]);
      assert.equal(nav.querySelector('[data-active]').textContent, path.includes('participants') ? 'Participants' : 'Graph');
      assert.equal(nav.querySelector('[data-disabled]').textContent, 'Settings');
      assert.doesNotMatch(nav.textContent, /Connections/);
      await click(nav.querySelector('[aria-label="Back to Substrates"]'));
      assert.equal(document.querySelector('output').textContent, '/');
      nav = document.querySelector('nav');
      assert.equal(nav.getAttribute('aria-label'), 'Main navigation');
      assert.match(nav.textContent, /Connections/);
      assert.equal(nav.querySelector('[data-active]').textContent, 'Substrates');
      await click([...document.querySelectorAll('a')].find(el => el.textContent === 'Enter substrate'));
      assert.equal(document.querySelector('nav').getAttribute('aria-label'), 'Substrate navigation');
    } finally { await act(async () => page.close()); el.remove(); }
  }
});

async function density(value) {
  const radio = document.querySelector(`input[type="radio"][value="${value}"]`);
  await click(radio);
}

test('Topology composes every lens, selection, inspector and isolation without changing the graph', async () => withGraph(async () => {
  await click(card('c'));
  assert.ok(card('c').querySelector('.proposition-field'));
  for (const mode of ['none', 'participation', 'potential-charge', 'tension', 'register']) {
    await density('detailed');
    await lens(mode);
    const analytical = card('c').style.getPropertyValue('--graph-overlay-color');
    const intensity = card('c').style.getPropertyValue('--graph-overlay-strength');
    const detailedEdges = document.querySelectorAll('.substrate-graph-edges path').length;
    await density('topology');
    assert.equal(document.querySelector('.substrate-graph-shell').dataset.density, 'topology');
    assert.equal(selected(), 'c');
    assert.equal(ids().length, 5);
    assert.equal(document.querySelectorAll('.substrate-graph-edges path').length, detailedEdges);
    assert.equal(card('c').style.getPropertyValue('--graph-overlay-color'), analytical);
    assert.equal(card('c').style.getPropertyValue('--graph-overlay-strength'), intensity);
    assert.equal(card('c').querySelector('.proposition-field'), null);
    assert.doesNotMatch(card('c').textContent, /full statement|Potential Charge|Tension/);
    assert.match(card('c').getAttribute('aria-label'), /c full statement/);
    assert.equal(card('c').tagName, 'BUTTON');
    assert.equal(card('c').closest('.substrate-graph-node').dataset.lineage, 'selected');
    assert.ok(card('c').closest('.substrate-graph-node').querySelector('[aria-label="Tragedy"]'));
    assert.ok(card('c').closest('.substrate-graph-node').querySelector('[aria-label="Renewal"]'));
    assert.equal(card('end').closest('.substrate-graph-node').dataset.lineage, 'unrelated');
    assert.match(document.querySelector('.graph-inspector').textContent, /c full statement/);
    await click([...document.querySelectorAll('[role="tab"]')].find(el => el.textContent === 'Field State'));
    assert.match(document.querySelector('.graph-inspector').textContent, /0.42/);
    await click([...document.querySelectorAll('[role="tab"]')].find(el => el.textContent === 'Proposition'));
    assert.equal(document.querySelector('.substrate-graph-controls select').value, mode);
    await click(document.querySelector('input[type="checkbox"]'));
    assert.equal(card('end'), null);
    await click(button('Previous proposition'));
    assert.equal(selected(), 'a');
    await click(button('Next proposition'));
    assert.equal(selected(), 'c');
    assert.equal(document.querySelector('.substrate-graph-shell').dataset.density, 'topology');
    await click(document.querySelector('input[type="checkbox"]'));
  }
  await click(card('b'));
  assert.equal(selected(), 'b');
  await density('detailed');
  assert.equal(selected(), 'b');
  assert.ok(card('b').querySelector('.proposition-field'));
  assert.equal(document.querySelector('.substrate-graph-controls select').value, 'register');
}));

test('Topology retains stacked presence, live movement, activity and selection while collapsed', async () => withGraph(async page => {
  const positions = ['Nova', 'Vela', 'Luna', 'Sera', 'Claire'].map((callsign, i) => ({ id: String(i), callsign,
    current_proposition_id: 'a', is_active: i === 0 || i === 4, activity_expires_at: null }));
  await act(async () => page.render(nodes, positions));
  await density('topology');
  assert.equal(document.querySelector('.graph-participants').dataset.compact, 'true');
  assert.equal(document.querySelectorAll('.graph-participant').length, 3);
  assert.ok(document.querySelector('[aria-label="Nova: active"]'));
  assert.ok(document.querySelector('[aria-label="Vela: inactive"]'));
  assert.match(document.querySelector('.graph-participant--overflow').getAttribute('aria-label'), /Claire: active/);
  assert.equal(document.querySelectorAll('.graph-participant__activity').length, 2);
  await click(card('a'));
  await lens('potential-charge');
  await click(button('Collapse graph workspace'));
  const moved = positions.map((p, i) => i === 0 ? { ...p, current_proposition_id: 'b', is_active: false } : p);
  await act(async () => page.render(nodes, moved));
  const nova = document.querySelector('[data-participant-id="0"]');
  assert.equal(nova.closest('.substrate-graph-node').querySelector('.proposition-card').dataset.propositionId, 'b');
  assert.equal(nova.hasAttribute('data-active'), false);
  await click(card('b'));
  assert.equal(document.querySelector('.graph-workspace').dataset.state, 'collapsed');
  assert.equal(selected(), 'b');
  await click(button('Open graph workspace'));
  assert.equal(document.querySelector('.substrate-graph-controls select').value, 'potential-charge');
  assert.equal(document.querySelector('input[value="topology"]').checked, true);
}));


test('Inspector tabs show observed measurements and structural history, with local tab state', async () => withGraph(async page => {
  const point = { proposition_id: 'origin', depth: 0, cadence: 0, depth_direction: 'negative', cadence_direction: 'negative' };
  await act(async () => page.render(nodes.map(p => ({ ...p, observables: { correspondence: .2, chirality: -.3, closure: 0 },
    closure_history: { points: [point], current: point, source: 'selected-committal' } }))));
  await click(card('origin'));
  const tab = label => [...document.querySelectorAll('[role="tab"]')].find(el => el.textContent === label);
  await click(tab('Field State'));
  for (const label of ['Correspondence', 'Chirality', 'Closure', 'Tension']) assert.ok(document.querySelector(`.graph-inspector svg[aria-label^="${label}:"]`));
  assert.ok(document.querySelector('.graph-inspector svg[aria-label^="Tension: -0.75"]'), 'uses authoritative tension, not a frontend mean');
  await click(tab('Closure'));
  assert.ok(document.querySelector('svg[aria-label^="Depth history"]'));
  assert.ok(document.querySelector('svg[aria-label^="Cadence history"]'));
  assert.equal(document.querySelectorAll('.closure-direction[data-direction="negative"][data-color="indigo.9"]').length, 2);
  await click(card('b'));
  assert.equal(tab('Closure'), undefined, 'expressive nodes have no Closure tab');
  assert.equal(tab('Proposition').getAttribute('aria-selected'), 'true');
  await click(tab('Field State'));
  await click(card('a'));
  assert.equal(tab('Field State').getAttribute('aria-selected'), 'true');
  assert.equal(selected(), 'a');
  assert.equal(document.querySelector('.graph-workspace').dataset.state, 'open');
}));
