import { test, after } from 'node:test';
import assert from 'node:assert/strict';
import { build, stop } from 'esbuild';
import { createRequire } from 'node:module';
import { Window } from 'happy-dom';

const channels = [];
const NativeMessageChannel = globalThis.MessageChannel;
globalThis.MessageChannel = class extends NativeMessageChannel { constructor() { super(); channels.push(this); } };
const window = new Window({ url: 'http://localhost' });
for (const key of ['window', 'document', 'navigator', 'HTMLElement', 'Document', 'Element', 'Node', 'ShadowRoot', 'MutationObserver', 'ResizeObserver', 'Event', 'CustomEvent']) {
  Object.defineProperty(globalThis, key, { configurable: true, value: key === 'window' ? window : window[key] });
}
globalThis.getComputedStyle = window.getComputedStyle.bind(window);
globalThis.requestAnimationFrame = window.requestAnimationFrame.bind(window);
globalThis.cancelAnimationFrame = window.cancelAnimationFrame.bind(window);
globalThis.IS_REACT_ACT_ENVIRONMENT = true;
after(async () => { await window.happyDOM.abort(); await window.happyDOM.close(); stop(); for (const c of channels) { c.port1.close(); c.port2.close(); } });

const bundle = await build({ stdin: { contents: `
import React from 'react';
import { createRoot } from 'react-dom/client';
import { MantineProvider } from '@mantine/core';
import { PreparationPanel } from './resources/js/components/PreparationPanel';
import { PreparationFields } from './resources/js/components/PreparationFields';
export { act } from 'react';
export function mount(container, selection = false) {
 const root = createRoot(container);
 function Fields() { const [items, setItems] = React.useState([]); return <><PreparationFields options={globalThis.preparationHarness.options} value={items} onChange={setItems} /><output>{JSON.stringify(items)}</output></>; }
 return { render() { root.render(<MantineProvider env="test">{selection ? <Fields /> : <PreparationPanel slug="test" participantId="actor" preparation={{execution_id:'execution', status:'pending', purpose:'Compare sources.', selections:[]}} onDefer={() => globalThis.preparationHarness.deferred++} />}</MantineProvider>); }, close() { root.unmount(); } };
}`, loader: 'tsx', resolveDir: process.cwd() }, plugins: [{ name: 'delivery', setup(b) {
  b.onResolve({ filter: /\/services\/commonsApi$/ }, () => ({ path: 'api', namespace: 'test' }));
  b.onLoad({ filter: /.*/, namespace: 'test' }, () => ({ resolveDir: process.cwd(), contents: `
import React from 'react';
export function useReadPreparationMutation() {
 const [data, setData] = React.useState();
 const read = React.useCallback(() => { globalThis.preparationHarness.read++; setData(globalThis.preparationHarness.delivery); }, []);
 return [read, { data, reset: () => globalThis.preparationHarness.resets++ }];
}
export function useAcknowledgePreparationMutation() {
 const acknowledge = React.useCallback(input => { globalThis.preparationHarness.acknowledge(input); return { unwrap: () => Promise.resolve() }; }, []);
 return [acknowledge, { reset: () => globalThis.preparationHarness.resets++ }];
}` }));
} }], bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
const module = { exports: {} };
new Function('require', 'module', 'exports', bundle.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const { act, mount } = module.exports;

test('human content is escaped, acknowledged after rendering, and discarded when the interaction closes', async () => {
  const calls = [];
  globalThis.preparationHarness = { read: 0, resets: 0, deferred: 0,
    delivery: { presentation_token: 'trusted-token', deliveries: [{ request_id: 'request', artifact_id: 'hash', source_type: 'html', read_type: 'raw', method: 'commons-native-text', version: '1', boundaries: { offset: 0, end: 12, total: 40, has_more: true }, content: '<script>globalThis.executed = true</script>Source text' }] },
    acknowledge(input) { assert.match(document.querySelector('pre').textContent, /Source text/); calls.push(input); },
  };
  const element = document.createElement('div'); document.body.append(element);
  const page = mount(element);
  await act(async () => page.render());
  assert.equal(globalThis.preparationHarness.read, 1);
  assert.deepEqual(calls, [{ slug: 'test', participantId: 'actor', executionId: 'execution', token: 'trusted-token' }]);
  assert.equal(element.querySelector('script'), null);
  assert.equal(globalThis.executed, undefined);
  assert.match(element.textContent, /More content exists/);
  const defer = [...element.querySelectorAll('button')].find(b => b.textContent === 'Defer with a reason');
  assert.equal(defer.disabled, false);
  await act(async () => defer.click());
  assert.equal(globalThis.preparationHarness.deferred, 1);
  await act(async () => page.close());
  assert.doesNotMatch(element.textContent, /Source text/);
  assert.equal(globalThis.preparationHarness.resets, 2);
  element.remove();
});

test('selection editor bounds associations and includes explicit character parameters', async () => {
  globalThis.preparationHarness = { options: [{ receipt_id: 'receipt', artifact_id: 'hash', source_type: 'plain-text', reference: 'Source', capabilities: [{ read_type: 'raw', parameters: {} }, { read_type: 'text', parameters: { max_chars: 4000 }, max_chars: 8000 }] }] };
  const element = document.createElement('div'); document.body.append(element);
  const page = mount(element, true);
  await act(async () => page.render());
  for (let i = 0; i < 8; i++) await act(async () => [...element.querySelectorAll('button')].find(b => b.textContent === 'Add Artifact').click());
  assert.equal([...element.querySelectorAll('button')].find(b => b.textContent === 'Add Artifact').disabled, true);
  const selected = JSON.parse(element.querySelector('output').textContent);
  assert.equal(selected.length, 8);
  assert.deepEqual(selected[0], { receipt_id: 'receipt', artifact_id: 'hash', read_type: 'text', parameters: { offset: 0, max_chars: 4000 } });
  await act(async () => [...element.querySelectorAll('button')].find(b => b.textContent === 'Remove selection').click());
  assert.equal(JSON.parse(element.querySelector('output').textContent).length, 7);
  await act(async () => page.close()); element.remove();
});
