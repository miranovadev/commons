import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

test('the participant page offers Rest only when the authoritative schema exposes it', async () => {
  const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
  const result = await build({
    stdin: {
      contents: `
        import React from 'react';
        import { renderToStaticMarkup } from 'react-dom/server';
        import { MantineProvider } from '@mantine/core';
        import { MemoryRouter } from 'react-router-dom';
        import { ParticipantPage } from './resources/js/pages/ParticipantPage';
        import { setParticipation } from './resources/js/services/commonsApi';
        export function render(action, rest_available, rest_reason, alternative) {
          setParticipation({ status: 'ready',
            gate: { id: 'gate', register: 'canon', tier: 'expressive', statement: 'Gate' },
            phase: { id: 'phase', kind: 'order', number: 7, write_register: 'canon' },
            rest_available, rest_reason, traversal_options: [],
            context: { participant: {id:'viewer',callsign:'Viewer'}, receipts:[], read:{propositions:[]},write:{propositions:[]},
              gate:{acting_id:'gate',propositions:[{ id: 'spent', statement: 'A route we learned from', tier: 'expressive', register: 'canon', role: 'gate', phase_number: 6, statement_truncated: false,
              exhausted: true, basin_exhausted: false, exhausted_at: '2026-09-16', actionable: false, receipts:[] }]}},
            schema: {
              oneOf: [{ $ref: '#/$defs/actions/' + action }],
              $defs: { actions: { [action]: { title: action, properties: { action: { const: action } } }, ...(alternative ? { [alternative]: { title: alternative, properties: { action: { const: alternative } } } } : {}) } },
            },
          });
          return renderToStaticMarkup(<MantineProvider env="test"><MemoryRouter><ParticipantPage /></MemoryRouter></MantineProvider>);
        }
      `,
      resolveDir: root, loader: 'tsx',
    },
    plugins: [{
      name: 'participation-api-fixture',
      setup(build) {
        build.onLoad({ filter: /realtime\/useRealtime\.ts$/ }, () => ({ contents: 'export const useParticipantRealtime = () => {};', loader: 'js' }));
    build.onLoad({ filter: /services\/commonsApi\.ts$/ }, () => ({
          contents: `
            let participation;
            export function setParticipation(value) { participation = value; }
            export const useGetPreparationHistoryQuery = () => ({ currentData: { items: [], next_cursor: null } });
export const useGetPreparationQuery = () => ({});
      export const useGetParticipantsQuery = () => ({ data: [] });
         export const useGetSessionQuery = () => ({ data: null });
         export const useGetParticipantQuery = () => ({ data: { id: 'viewer', callsign: 'Viewer', max_read_count: 25 } });
            export const useGetParticipationQuery = (...args) => useGetParticipantWorkspaceQuery(...args);
export const useGetParticipantWorkspaceQuery = () => ({ data: participation });
            export const useReadPreparationMutation = () => [() => {}, { reset() {} }];
         export const useAcknowledgePreparationMutation = () => [() => {}, { reset() {} }];
         export const useContributeMutation = () => [() => {}, { reset() {} }];
            export const useEnterCycleMutation = () => [() => {}, {}];
          `,
          loader: 'ts',
        }));
      },
    }],
    bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic',
  });
  const require = createRequire(import.meta.url);
  const module = { exports: {} };
  new Function('require', 'module', 'exports', result.outputFiles[0].text)(require, module, module.exports);
  for (const action of ['proposition.variant', 'proposition.reclassify', 'proposition.revise', 'attestation.create', 'proposition.traverse', 'proposition.reconcile', 'instrument.execute', 'proposition.commit']) {
    const markup = module.exports.render(action, false);
    assert.doesNotMatch(markup, /No available move|>Rest<\/span>/);
  }
  // No independent client inference from an empty traversal set or rest_available flag.
  assert.doesNotMatch(module.exports.render('proposition.variant', true), /No available move|>Rest<\/span>/);
  const exhausted = module.exports.render('participant.rest', true);
  assert.match(exhausted, /No available move/);
  assert.doesNotMatch(exhausted, /Tragic Region/);
  assert.match(exhausted, /Exhausted continuation/);
  assert.match(exhausted, /A route we learned from/);
  assert.match(exhausted, /data-proposition-id="spent"/);
  assert.match(exhausted, /aria-label="Open No available move · Rest"/);
  const archived = module.exports.render('participant.rest', true, 'no_remaining_potential');
  assert.match(archived, /Open No available move · Rest/);
  assert.doesNotMatch(archived, /No remaining Potential/);
  const optional = module.exports.render('participant.rest', true, 'no_available_move', 'proposition.traverse');
  assert.match(optional, /Open Encounter complete · Rest/);
  assert.doesNotMatch(optional, /No available move/);
  // Even a stale/absent Rest flag cannot hide an authoritative Rest action.
  assert.match(module.exports.render('participant.rest', false), /aria-label="Open No available move · Rest"/);
  assert.match(module.exports.render('participant.rest', undefined), /aria-label="Open No available move · Rest"/);
});
