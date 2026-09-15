import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';
const result = await build({ entryPoints: ['resources/js/instrumentInvocations.ts'], bundle: true, platform: 'node', format: 'cjs', write: false });
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const { normalizedInvocation, duplicateInstrument } = module.exports;
const url = reference => ({ kind: 'hyperlink', reference });
test('canonical URLs match without merging query, port, scheme, path case or fragments', () => {
  const original = url('https://example.test/paper?q=1');
  assert.equal(normalizedInvocation(original), normalizedInvocation(url(' https://EXAMPLE.test:443/a/../%70aper?q=1 ')));
  for (const reference of ['http://example.test/paper?q=1', 'https://example.test/paper?q=2', 'https://example.test:8443/paper?q=1', 'https://example.test/Paper?q=1', 'https://example.test/paper?q=1#section']) {
    assert.notEqual(normalizedInvocation(original), normalizedInvocation(url(reference)));
  }
});
test('forms resolve exclusions for the selected target and normalized input only', () => {
  const invocation = { kind: 'web_search', query: 'evidence discovery' };
  const exclusion = { receipt_id: 'receipt', invocation };
  const plan = { schema: { 'x-commons': { instrument_exclusions: { target: [exclusion] } } } };
  assert.equal(duplicateInstrument(plan, 'target', { kind: 'web_search', query: ' evidence\n discovery ' }), exclusion);
  assert.equal(duplicateInstrument(plan, 'other', invocation), undefined);
  assert.equal(duplicateInstrument(plan, 'target', { kind: 'web_search', query: 'different query' }), undefined);
  assert.equal(duplicateInstrument(plan, 'target', { kind: 'citation', reference: 'evidence discovery' }), undefined);
});
