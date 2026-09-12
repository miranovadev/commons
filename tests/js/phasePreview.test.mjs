import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
const result = await build({ stdin: { contents: `
  import React from 'react';
  import { renderToStaticMarkup } from 'react-dom/server';
  import { MantineProvider } from '@mantine/core';
  import { ParticipantWorkspace } from './resources/js/components/ParticipantWorkspace';
  import { PhaseProgress } from './resources/js/components/PhaseIdentity';
  export { phaseRegisterPreview, canPreviewPhase } from './resources/js/phasePreview';
  export function render(plan, previewKind) {
    return renderToStaticMarkup(<MantineProvider env="test"><ParticipantWorkspace plan={plan} previewKind={previewKind}
      availableActions={Object.keys(plan.schema.$defs.actions)} onAction={() => { throw Error('Preview must not act'); }}
      onSubmit={async () => { throw Error('Preview must not submit'); }} status={<span>LIVE STATUS</span>} /></MantineProvider>);
  }
  export function stepper(current, selected = current, busy = false) {
    const calls = [];
    const element = PhaseProgress({ kind: current, selectedKind: selected, busy, onSelect: kind => calls.push(kind) });
    for (let i = 0; i < 7; i++) element.props.onStepClick(i);
    return { calls, html: renderToStaticMarkup(<MantineProvider env="test">{element}</MantineProvider>) };
  }
`, loader: 'tsx', resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const ui = module.exports;
const css = readFileSync('resources/css/workspace.css', 'utf8');
function fixture(domain = 'settlement') {
  const registers = domain === 'settlement' ? ['signal', 'stance', 'concord'] : ['activity', 'frontier', 'canon'];
  const nodes = registers.map((register, index) => ({ id: 'p' + index, tier: 'expressive', register, statement: 'CARD ' + register }));
  const actions = { 'proposition.variant': { properties: { statement: {} } }, 'attestation.create': { properties: { proposition_id: { enum: ['p2'] }, direction: { enum: ['support', 'refute'] } } } };
  return { phase: { id: 'live', kind: 'order', number: 7, write_register: registers[2] }, gate: nodes[1], lineage: nodes.slice(0, 2),
    write_options: [nodes[2]], workspace: { domain, registers: { [registers[1]]: [nodes[1]] } }, schema: { $defs: { actions } } };
}
function region(html, role) {
  const start = html.indexOf('data-role="' + role + '"');
  const end = html.indexOf('data-role="', start + 10);
  return html.slice(start, end < 0 ? undefined : end);
}
for (const domain of ['emergence', 'settlement']) test(`register preview reorganizes only loaded ${domain} cards without changing the plan`, () => {
  const plan = fixture(domain);
  const original = structuredClone(plan);
  const registers = domain === 'settlement' ? ['signal', 'stance', 'concord'] : ['activity', 'frontier', 'canon'];
  const mediate = ui.phaseRegisterPreview(plan, 'mediate');
  assert.equal(mediate.write.register, registers[1]);
  assert.equal(mediate.gate.register, registers[0]);
  assert.equal(mediate.read.register, registers[2]);
  assert.equal(mediate.write.cards.length, 1, 'duplicate loaded copies appear only once');
  const html = ui.render(plan, 'mediate');
  for (const role of ['read', 'gate', 'write']) assert.match(region(html, role), new RegExp('CARD ' + mediate[role].register));
  assert.doesNotMatch(html, /LIVE STATUS|Generative Write actions|write-option-card__actions|>Reconcile<\/span>/);
  assert.match(html, /data-preview="true"/);
  assert.deepEqual(plan, original);
  assert.equal(ui.phaseRegisterPreview(plan, 'order'), null);
  assert.match(ui.render(plan), /LIVE STATUS/);
  assert.match(ui.render(plan), /write-option-card__actions/);
});
test('missing provenance/register data remains empty instead of borrowing a current Gate', () => {
  const plan = fixture();
  plan.lineage = []; plan.workspace.registers = {}; plan.write_options = [];
  const preview = ui.phaseRegisterPreview(plan, 'disperse');
  assert.deepEqual(preview.write.cards, []);
  assert.match(region(ui.render(plan, 'disperse'), 'write'), /No cards from this register are loaded/);
  assert.match(region(ui.render(plan, 'distinguish'), 'read'), /No cards from this register are loaded/);
  plan.phase.kind = 'persist';
  assert.equal(ui.phaseRegisterPreview(plan, 'mediate'), null);
});
test('Stepper allows prior/current views but never future steps or changes while busy', () => {
  const { calls, html } = ui.stepper('mediate', 'persist');
  assert.deepEqual(calls, ['distinguish', 'persist', 'constrain', 'disperse', 'mediate']);
  const steps = [...html.matchAll(/<button\b[^>]*data-state="[^"]+"[^>]*>/g)].map(match => match[0]);
  assert.equal(steps.length, 7);
  assert.ok(steps.slice(0, 5).every(step => !step.includes('disabled')));
  assert.ok(steps.slice(5).every(step => step.includes('disabled')));
  assert.match(steps[1], /data-preview="true"/);
  assert.match(steps[4], /aria-current="step"/);
  assert.deepEqual(ui.stepper('mediate', 'mediate', true).calls, []);
});
test('Write tools are outside card content and scroll thumbs hide without changing their gutter', () => {
  const write = region(ui.render(fixture()), 'write');
  assert.ok(write.indexOf('write-row__tools') < write.indexOf('LIVE STATUS'));
  assert.ok(write.indexOf('LIVE STATUS') < write.indexOf('register-row__content'));
  assert.ok(write.indexOf('Generative Write actions') < write.indexOf('register-row__content'));
  assert.match(css, /register-row__content[^}]*overflow:visible/);
  assert.match(css, /proposition-navigator__viewport[^}]*overflow-x:auto; overflow-y:hidden/);
  assert.match(css, /scrollbar-color:transparent transparent/);
  assert.match(css, /:is\(:hover,:focus-within\)[^}]*scrollbar-color:var/);
  assert.match(css, /cycle-context-header__phase \{ grid-column:2; text-align:center/);
});
