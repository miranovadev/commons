import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

test('Write renders only local cards and exactly the two schema-driven direction slots', async () => {
  const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
  const result = await build({
    stdin: {
      contents: `
        import React from 'react';
        import { renderToStaticMarkup } from 'react-dom/server';
        import { MantineProvider } from '@mantine/core';
        import { ParticipantWorkspace } from './resources/js/components/ParticipantWorkspace';
        const gate = { id: 'gate', register: 'activity', tier: 'expressive', statement: 'Gate' };
        const target = { id: 'historical', register: 'frontier', tier: 'expressive', statement: 'Historical target' };
        export function render(effective, allowed = true) {
          const directions = ['refute', 'support'].filter(direction => direction !== effective);
          const actions = allowed ? {
            'attestation.create': {
              properties: { proposition_id: { enum: [target.id] }, direction: { enum: ['refute', 'support'] } },
              anyOf: directions.map(direction => ({ properties: { proposition_id: { enum: [target.id] }, direction: { enum: [direction] } } })),
            },
            ...(effective ? { 'proposition.traverse': { properties: { proposition_id: { enum: [target.id] } } } } : {}),
          } : {};
          const unrelated = { ...target, id: 'unrelated', statement: 'Unrelated horizon proposition' };
          const plan = {
            gate, phase: { number: 2, kind: 'persist', write_register: 'frontier' },
            workspace: { domain: 'emergence', registers: { frontier: [unrelated] } },
            // Deliberately omit attestation metadata: schema drives the buttons.
            write_options: [target], attestation_options: [target], traversal_options: effective ? [target] : [],
            gate_options: [unrelated], instrument_options: [unrelated],
            schema: { $defs: { actions } },
          };
          return renderToStaticMarkup(<MantineProvider env="test"><ParticipantWorkspace plan={plan} availableActions={Object.keys(actions)} onAction={() => {}} /></MantineProvider>);
        }
      `,
      resolveDir: root, loader: 'tsx',
    },
    bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic',
  });
  const require = createRequire(import.meta.url);
  const module = { exports: {} };
  new Function('require', 'module', 'exports', result.outputFiles[0].text)(require, module, module.exports);
  for (const [effective, expected] of [[null, ['Refute', 'Support']], ['support', ['Refute', 'Traverse']], ['refute', ['Traverse', 'Support']]]) {
    const markup = module.exports.render(effective);
    assert.match(markup, /Historical target/);
    assert.doesNotMatch(markup, /Unrelated horizon proposition/);
    assert.deepEqual([...markup.matchAll(/>(Support|Refute|Traverse)<\/span>/g)].map(match => match[1]), expected);
  }
  assert.doesNotMatch(module.exports.render(null, false), /Historical target|>(Support|Refute|Traverse)<\/span>/);
});
