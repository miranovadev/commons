import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';

test('cards show directional counts and the rationale panel obeys schema permissions', async () => {
  const result = await build({ stdin: { contents: `
    import React from 'react';
    import { renderToStaticMarkup } from 'react-dom/server';
    import { MantineProvider } from '@mantine/core';
    import { PropositionCard } from './resources/js/components/PropositionCard';
    import { AttestationPanel } from './resources/js/components/AttestationPanel';
    const proposition = { id: 'p', tier: 'expressive', register: 'frontier', statement: 'Proposition',
      attestation_counts: { support: 3, refute: 2 }, observables: { correspondence: 0, chirality: 0, closure: 0 } };
    export function render(directions) {
      const plan = { schema: { $defs: { actions: { 'attestation.create': {
        properties: { proposition_id: { enum: ['p'] }, direction: { enum: directions } }
      } } } } };
      return renderToStaticMarkup(<MantineProvider env="test"><PropositionCard proposition={proposition} />
        <AttestationPanel plan={plan} proposition={proposition} onSubmit={async () => true} /></MantineProvider>);
    }
  `, resolveDir: process.cwd(), loader: 'tsx' }, bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
  const module = { exports: {} };
  new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
  const markup = module.exports.render(['refute']);
  assert.match(markup, /aria-label="support: 3"/);
  assert.match(markup, /aria-label="refute: 2"/);
  assert.match(markup, /attestation-direction-icon--support/);
  assert.match(markup, /attestation-direction-icon--refute/);
  assert.match(markup, /<textarea/);
  assert.match(markup, /Rationale/);
  assert.match(markup, /Refute with Attestation/);
  assert.doesNotMatch(markup, /Support with Attestation|role="dialog"/);
  assert.doesNotMatch(module.exports.render([]), /<textarea|Attest this Proposition/);
});
