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
        export function render(effective, allowed = true, auxiliary = false, kind = 'persist') {
          const directions = ['refute', 'support'].filter(direction => direction !== effective);
          const traversalAllowed = ['distinguish', 'persist', 'constrain', 'bias'].includes(kind);
          const actions = allowed ? {
            'attestation.create': {
              properties: { proposition_id: { enum: [target.id] }, direction: { enum: ['refute', 'support'] } },
              anyOf: directions.map(direction => ({ properties: { proposition_id: { enum: [target.id] }, direction: { enum: [direction] } } })),
            },
            ...(effective && traversalAllowed ? { 'proposition.traverse': { properties: { proposition_id: { enum: [target.id] } } } } : {}),
            ...(auxiliary ? {
              'proposition.revise': { properties: { proposition_id: { enum: [target.id] } } },
              'instrument.execute': { properties: { proposition_id: { enum: [target.id] } } },
            } : {}),
          } : {};
          const unrelated = { ...target, id: 'unrelated', statement: 'Unrelated horizon proposition' };
          const plan = {
            gate, phase: { number: kind === 'persist' ? 2 : 4, kind, write_register: 'frontier' },
            workspace: { domain: 'emergence', registers: { frontier: [unrelated] } },
            // Deliberately omit attestation metadata: schema drives the buttons.
            write_options: [target], attestation_options: [target], traversal_options: effective ? [target] : [],
            gate_options: [unrelated], instrument_options: [target, unrelated], revision_options: [target],
            schema: { $defs: { actions } },
          };
          const availableActions = [...Object.keys(actions), ...(effective && !traversalAllowed ? ['proposition.traverse'] : [])];
          return renderToStaticMarkup(<MantineProvider env="test"><ParticipantWorkspace plan={plan} availableActions={availableActions} onAction={() => {}} /></MantineProvider>);
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
    for (const auxiliary of [false, true]) {
      const markup = module.exports.render(effective, true, auxiliary);
      assert.match(markup, /Historical target/);
      assert.doesNotMatch(markup, /Unrelated horizon proposition/);
      assert.deepEqual([...markup.matchAll(/>(Support|Refute|Traverse)<\/span>/g)].map(match => match[1]), expected);
      assert.match(markup, /write-option-card__actions--directional/);
      const footer = markup.slice(markup.indexOf('write-option-card__actions--directional'));
      const left = footer.slice(footer.indexOf('data-action-region="left"'), footer.indexOf('data-action-region="instrument"'));
      const instrument = footer.slice(footer.indexOf('data-action-region="instrument"'), footer.indexOf('data-action-region="right"'));
      const right = footer.slice(footer.indexOf('data-action-region="right"'));
      const middle = markup.slice(markup.indexOf('data-action-region="middle"'), markup.indexOf('write-option-card__actions--directional'));
      assert.match(left, new RegExp('>' + expected[0] + '</span>'));
      assert.match(right, new RegExp('>' + expected[1] + '</span>'));
      assert.doesNotMatch(middle, />(Support|Refute|Traverse)<\/span>/);
      assert.doesNotMatch(left + right, />(Revise|Instrument)<\/span>/);
      if (auxiliary) {
        assert.match(middle, />Revise<\/span>/);
        assert.match(instrument, />Instrument<\/span>/);
      } else {
        assert.doesNotMatch(markup, />(Revise|Instrument)<\/span>/);
      }
    }
  }
  assert.doesNotMatch(module.exports.render(null, false), /Historical target|>(Support|Refute|Traverse)<\/span>/);
  for (const kind of ['disperse', 'mediate']) {
    // The schema is authoritative even if a stale caller lists Traverse as available.
    const markup = module.exports.render('support', true, false, kind);
    assert.doesNotMatch(markup, />Traverse<\/span>/);
    assert.match(markup, />Refute<\/span>/);
  }
});
