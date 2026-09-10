import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

test('Mediate counterparts and unary Bias choices use their own schema-authorized Gate populations', async () => {
  const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
  const result = await build({
    stdin: {
      contents: `
        import React from 'react';
        import { renderToStaticMarkup } from 'react-dom/server';
        import { MantineProvider } from '@mantine/core';
        import { ParticipantWorkspace } from './resources/js/components/ParticipantWorkspace';
        export function render(kind, authorized = true) {
          const gateRegister = kind === 'bias' ? 'frontier' : 'activity';
          const writeRegister = kind === 'bias' ? 'canon' : 'frontier';
          const gate = { id: 'gate', register: gateRegister, tier: 'expressive', statement: 'Privileged Gate' };
          const sibling = { ...gate, id: 'sibling', statement: 'Sibling alternative' };
          const unrelated = { ...gate, id: 'unrelated', statement: 'Unrelated alternative' };
          const read = { ...gate, id: 'read', register: 'canon', statement: 'Disperse Read anchor' };
          const readSibling = { ...read, id: 'read-sibling', statement: 'Matching Read sibling' };
          const child = { ...gate, id: 'child', register: writeRegister, statement: 'Forward child' };
          const gateAction = kind === 'bias' ? 'proposition.bias' : 'proposition.reconcile';
          const target = kind === 'bias' ? 'proposition_id' : 'other_proposition_id';
          const choices = kind === 'bias' ? ['gate', 'sibling'] : ['sibling'];
          const actions = {
            'attestation.create': { properties: { proposition_id: { enum: ['child'] }, direction: { enum: ['support', 'refute'] } } },
            ...(authorized ? { [gateAction]: { required: ['action', target], properties: { action: { const: gateAction }, [target]: { enum: choices } } } } : {}),
          };
          const plan = { gate, phase: { kind, number: kind === 'bias' ? 6 : 5, write_register: writeRegister },
            read_options: kind === 'mediate' ? [read, readSibling] : [],
            gate_options: [sibling, unrelated], write_options: [child], attestation_options: [child],
            workspace: { domain: 'emergence', registers: { [gateRegister]: [unrelated] } },
            schema: { $defs: { actions } },
          };
          return renderToStaticMarkup(<MantineProvider env="test"><ParticipantWorkspace plan={plan} availableActions={Object.keys(actions)} onAction={() => {}} /></MantineProvider>);
        }
      `,
      resolveDir: root, loader: 'tsx',
    },
    bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic',
  });
  const module = { exports: {} };
  new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
  for (const kind of ['mediate', 'bias']) {
    const markup = module.exports.render(kind);
    if (kind === 'mediate') {
      const read = markup.slice(markup.indexOf('data-role="read"'), markup.indexOf('data-role="gate"'));
      assert.match(read, /Disperse Read anchor/);
      assert.match(read, /Matching Read sibling/);
      assert.doesNotMatch(read, /Sibling alternative|Forward child/);
    }
    const gate = markup.slice(markup.indexOf('data-role="gate"'), markup.indexOf('data-role="write"'));
    const write = markup.slice(markup.indexOf('data-role="write"'));
    if (kind === 'mediate') assert.match(gate, /proposition-card--selected/);
    else assert.doesNotMatch(gate, /proposition-card--selected/);
    assert.match(gate, /Privileged Gate/);
    assert.match(gate, /Sibling alternative/);
    if (kind === 'mediate') {
      assert.match(gate, /data-reconcile-candidate="sibling"/);
      assert.equal([...gate.matchAll(/>Reconcile<\/span>/g)].length, 1);
    } else {
      assert.match(gate, /data-bias-candidate="gate"/);
      assert.match(gate, /data-bias-candidate="sibling"/);
      assert.equal([...gate.matchAll(/>Bias<\/span>/g)].length, 2);
      assert.doesNotMatch(gate, /Reconcile/);
      assert.equal([...gate.matchAll(/Privileged Gate/g)].length, 1);
    }
    assert.match(write, /Forward child/);
    assert.doesNotMatch(write, /Sibling alternative|>Reconcile<\/span>|>Bias<\/span>/);
    assert.doesNotMatch(markup, /Unrelated alternative/);
    const denied = module.exports.render(kind, false);
    assert.doesNotMatch(denied, /Sibling alternative|>Reconcile<\/span>|>Bias<\/span>/);
    if (kind === 'bias') {
      const deniedGate = denied.slice(denied.indexOf('data-role="gate"'), denied.indexOf('data-role="write"'));
      assert.doesNotMatch(deniedGate, /Privileged Gate/);
      assert.match(deniedGate, /No active Proposition/);
    }
  }
});
