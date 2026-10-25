import { test, after } from 'node:test';
import assert from 'node:assert/strict';
import { build, stop } from 'esbuild';
import { createRequire } from 'node:module';
import { setTimeout as wait } from 'node:timers/promises';
import { Window } from 'happy-dom';

const channels = [];
const NativeMessageChannel = globalThis.MessageChannel;
globalThis.MessageChannel = class extends NativeMessageChannel { constructor() { super(); channels.push(this); } };
const window = new Window({ url: 'http://localhost' });
for (const key of ['window', 'document', 'navigator', 'HTMLElement', 'HTMLInputElement', 'HTMLTextAreaElement', 'Document', 'Element', 'Node', 'ShadowRoot', 'MutationObserver', 'ResizeObserver', 'Event', 'CustomEvent']) {
  Object.defineProperty(globalThis, key, { configurable: true, value: key === 'window' ? window : window[key] });
}
globalThis.getComputedStyle = window.getComputedStyle.bind(window);
globalThis.requestAnimationFrame = window.requestAnimationFrame.bind(window);
globalThis.cancelAnimationFrame = window.cancelAnimationFrame.bind(window);
globalThis.IS_REACT_ACT_ENVIRONMENT = true;
const NativeRequest = globalThis.Request;
globalThis.Request = class extends NativeRequest { constructor(input, options) { super(typeof input === 'string' && input.startsWith('/') ? `http://localhost${input}` : input, options); } };
after(async () => { await window.happyDOM.abort(); await window.happyDOM.close(); stop(); for (const c of channels) { c.port1.close(); c.port2.close(); } });

