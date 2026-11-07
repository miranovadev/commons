import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';
import { Window } from 'happy-dom';

const bundle = await build({ stdin: { contents: `
  import React from 'react';
  import { renderToStaticMarkup } from 'react-dom/server';
  import { MantineProvider } from '@mantine/core';
  import { ParticipantWorkspace } from './resources/js/components/ParticipantWorkspace';
  export function render(packet) {
    return renderToStaticMarkup(<MantineProvider env="test"><ParticipantWorkspace plan={packet}
      availableActions={Object.keys(packet.schema.$defs.actions)} onAction={() => {}} onForm={() => {}} /></MantineProvider>);
  }`, resolveDir: process.cwd(), loader: 'tsx' }, bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
const module = { exports: {} };
new Function('require', 'module', 'exports', bundle.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);

const cases = [
  ['distinguish', 'instrument.execute', 'gate', 'Instrument'],
  ['persist', 'attestation.create', 'write', 'Refute'],
  ['constrain', 'proposition.traverse', 'write', 'Traverse'],
  ['disperse', 'proposition.revise', 'write', 'Revise'],
  ['mediate', 'proposition.reconcile', 'read', 'Reconcile'],
  ['bias', 'proposition.reclassify', 'gate', 'Reclassify'],
  ['order', 'proposition.commit', 'write', 'Commit'],
];
for (const [index, [kind, action, role, label]] of cases.entries()) test(`${kind} renders exactly the published roles, questions, evidence and target`, () => {
  const node = (id, relation) => ({ id, relation, register:'canon', tier:'expressive', statement:'Statement '+id,
    participant:{id:'author',callsign:'Vela'}, receipts:['receipt'], explored:true, attestation:{direction:'support', rationale:'I observed this.'},
    observables:{closure:0.12345}, field:{tension:0.98765} });
  const context = { participant:{id:'viewer',callsign:'Claire'}, receipts:[{id:'receipt',type:'citation',reference:'source',
    summary:{target_id:'source'},loaded:true,loaded_inline_content:{statement:'Evidence from Read'}}],
    read:{question:'Interpret this pair?',propositions:[node('parent','causal'),node('counterpart','counterpart')]},
    gate:{acting_id:'here',propositions:[node('here','acting')]},write:{question:'What follows?',propositions:[node('next')]} };
  const target = {read:'counterpart',gate:'here',write:'next'}[role];
  const prop = action === 'proposition.reconcile' ? 'other_proposition_id' : 'proposition_id';
  const definition = {properties:{[prop]:{enum:[target]}}};
  if (action === 'attestation.create') definition.anyOf = [{properties:{proposition_id:{enum:[target]},direction:{enum:['refute']}}}];
  const html = module.exports.render({phase:{kind,number:index+1,write_register:'canon',id:'phase'},context,
    schema:{$defs:{actions:{[action]:definition}}}, workspace:{registers:{canon:[node('hidden')]}}});
  const document = new Window().document;
  document.body.innerHTML = html;
  for (const role of ['read','gate','write']) {
    assert.deepEqual([...document.querySelectorAll(`[data-role="${role}"] article`)].map(card=>card.dataset.propositionId),
      context[role].propositions.map(item=>item.id));
  }
  assert.match(document.body.textContent,/Interpret this pair\?/);
  assert.match(document.body.textContent,/What follows\?/);
  for (const word of ['causal','counterpart','Acting Gate','Vela','I observed this.','Explored by you','Evidence from Read']) {
    assert.ok(document.body.textContent.includes(word),word);
  }
  assert.doesNotMatch(html,/0\.12345|0\.98765|Field State|Closure preview|heatmap|Statement hidden|directional-whirl/);
  const buttons = [...document.querySelectorAll('button')].filter(b => b.textContent === label);
  assert.deepEqual(buttons.map(b=>b.closest('article')?.dataset.propositionId),[target]);
  assert.ok(![...document.querySelectorAll('button')].some(b=>b.textContent==='Support'));
});
