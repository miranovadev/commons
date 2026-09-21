import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';
import { quickContribution } from '../../resources/js/participationOptions.ts';

import { replayReceiptIds } from '../../resources/js/instrumentInvocations.ts';
const schema = { $defs: { actions: { 'instrument.execute': { anyOf: [
  { properties: { instrument: { anyOf: [{ properties: { kind: { enum: ['web_search'] } } }] } } },
  { properties: { proposition_id: { enum: ['target'] }, instrument: { anyOf: [{ properties: { replay_receipt_id: { enum: ['search'] } } }] } } },
] } } } };
test('Replay choices are bound to the exact Instrument target', () => {
  assert.deepEqual(replayReceiptIds({ schema }, 'target'), ['search']);
  assert.deepEqual(replayReceiptIds({ schema }, 'foreign'), []);
  assert.deepEqual(replayReceiptIds({ schema }), []);
  assert.equal(quickContribution({ schema }, 'receipt.replay', { receipt_id: 'search' }), null);
});

const result = await build({ stdin: { contents: `
  import React from 'react';
  import { renderToStaticMarkup } from 'react-dom/server';
  import { MantineProvider } from '@mantine/core';
  import { ReceiptContextPanel } from './resources/js/components/ReceiptContextPanel';
  import { InstrumentFields } from './resources/js/components/InstrumentFields';
  export const panel = (plan, enabled = true) => renderToStaticMarkup(<MantineProvider env="test"><ReceiptContextPanel plan={plan} /></MantineProvider>);
  export const fields = (value, includeCitation = true, replayIds = []) => renderToStaticMarkup(<MantineProvider env="test"><InstrumentFields value={value} onChange={() => {}} includeCitation={includeCitation} replayIds={replayIds} /></MantineProvider>);
  export const change = (value, kind, replayIds = []) => { let next; const tree = InstrumentFields({ value, replayIds, onChange: value => { next = value; } }); tree.props.children[0].props.onChange(kind); return next; };
`, resolveDir: process.cwd(), loader: 'tsx' }, bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const receipts = [
  { id: 'search', type: 'web_search', reference: 'Discover resources', status: 'available' },
  { id: 'citation', type: 'citation', reference: 'Known source', status: 'available' },
  { id: 'loaded', type: 'web_search', reference: 'Loaded query', status: 'loaded', loaded: true, active: true, evidence: { executed_at: '2026-09-14', results: [
    { rank: 1, title: '<script>external title</script>', url: 'https://example.com/paper?q=1', source: 'example.com', excerpt: '<img src=x onerror=alert(1)>', excerpt_truncated: false },
  ] } },
  { id: 'failed', type: 'web_search', reference: 'Failed query', status: 'unqualified', observation_error: 'Web Search is unavailable or unconfigured.' },
];

test('Catalogue metadata never exposes standalone Read or Replay controls', () => {
  const html = module.exports.panel({ schema, receipts });
  assert.equal([...html.matchAll(/>Read Receipt</g)].length, 0);
  assert.equal([...html.matchAll(/>Replay Receipt</g)].length, 0);
  assert.match(html, />Loaded</);
  assert.match(html, /Web Search is unavailable or unconfigured/);
  assert.doesNotMatch(module.exports.panel({ schema, receipts }, false), />Read Receipt<|>Replay Receipt</);
  assert.doesNotMatch(module.exports.panel({ schema: {}, receipts }), />Read Receipt<|>Replay Receipt</);
});

test('Search evidence renders escaped text and resource links', () => {
  const html = module.exports.panel({ schema, receipts });
  assert.match(html, /href="https:\/\/example.com\/paper\?q=1"/);
  assert.match(html, /&lt;script&gt;external title&lt;\/script&gt;/);
  assert.doesNotMatch(html, /<script>external title|<img src=x/);
  assert.match(html, /Observed 2026-09-14/);
});

test('Search has a bounded query field and switches create disjoint Instrument payloads', () => {
  const html = module.exports.fields({ kind: 'web_search', query: 'discovery' });
  assert.match(html, /Search query/);
  assert.match(html, /maxLength="1000"/i);
  assert.doesNotMatch(html, />Reference</);
  assert.deepEqual(module.exports.change({ kind: 'hyperlink', reference: 'https://example.com' }, 'web_search'), { kind: 'web_search', query: '' });
  assert.deepEqual(module.exports.change({ kind: 'web_search', query: 'discarded' }, 'citation'), { kind: 'citation', reference: '' });
  assert.match(module.exports.fields({ kind: 'web_search', query: '' }, false), /Web Search/);
});

test('Replay is a disjoint Instrument form only where the schema supplies choices', () => {
  assert.doesNotMatch(module.exports.fields({ kind: 'citation', reference: '' }), /Replay stored invocation/);
  assert.deepEqual(module.exports.change({ kind: 'web_search', query: 'discarded' }, 'replay', ['search']), { replay_receipt_id: 'search' });
  const html = module.exports.fields({ replay_receipt_id: 'search' }, true, ['search']);
  assert.match(html, /Replay Receipt/);
  assert.doesNotMatch(html, /Search query|>Reference</);
});
