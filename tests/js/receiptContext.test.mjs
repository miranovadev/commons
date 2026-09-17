import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { quickContribution } from '../../resources/js/participationOptions.ts';

const schema = { $defs: { actions: { 'receipt.read': { properties: { receipt_id: { enum: ['available'] } } } } } };
test('Receipt Read sends only the authorized Receipt ID', () => {
  assert.deepEqual(quickContribution({ schema }, 'receipt.read', { receipt_id: 'available', proposition_id: 'injected', rationale: 'injected' }),
    { action: 'receipt.read', receipt_id: 'available' });
  assert.equal(quickContribution({ schema }, 'receipt.read', { receipt_id: 'loaded' }), null);
  assert.equal(quickContribution({ schema: {} }, 'receipt.read', { receipt_id: 'available' }), null);
});

test('Receipt panel exposes only authorized Reads and preloads evidence without duplicate buttons', async () => {
  const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
  const result = await build({ stdin: { contents: `
    import React from 'react';
    import { renderToStaticMarkup } from 'react-dom/server';
    import { MantineProvider } from '@mantine/core';
    import { ReceiptContextPanel } from './resources/js/components/ReceiptContextPanel';
    export const render = (plan, canRead = true) => renderToStaticMarkup(<MantineProvider env="test"><ReceiptContextPanel plan={plan} canRead={canRead} onRead={() => {}} /></MantineProvider>);
  `, resolveDir: root, loader: 'tsx' }, bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
  const module = { exports: {} };
  new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
  const receipts = [
    { id: 'available', type: 'citation', reference: 'Outside context', status: 'available' },
    { id: 'loaded', type: 'tragedy', reference: 'Remembered', status: 'loaded', availability: 'consumed', attached_proposition_id: 'classified', evidence: { statement: 'Preloaded evidence body', exhausted_committals: [{ id: 'old', statement: 'Historical Committal loaded by reference', register: 'canon', phase_number: 7 }] } },
    { id: 'present', type: 'citation', reference: 'In lineage', status: 'already-in-context' },
    { id: 'failed', type: 'hyperlink', reference: 'Failed verification', status: 'unqualified' },
  ];
  const html = module.exports.render({ schema, receipts });
  assert.equal([...html.matchAll(/>Read Receipt</g)].length, 1);
  assert.match(html, /Preloaded evidence body/);
  assert.match(html, />Loaded</);
  assert.match(html, />Consumed</);
  assert.match(html, /Historical Committal loaded by reference/);
  assert.match(html, /already in this context/);
  assert.match(html, /Failed verification/);
  assert.doesNotMatch(module.exports.render({ receipts, schema: {} }), />Read Receipt</);
  assert.doesNotMatch(module.exports.render({ receipts, schema }, false), />Read Receipt</);
});
