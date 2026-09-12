import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';

const result = await build({ stdin: { contents: `
  export { configureStore } from '@reduxjs/toolkit';
  export { commonsApi } from './resources/js/services/commonsApi';
  export { refreshParticipant, refreshGraphPropositions } from './resources/js/realtime/cacheActions';
`, resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false });
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const { configureStore, commonsApi, refreshParticipant, refreshGraphPropositions } = module.exports;
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
  }
  refreshParticipant(s.dispatch, 'a');
  for (const endpoint of ['getParticipant', 'getParticipation']) {
    assert.equal(commonsApi.endpoints[endpoint].select({ slug: 'test', participantId: 'a' })(s.getState()).isUninitialized, true);
    assert.equal(commonsApi.endpoints[endpoint].select({ slug: 'test', participantId: 'b' })(s.getState()).isSuccess, true);
  }
  s.dispatch(commonsApi.util.resetApiState());
});
