import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { quickContribution, receiptChoices } from '../../resources/js/participationOptions.ts';

const schema = { $defs: { actions: {} } };
test('Receipt references do not offer standalone loading', () => {
  assert.equal(quickContribution({ schema }, 'receipt.read', { receipt_id: 'available' }), null);
});

test('Receipt panel is metadata-only except for active loaded evidence', async () => {
  const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
  const result = await build({ stdin: { contents: `
    import React from 'react';
    import { renderToStaticMarkup } from 'react-dom/server';
    import { MantineProvider } from '@mantine/core';
    import { ReceiptContextPanel } from './resources/js/components/ReceiptContextPanel';
    export const render = (plan, canRead = true) => renderToStaticMarkup(<MantineProvider env="test"><ReceiptContextPanel plan={plan} /></MantineProvider>);
  `, resolveDir: root, loader: 'tsx' }, bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
  const module = { exports: {} };
  new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
  const receipts = [
    { id: 'available', type: 'citation', reference: 'Outside context', status: 'available' },
    { id: 'loaded', type: 'hyperlink', reference: 'Remembered', status: 'loaded', loaded: true, active: true, availability: 'consumed', attached_proposition_id: 'classified', evidence: { statement: 'Preloaded evidence body', exhausted_committals: [{ id: 'old', statement: 'Historical Committal loaded by reference', register: 'canon', phase_number: 7 }] } },
    { id: 'present', type: 'citation', reference: 'In lineage', status: 'already-in-context' },
    { id: 'failed', type: 'hyperlink', reference: 'Failed verification', status: 'unqualified' },
  ];
  const html = module.exports.render({ schema, receipts });
  assert.equal([...html.matchAll(/>Read Receipt</g)].length, 0);
  assert.match(html, /Preloaded evidence body/);
  const inactive = receipts.map(item => ({ ...item, active: false }));
  assert.doesNotMatch(module.exports.render({ schema, receipts: inactive }), /Preloaded evidence body|Historical Committal loaded by reference/);
  assert.match(module.exports.render({ schema, receipts: inactive }), />Loaded</);
  assert.match(html, />Loaded</);
  assert.match(html, />Consumed</);
  assert.match(html, /Historical Committal loaded by reference/);
  assert.match(html, /already in this context/);
  assert.match(html, /Failed verification/);
  assert.doesNotMatch(module.exports.render({ receipts, schema: {} }), />Read Receipt</);
  assert.doesNotMatch(module.exports.render({ receipts, schema }, false), />Read Receipt</);
  const reference = { id: 'attached', type: 'hyperlink', reference: 'Attached resource', evidence: { statement: 'UNREAD BODY' } };
  const referenced = { receipts: [], gate: { id: 'gate', receipts: [reference] }, schema: { $defs: { actions: { 'receipt.read': { properties: { receipt_id: { enum: ['attached'] } } } } } } };
  const referenceHtml = module.exports.render(referenced);
  assert.match(referenceHtml, /Attached resource/);
  assert.match(referenceHtml, /Referenced by gate/);
  assert.equal([...referenceHtml.matchAll(/>Read Receipt</g)].length, 0);
  assert.doesNotMatch(referenceHtml, /UNREAD BODY/);
  assert.doesNotMatch(module.exports.render({ ...referenced, schema: {} }), />Read Receipt</);
  assert.equal(receiptChoices(referenced)[0].id, 'attached');
  assert.equal(receiptChoices(referenced)[0].evidence, undefined);
  assert.equal(receiptChoices({ ...referenced, receipts: [{ ...reference, status: 'loaded' }] }).length, 1);
  assert.doesNotMatch(module.exports.render({ receipts: [{ ...reference, status: 'available' }], schema: {} }), /UNREAD BODY/);

  const memory = { id: 'memory', type: 'tragedy', reference: 'Catastrophe', loaded: true, memory_active: true,
    active: true, evidence: { statement: 'MUST NOT BE EVIDENCE' }, availability: 'consumed', status: 'loaded',
    tragedy_memory: { earlier_history_omitted: true, committed_chain: [{ proposition_id: 'owner', statement: 'Committed failed path', register: 'canon', domain: 'emergence', tension: 0.5 }],
      catastrophe: { phase: 2, statement: 'Historical catastrophe endpoint', tension: -1, charge: 0, recorded_field_available: true } } };
  const remembered = module.exports.render({ receipts: [memory], schema });
  assert.match(remembered, /Committed failed path/);
  assert.match(remembered, /Historical catastrophe endpoint/);
  assert.match(remembered, /Historical Tension -1/);
  assert.match(remembered, /Earlier committed history omitted/);
  assert.doesNotMatch(remembered, /MUST NOT BE EVIDENCE/);
  assert.doesNotMatch(module.exports.render({ receipts: [{ ...memory, loaded: false, memory_active: false }], schema }), /Committed failed path|Historical catastrophe endpoint/);

  const failedPreparation = { id: 'failure-observation', type: 'preparation', reference: 'execution', status: 'loaded', loaded: true,
    preparation_history: { purpose: 'Investigate the source', participant_id: 'actor', originating_gate_id: 'gate',
      selections: [{ receipt_id: 'source', artifact_id: 'hash', read_type: 'text', parameters: { offset: 7, max_chars: 20000 } }],
      deliveries: [], outcome: { type: 'failed' }, content_verified: false,
      failure: { stage: 'read', classification: 'reader_failure', method: 'native', version: '2' },
      failure_reason: '<script>untrusted diagnostic</script>' } };
  const failedHtml = module.exports.render({ receipts: [failedPreparation], schema });
  assert.match(failedHtml, /Outcome: failed/);
  assert.match(failedHtml, /Operational failure/);
  assert.match(failedHtml, /does not establish source support or refutation/);
  assert.match(failedHtml, /Requested offset 7/);
  assert.match(failedHtml, /Requested maximum 20000/);
  assert.doesNotMatch(failedHtml, /Presented using|<script>/);
  assert.match(failedHtml, /&lt;script&gt;/);

});