const bundle = await build({ stdin: { contents: `
import React from 'react';
import { createRoot } from 'react-dom/client';
import { MantineProvider } from '@mantine/core';
import { MemoryRouter, Routes, Route, useLocation } from 'react-router-dom';
import { Provider } from 'react-redux';
import { configureStore } from '@reduxjs/toolkit';
import { commonsApi } from './resources/js/services/commonsApi';
import { ParticipantPage } from './resources/js/pages/ParticipantPage';
import { InspectorContent } from './resources/js/components/PropositionInspector';
import { PreparationDetails } from './resources/js/components/PreparationDetails';
export { act } from 'react';
function Location() { return <output data-location>{useLocation().pathname + useLocation().search}</output>; }
export function mount(container, surface, preparation) {
 const store = configureStore({ reducer: { [commonsApi.reducerPath]: commonsApi.reducer }, middleware: getDefault => getDefault().concat(commonsApi.middleware) });
 const root = createRoot(container);
 return {
  render() { root.render(<Provider store={store}><MantineProvider env="test"><MemoryRouter initialEntries={['/substrates/test/participants/viewer']}>
   <Location /><Routes><Route path="/substrates/:slug/participants/:participantId" element={surface === 'details' ? <PreparationDetails slug="test" preparation={preparation} /> : surface === 'graph' ? <InspectorContent graph substrateSlug="test" selection={{ kind:'graph', proposition: globalThis.experience.plan.gate }} /> : <ParticipantPage />} /><Route path="*" element={<div>Graph destination</div>} /></Routes>
  </MemoryRouter></MantineProvider></Provider>); },
  refresh() { store.dispatch(commonsApi.util.invalidateTags([{ type: 'Participation', id:'viewer' }])); },
  close() { root.unmount(); store.dispatch(commonsApi.util.resetApiState()); }
 };
}`, loader: 'tsx', resolveDir: process.cwd() }, plugins: [{ name: 'transport', setup(b) {
 b.onResolve({ filter: /\/realtime\/useRealtime$/ }, () => ({ path: 'realtime', namespace: 'test' }));
 b.onLoad({ filter: /.*/, namespace: 'test' }, () => ({ contents: 'export const useParticipantRealtime = (...args) => { globalThis.experience.realtime = args; };' }));
} }], bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
const module = { exports: {} };
new Function('require', 'module', 'exports', bundle.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const { act, mount } = module.exports;
const button = name => [...document.querySelectorAll('button')].find(b => b.textContent.trim() === name);
const tick = async () => { await act(async () => { await wait(30); }); };
async function until(check) { for (let i = 0; i < 50 && !check(); i++) await tick(); assert.ok(check(), 'expected UI condition'); }
async function click(control) { assert.ok(control, 'control exists'); await act(async () => control.click()); }
async function draft(value) {
 const field = document.querySelector('textarea'); assert.ok(field);
 await act(async () => { Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value').set.call(field, value); field.dispatchEvent(new window.Event('input', { bubbles: true })); });
 return field;
}
function fixture() {
 const gate = { id:'gate', register:'canon', tier:'expressive', statement:'An observed Gate.', instruments:[] };
 const action = { title:'Variant', properties:{ action:{ const:'proposition.variant' }, statement:{} }, required:['action','statement'] };
 const plan = { phase:{ id:'phase', kind:'disperse', number:4, write_register:'activity' }, gate, read:[], write_options:[], gate_options:[gate],
  schema:{ oneOf:[{ $ref:'#/$defs/actions/proposition.variant' }], $defs:{ actions:{ 'proposition.variant':action } } } };
 return { plan, state:{ status:'ready', plan, participant:{ id:'viewer', callsign:'Claire' }, context_horizon_id:'horizon' }, requests:[], posts:[], workspaceReads:0 };
}
function record(outcome = 'contributed') {
 return { id:'execution', participant:{id:'viewer',callsign:'Claire'}, originating_gate_id:'gate', cycle:{id:'cycle',sequence:3}, phase:{id:'phase',number:4,name:'disperse'},
 purpose:'Compare two readings.', status: outcome === 'failed' ? 'failed' : ['pending','presented'].includes(outcome) ? outcome : 'completed', outcome: ['pending','presented'].includes(outcome) ? null : outcome,
 resulting_proposition_id: outcome === 'contributed' ? 'result' : null, receipt_id: ['pending','presented'].includes(outcome) ? null : 'receipt', created_at:'2026-10-25T00:00:00Z', started_at:null,
 presented_at: outcome === 'pending' ? null : '2026-10-25T00:00:01Z', resolved_at:['pending','presented'].includes(outcome) ? null : '2026-10-25T00:00:02Z',
 selections:[{receipt_id:'source-one',artifact_id:'hash-one',read_type:'text',parameters:{offset:2,max_chars:20000},source:{receipt_type:'load_file',reference:'https://example.test/file',media_type:'text/plain',size:32000}},
 {receipt_id:'source-two',artifact_id:'hash-two',read_type:'raw',source:null}],
 deliveries: outcome === 'pending' ? [] : [{artifact_id:'hash-one',receipt_ids:['source-one'],read_type:'text',method:'native',version:'1',payload_bytes:9000,boundaries:{offset:2,end:8002,length:8000,total:32000,has_more:true}}],
 deferment_reason:'Need another source.', failure:outcome === 'failed' ? {stage:'read',classification:'storage_failure',reason:'<script>globalThis.executed = true</script>'} : null };
}
async function withPage(surface, run, preparation) {
 const harness = globalThis.experience = fixture();
 globalThis.fetch = async request => {
  const url = typeof request === 'string' ? request : request.url;
  const path = new URL(url, 'http://localhost').pathname;
  harness.requests.push(url);
  let data;
  if (path.endsWith('/csrf-cookie')) return new Response(null, {status:204});
  if (path.endsWith('/participate')) {
   harness.posts.push(JSON.parse(await request.text()));
   return new Promise(resolve => { harness.resolvePost = resolve; });
  }
  if (path.endsWith('/workspace')) {
   harness.workspaceReads++;
   if (harness.holdWorkspace) await new Promise(resolve => { harness.releaseWorkspace = resolve; });
   data = harness.state;
  } else if (path.endsWith('/preparations/execution')) data = record();
  else if (path.endsWith('/preparations')) data = { items:[record()],next_cursor:null };
  else if (path.endsWith('/participants/viewer')) data = {id:'viewer',callsign:'Claire',max_read_count:25};
  else if (path.endsWith('/participants')) data = [];
  else if (path.endsWith('/session')) data = null;
  else throw new Error(`Unexpected endpoint: ${url}`);
  return new Response(JSON.stringify({data}), {headers:{'Content-Type':'application/json'}});
 };
 const element = document.createElement('div'); document.body.append(element);
 const page = mount(element, surface, preparation);
 try { await act(async () => page.render()); await run(harness, page, element); }
 finally { await act(async () => page.close()); element.remove(); }
}

test('background HTTP and projection refresh retain the interactive workspace, drafts, realtime and valid submission', async () => {
 await withPage('participant', async (h, page) => {
  await until(() => button('Variant'));
  await click(button('Variant'));
  const field = await draft('A human draft survives collaboration.');
  h.holdWorkspace = true;
  await act(async () => page.refresh());
  await until(() => h.releaseWorkspace);
  assert.equal(document.querySelector('textarea'), field);
  assert.equal(field.value, 'A human draft survives collaboration.');
  assert.equal(document.querySelector('fieldset').disabled, false);
  h.state = {status:'waiting',reason:{code:'potential_projection_pending',message:'The field is updating. Please retry shortly.'}};
  h.holdWorkspace = false;
  await act(async () => h.releaseWorkspace());
  await until(() => document.body.textContent.includes('Refreshing available options'));
  assert.equal(document.querySelector('textarea'), field);
  assert.ok(button('Apply contribution'));
  assert.equal(button('Apply contribution').disabled, false);
  assert.equal(document.querySelector('fieldset').disabled, false);
  assert.doesNotMatch(document.body.textContent, /Ready to enter the cycle|Please retry shortly/);
  assert.deepEqual(h.realtime, ['test','viewer',true,'horizon']);
  await click(button('Apply contribution'));
  await until(() => h.posts.length === 1);
  assert.equal(h.posts[0].contribution.statement, field.value);
  assert.equal(h.posts[0].phase_id, 'phase');
  assert.equal(document.querySelector('fieldset').disabled, true, 'own submitted action retains local feedback');
  h.state = {status:'ready',plan:h.plan};
  await act(async () => h.resolvePost(new Response(JSON.stringify({data:h.state}), {headers:{'Content-Type':'application/json'}})));
  await until(() => !document.querySelector('textarea'));
  assert.ok(h.workspaceReads >= 3, 'mutation still invalidates the workspace');
 });
});

for (const phaseId of ['phase', 'next-phase']) test(`changed options (${phaseId}) keep drafts and display the authoritative rejection`, async () => {
 await withPage('participant', async (h, page) => {
  await until(() => button('Variant')); await click(button('Variant'));
  const field = await draft('Keep this draft after a conflict.');
  h.state = {status:'ready',plan:{...h.plan,phase:{...h.plan.phase,id:phaseId},schema:{$defs:{actions:{}},oneOf:[]}}};
  await act(async () => page.refresh()); await until(() => h.workspaceReads >= 2); await tick();
  assert.equal(document.querySelector('textarea'),field);
  await click(button('Apply contribution')); await until(() => h.posts.length === 1);
  assert.equal(h.posts[0].phase_id,'phase','draft keeps its original authority identity');
  await act(async () => h.resolvePost(new Response(JSON.stringify({message:'The participation state changed. Refresh before contributing.'}), {status:409,headers:{'Content-Type':'application/json'}})));
  await until(() => document.body.textContent.includes('The participation state changed.'));
  assert.equal(document.querySelector('textarea'),field);
  assert.equal(field.value,'Keep this draft after a conflict.');
  assert.equal(button('Apply contribution').disabled,false);
  assert.ok(h.workspaceReads >= 3,'rejected submission refreshes current options');
 });
});

test('a real waiting transition replaces the workspace after a transient projection refresh', async () => {
 await withPage('participant', async (h, page) => {
  await until(() => button('Variant'));
  h.state = {status:'waiting',reason:{code:'potential_projection_pending'}};
  await act(async () => page.refresh()); await until(() => h.workspaceReads >= 2); await tick();
  assert.ok(button('Variant'));
  h.state = {status:'waiting',reason:{code:'cycle_not_started'},entry:{mode:'potential',available:false}};
  await act(async () => page.refresh()); await until(() => !document.querySelector('fieldset'));
  assert.match(document.body.textContent,/Ready to enter the cycle/);
 });
});

for (const outcome of ['contributed','deferred','failed','pending','presented']) test(`Preparation details faithfully render ${outcome} history`, async () => {
 await withPage('details', async (_h, _page, element) => {
  const text = element.textContent;
  assert.match(text,/Selection 1/); assert.match(text,/Selection 2/);
  assert.match(text,/Requested maximum characters20000/);
  assert.match(text,/Automatic allocation/);
  assert.match(text,/Not recorded/);
  assert.doesNotMatch(text,/undefined|null/);
  if (outcome !== 'pending') { assert.match(text,/Delivered characters8000/); assert.match(text,/Delivered bytes9000/); assert.match(text,/Actual end8002/); assert.match(text,/More material remainedYes/); }
  if (outcome === 'contributed') assert.ok(element.querySelector('a[href="/substrates/test?proposition=result&view=history"]'));
  if (outcome === 'deferred') assert.match(text,/Participant’s reason: Need another source/);
  if (outcome === 'failed') { assert.match(text,/storage_failure/); assert.match(text,/No successful source observation is implied/); assert.equal(element.querySelector('script'),null); assert.equal(globalThis.executed,undefined); }
  if (outcome === 'pending') { assert.match(text,/No acknowledged delivery/); assert.match(text,/No presentation or final outcome/); assert.doesNotMatch(text,/Delivered characters8000/); }
  if (outcome === 'presented') assert.match(text,/no final outcome has been recorded/);
 }, record(outcome));
});

for (const surface of ['graph','participant']) test(`${surface} opens the shared read-only Preparation details and links to its result`, async () => {
 await withPage(surface, async (h) => {
  await until(() => button('Preparation history')); await click(button('Preparation history'));
  await until(() => [...document.querySelectorAll('button')].some(b => b.textContent.includes('Compare two readings.')));
  await click([...document.querySelectorAll('button')].find(b => b.textContent.includes('Compare two readings.')));
  await until(() => document.querySelector('[aria-label="Preparation details"]'));
  assert.match(document.body.textContent,/Requested maximum characters20000/);
  const listUrl = h.requests.find(url => url.includes('/preparations?'));
  assert.ok(listUrl.includes(surface === 'graph' ? 'proposition_id=gate' : 'participant_id=viewer'));
  assert.equal(h.requests.some(url => /\/reading|\/presented/.test(url)),false);
  await click(document.querySelector('a[href="/substrates/test?proposition=result&view=history"]'));
  assert.equal(document.querySelector('[data-location]').textContent,'/substrates/test?proposition=result&view=history');
 });
});
