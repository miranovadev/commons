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
      attestation_summary: { support_direction: 'positive', positive: 3, negative: 2 }, attestation_counts: { support: 3, refute: 2 }, observables: { correspondence: 0, chirality: 0, closure: 0 } };
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
  assert.match(markup, /data-direction="positive"/);
  assert.match(markup, /data-direction="negative"/);
  assert.match(markup, /<textarea/);
  assert.match(markup, /Rationale/);
  assert.match(markup, /Refute with Attestation/);
  assert.doesNotMatch(markup, /Support with Attestation|role="dialog"/);
  assert.doesNotMatch(module.exports.render([]), /<textarea|Attest this Proposition/);
});

test('main page submits a footer action once, retries failures and does not reopen a confirmation', async () => {
  const result = await build({ stdin: { contents: `
    import React from 'react';
    import { renderToStaticMarkup } from 'react-dom/server';
    import { MantineProvider } from '@mantine/core';
    import { MemoryRouter } from 'react-router-dom';
    import { ParticipantPage } from './resources/js/pages/ParticipantPage';
    export function render(harness) {
      globalThis.attestationHarness = harness;
      return renderToStaticMarkup(<MantineProvider env="test"><MemoryRouter><ParticipantPage /></MemoryRouter></MantineProvider>);
    }
  `, resolveDir: process.cwd(), loader: 'tsx' }, plugins: [{ name: 'participation-fixture', setup(build) {
    build.onResolve({ filter: /\/realtime\/useRealtime$/ }, () => ({ path: 'realtime', namespace: 'fixture' }));
    build.onResolve({ filter: /\/services\/commonsApi$/ }, () => ({ path: 'api', namespace: 'fixture' }));
    build.onResolve({ filter: /\/components\/ParticipantWorkspace$/ }, () => ({ path: 'workspace', namespace: 'fixture' }));
    build.onLoad({ filter: /.*/, namespace: 'fixture' }, ({ path }) => ({ contents: path === 'realtime' ? 'export const useParticipantRealtime = () => {};' : path === 'workspace'
      ? 'export function ParticipantWorkspace(props) { globalThis.attestationHarness.workspace = props; return null; }'
      : `export const useGetParticipantsQuery = () => ({ data: [] });
         export const useGetSessionQuery = () => ({ data: null });
         export const useGetParticipantQuery = () => ({ data: { callsign: 'Viewer' } });
         export const useGetParticipationQuery = () => ({ data: globalThis.attestationHarness.participation });
         export const useContributeMutation = () => [globalThis.attestationHarness.contribute, { isLoading: false, reset() {} }];
         export const useEnterCycleMutation = () => [() => {}, { reset() {} }];` }));
  } }], bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
  const module = { exports: {} };
  new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
  const calls = [];
  let resolve, reject;
  const actions = { 'attestation.create': { properties: { proposition_id: { enum: ['p'] }, direction: { enum: ['support', 'refute'] } } } };
  const harness = {
    participation: { status: 'ready', plan: { gate: { id: 'gate', statement: 'Gate' }, phase: { id: 'phase', kind: 'persist' }, schema: {
      oneOf: [{ $ref: '#/$defs/actions/attestation.create' }], $defs: { actions },
    } } },
    contribute(input) {
      calls.push(input);
      return { unwrap: () => new Promise((yes, no) => { resolve = yes; reject = no; }) };
    },
  };
  module.exports.render(harness);
  const click = () => harness.workspace.onAction('attestation.create', { proposition_id: 'p', direction: 'support' });
  click(); click();
  assert.equal(calls.length, 1);
  assert.deepEqual(calls[0].contribution, { action: 'attestation.create', proposition_id: 'p', direction: 'support' });
  reject(new Error('Try again'));
  await new Promise(setImmediate);
  click();
  assert.equal(calls.length, 2);
  resolve({});
  await new Promise(setImmediate);
  click();
  assert.equal(calls.length, 2, 'completed phase is protected until the refreshed plan arrives');
  delete globalThis.attestationHarness;
});
