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
  import { PropositionGraph } from './resources/js/components/PropositionGraph';
  import { commonsApi } from './resources/js/services/commonsApi';
  export { act } from 'react';
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
const { act, mount } = module.exports;
const node = (id, parent_ids = []) => ({ id, title: id, statement: `${id} full statement`, parent_ids,
  register: 'activity', tier: parent_ids.length ? 'expressive' : 'committal', is_origin: !parent_ids.length,
  instruments: [], field: { tension: -.75, charge: .25, potential_charge: .42, status: 'current', potential_status: 'current' } });
const nodes = [node('origin'), node('a', ['origin']), node('b', ['origin']), node('c', ['a', 'b']), node('end', ['c'])];
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

test('rail collapse, lock, reopen and deselect retain independent graph state', async () => withGraph(async () => {
  assert.equal(document.querySelector('.graph-workspace').dataset.state, 'open');
  assert.equal(document.querySelector('[role="dialog"]'), null);
  await click(card('a'));
  assert.match(document.querySelector('.graph-workspace__inspector').textContent, /a full statement/);
  await click(button('Collapse graph workspace'));
  assert.equal(selected(), 'a');
  await click(card('b'));
  assert.equal(document.querySelector('.graph-workspace').dataset.state, 'open');
  assert.equal(selected(), 'b');
  await click(button('Lock graph workspace closed'));
  await click(card('c'));
  assert.equal(selected(), 'c');
  assert.equal(document.querySelector('.graph-workspace').dataset.state, 'locked');
  assert.equal(document.querySelector('.graph-workspace__inspector'), null);
  await click(button('Unlock graph workspace'));
  assert.equal(document.querySelector('.graph-workspace').dataset.state, 'collapsed');
  await click(card('a'));
  assert.equal(document.querySelector('.graph-workspace').dataset.state, 'open');
  await lens('tension');
  assert.equal(document.querySelector('.graph-workspace__inspector [data-directional]').dataset.tensionPalette, 'analytical');
  await click(document.querySelector('input[type="checkbox"]'));
  await click([...document.querySelectorAll('button')].find(el => el.textContent === 'Deselect'));
  assert.equal(selected(), undefined);
  assert.equal(document.querySelector('.substrate-graph-shell').hasAttribute('data-has-selection'), false);
  assert.equal(ids().length, 5);
  assert.equal(document.querySelector('.substrate-graph-controls select').value, 'tension');
  assert.equal(document.querySelector('input[type="checkbox"]').disabled, true);
  assert.match(document.querySelector('.graph-workspace__inspector').textContent, /Inspect structural state/);
  await click(button('Lock graph workspace closed'));
  await click(button('Unlock and open graph workspace'));
  assert.equal(document.querySelector('.graph-workspace').dataset.state, 'open');
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
  assert.match(document.querySelector('.graph-workspace__inspector').textContent, /end full statement/);
}));
