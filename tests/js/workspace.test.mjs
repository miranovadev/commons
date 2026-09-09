import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

test('human Write cards render Support and Refute for a schema-authorized historical target', async () => {
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
        export function render(allowed) {
          const plan = {
            gate, phase: { number: 2, kind: 'persist', write_register: 'frontier' },
            workspace: { domain: 'emergence', registers: {} },
            write_options: [], attestation_options: [target],
            schema: { $defs: { actions: allowed ? { 'attestation.create': { properties: { proposition_id: { enum: ['historical'] } } } } : {} } },
          };
          return renderToStaticMarkup(<MantineProvider env="test"><ParticipantWorkspace plan={plan} availableActions={allowed ? ['attestation.create'] : []} onAction={() => {}} /></MantineProvider>);
        }
      `,
      resolveDir: root,
      loader: 'tsx',
    },
    bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic',
  });
  const require = createRequire(import.meta.url);
  const module = { exports: {} };
  new Function('require', 'module', 'exports', result.outputFiles[0].text)(require, module, module.exports);
  const markup = module.exports.render(true);
  assert.match(markup, /Historical target/);
  assert.match(markup, /<button\b[^>]*>(?:(?!<\/button>)[\s\S])*>Support<\/span>/);
  assert.match(markup, /<button\b[^>]*>(?:(?!<\/button>)[\s\S])*>Refute<\/span>/);
  assert.doesNotMatch(module.exports.render(false), />Support<\/span>/);
});
