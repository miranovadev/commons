import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';

const result = await build({ stdin: { contents: `
  export { configureStore } from '@reduxjs/toolkit';
  export { commonsApi } from './resources/js/services/commonsApi';
  export { refreshParticipant, refreshGraphPropositions, refreshGraphMetrics } from './resources/js/realtime/cacheActions';
`, resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false });
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const { configureStore, commonsApi, refreshParticipant, refreshGraphPropositions, refreshGraphMetrics } = module.exports;
const node = id => ({ id, statement: id, register: 'activity', tier: 'expressive' });
function store() {
  return configureStore({ reducer: { [commonsApi.reducerPath]: commonsApi.reducer },
    middleware: getDefault => getDefault().concat(commonsApi.middleware) });
}

test('a created notification fetches only its Proposition, stores detail and upserts the graph idempotently', async () => {
  const s = store();
  await s.dispatch(commonsApi.util.upsertQueryData('getPropositions', 'test', [node('origin')]));
  const requests = [];
  const originalRequest = globalThis.Request;
  const originalFetch = globalThis.fetch;
  const originalDocument = globalThis.document;
  globalThis.document = { cookie: '' };
  globalThis.Request = class extends originalRequest {
    constructor(input, options) { super(typeof input === 'string' && input.startsWith('/') ? `http://localhost${input}` : input, options); }
  };
  globalThis.fetch = async request => {
    requests.push(request.url);
    return new Response(JSON.stringify({ data: node('child') }), { headers: { 'Content-Type': 'application/json' } });
  };
  try {
    await refreshGraphPropositions(s.dispatch, 'test', ['child', 'child'], () => true);
    await refreshGraphPropositions(s.dispatch, 'test', ['child'], () => true);
    assert.deepEqual(requests, Array(2).fill('http://localhost/api/substrates/test/propositions/child'));
    assert.deepEqual(commonsApi.endpoints.getPropositions.select('test')(s.getState()).data.map(n => n.id), ['origin', 'child']);
    assert.equal(commonsApi.endpoints.getProposition.select({ slug: 'test', propositionId: 'child' })(s.getState()).data.id, 'child');
  } finally {
    s.dispatch(commonsApi.util.resetApiState());
    globalThis.Request = originalRequest; globalThis.fetch = originalFetch; globalThis.document = originalDocument;
  }
});

test('Participant refresh invalidates only that Participant and its derived plan', async () => {
  const s = store();
  for (const id of ['a', 'b']) {
    await s.dispatch(commonsApi.util.upsertQueryData('getParticipant', { slug: 'test', participantId: id }, { id }));
    await s.dispatch(commonsApi.util.upsertQueryData('getParticipation', { slug: 'test', participantId: id }, { status: 'waiting' }));
    await s.dispatch(commonsApi.util.upsertQueryData('getParticipantWorkspace', { slug: 'test', participantId: id }, { status: 'waiting' }));
    await s.dispatch(commonsApi.util.upsertQueryData('getPreparationHistory', { slug: 'test', participantId: id }, { items: [], next_cursor: null }));
  }
  refreshParticipant(s.dispatch, 'a');
  for (const endpoint of ['getParticipant', 'getParticipation', 'getParticipantWorkspace', 'getPreparationHistory']) {
    assert.equal(commonsApi.endpoints[endpoint].select({ slug: 'test', participantId: 'a' })(s.getState()).isUninitialized, true);
    assert.equal(commonsApi.endpoints[endpoint].select({ slug: 'test', participantId: 'b' })(s.getState()).isSuccess, true);
  }
  s.dispatch(commonsApi.util.resetApiState());
});

test('large event bursts use bounded batches and metric refresh preserves statements and topology', async () => {
  const s = store();
  await s.dispatch(commonsApi.util.upsertQueryData('getPropositions', 'test', [node('origin')]));
  const originalRequest = globalThis.Request, originalFetch = globalThis.fetch, originalDocument = globalThis.document;
  globalThis.document = { cookie: '' };
  globalThis.Request = class extends originalRequest {
    constructor(input, options) { super(typeof input === 'string' && input.startsWith('/') ? `http://localhost${input}` : input, options); }
  };
  const requests = [];
  globalThis.fetch = async request => {
    const url = new URL(request.url); requests.push(url);
    const data = url.pathname.endsWith('/graph-metrics') ? [{ id: 'origin', field: { tension: -.5, charge: .5, potential_charge: .2 }, participation_count: 9 }]
      : url.searchParams.getAll('ids[]').map(id => ({ ...node(id), parent_ids: ['origin'] }));
    return new Response(JSON.stringify({ data }), { headers: { 'Content-Type': 'application/json' } });
  };
  try {
    await refreshGraphPropositions(s.dispatch, 'test', Array.from({ length: 205 }, (_, i) => String(i)), () => true);
    assert.deepEqual(requests.map(url => url.searchParams.getAll('ids[]').length), [100, 100, 5]);
    await s.dispatch(commonsApi.util.upsertQueryData('getProposition', { slug: 'test', propositionId: 'origin' }, { ...node('origin'), instruments: [{ id: 'receipt' }] }));
    await s.dispatch(commonsApi.util.upsertQueryData('getProposition', { slug: 'other', propositionId: 'origin' }, node('origin')));
    const before = commonsApi.endpoints.getPropositions.select('test')(s.getState()).data;
    assert.equal(before.length, 206);
    await refreshGraphMetrics(s.dispatch, 'test', () => true);
    const after = commonsApi.endpoints.getPropositions.select('test')(s.getState()).data;
    assert.equal(after[0].field.potential_charge, .2);
    const detail = commonsApi.endpoints.getProposition.select({ slug: 'test', propositionId: 'origin' })(s.getState()).data;
    assert.equal(detail.field.potential_charge, .2, 'live field reaches the persistent inspector cache');
    assert.deepEqual(detail.instruments, [{ id: 'receipt' }], 'full detail is preserved');
    assert.equal(commonsApi.endpoints.getProposition.select({ slug: 'other', propositionId: 'origin' })(s.getState()).data.field, undefined);
    assert.equal(requests.length, 4, 'detail cache refresh introduces no extra HTTP requests');
    assert.equal(after[0].participation_count, 9);
    assert.equal(after[0].statement, 'origin');
    assert.equal(after[1], before[1], 'unaffected cards keep their reference');
    assert.deepEqual(after[1].parent_ids, ['origin']);
    await refreshGraphMetrics(s.dispatch, 'test', () => true);
    assert.equal(commonsApi.endpoints.getPropositions.select('test')(s.getState()).data, after, 'unchanged metrics do not invalidate the graph');
  } finally {
    s.dispatch(commonsApi.util.resetApiState());
    globalThis.Request = originalRequest; globalThis.fetch = originalFetch; globalThis.document = originalDocument;
  }
});
