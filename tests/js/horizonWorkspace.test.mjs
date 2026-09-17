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
  export { workspaceLanes, workspaceDomain } from './resources/js/workspaceLanes';
  export { actionPropositions, quickContribution } from './resources/js/participationOptions';
  export { chronologicalLineage } from './resources/js/lineage';
  export { configureStore } from '@reduxjs/toolkit';
  export { commonsApi } from './resources/js/services/commonsApi';
  export function render(plan, previewKind) {
    return renderToStaticMarkup(<MantineProvider env="test"><ParticipantWorkspace plan={plan} previewKind={previewKind}
      availableActions={Object.keys(plan.schema.$defs.actions)} onAction={() => {}} /></MantineProvider>);
  }
`, loader: 'tsx', resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
const module = { exports: {} };
new Function('require', 'module', 'exports', bundle.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const ui = module.exports;
const node = (id, register, tier = 'expressive') => ({ id, register, tier, statement: id, parent_ids: [] });
const domains = { emergence: ['activity', 'frontier', 'canon'], settlement: ['signal', 'stance', 'concord'] };
function fixture(domain, phase = 7, continuity = false) {
  const registers = domains[domain];
  const other = domains[domain === 'emergence' ? 'settlement' : 'emergence'];
  const position = phase === 7 ? 2 : (phase - 1) % 3;
  const opening = node('boundary-' + domain, other[2], 'committal');
  const provenance = continuity ? opening : node('structural-origin', 'canon', 'committal');
  const gate = phase === 1 ? provenance : node('gate-' + domain, registers[phase === 7 ? 2 : (position + 2) % 3]);
  const history = registers.map(register => node('history-' + register, register));
  const write = node('write-' + domain, registers[position], phase === 7 ? 'committal' : 'expressive');
  return {
    phase: { id: domain + phase, number: phase, kind: ['distinguish', 'persist', 'constrain', 'disperse', 'mediate', 'bias', 'order'][phase - 1], write_register: registers[position] },
    gate, lineage: [provenance, ...history], read_options: [history[phase === 7 ? 1 : (position + 1) % 3]], write_options: [write], gate_options: [gate],
    workspace: { domain, horizon: { id: domain, domain, sequence: domain === 'emergence' ? 3 : 2, parent_context_horizon_id: 'parent', opened_by_proposition_id: opening.id, opening_proposition: opening },
      provenance, registers: Object.fromEntries(history.map(card => [card.register, [card]])) },
    schema: { $defs: { actions: {
      ...(phase === 7 ? {
        'proposition.commit': { properties: { proposition_id: { enum: [gate.id] } } },
        'proposition.traverse': { properties: { proposition_id: { enum: [write.id] } } },
      } : { 'attestation.create': { properties: { proposition_id: { enum: [write.id] }, direction: { enum: ['support', 'refute'] } } } }),
      ...(phase === 6 ? { 'proposition.bias': { properties: { proposition_id: { enum: [gate.id] } } } } : {}),
    } } },
  };
}
function documentFor(plan, preview) {
  const document = new Window().document;
  document.body.innerHTML = ui.render(plan, preview);
  return document;
}
function assertRegisterGroups(document) {
  for (const group of document.querySelectorAll('.register-row')) {
    const register = group.dataset.register;
    if (register) assert.equal(group.querySelector('.register-row__title').textContent.toLowerCase(), register);
    for (const card of group.querySelectorAll('.proposition-card')) assert.equal(card.dataset.register, register);
  }
}
for (const domain of Object.keys(domains)) {
  test(`${domain} exhausted alternatives stay visible in Write with no operational controls`, () => {
    const plan = fixture(domain, 1);
    plan.exhausted_alternatives = ['A', 'B'].map(id => ({
      id, role: 'write', phase_number: 1, register: plan.phase.write_register, tier: 'expressive',
      statement: `${id} was explored`, statement_truncated: false,
      exhausted: true, basin_exhausted: false, exhausted_at: '2026-09-17', actionable: false,
    }));
    const document = documentFor(plan);
    const write = document.querySelector('[data-role="write"]');
    assert.equal(write.querySelectorAll('[data-exhausted-id]').length, 2);
    assert.equal(write.querySelector('.proposition-card').dataset.propositionId, `write-${domain}`);
    for (const scar of write.querySelectorAll('[data-exhausted-id]')) {
      assert.match(scar.textContent, /Exhausted · Context only/);
      assert.equal(scar.querySelector('button, input, [role="button"]'), null);
    }
    assert.deepEqual(ui.actionPropositions(plan, 'attestation.create').map(node => node.id), [`write-${domain}`]);
    assert.equal(ui.quickContribution(plan, 'proposition.traverse', { proposition_id: 'A' }), null);
    assert.equal(ui.quickContribution(plan, 'attestation.create', { proposition_id: 'A', direction: 'support' }), null);
    const next = fixture(domain, 2);
    next.exhausted_alternatives = [{ ...plan.exhausted_alternatives[0], id: 'phase-two', phase_number: 2,
      register: next.phase.write_register, statement: 'Phase two history' }];
    assert.equal(documentFor(next).querySelector('[data-exhausted-id="A"]'), null);
    assert.ok(documentFor(next).querySelector('[data-role="write"] [data-exhausted-id="phase-two"]'));
    assert.equal(documentFor(next, 'distinguish').querySelector('[data-exhausted-id]'), null);
    plan.write_options = [];
    plan.schema.$defs.actions = { 'proposition.variant': { properties: {} } };
    const allSpent = documentFor(plan);
    assert.equal(allSpent.querySelectorAll('[data-role="write"] [data-exhausted-id]').length, 2);
    assert.match(allSpent.querySelector('[data-role="gate"]').textContent, /Variant/);
    assert.doesNotMatch(allSpent.querySelector('[data-role="write"]').textContent, /No existing Proposition/);
  });
}
for (const domain of Object.keys(domains)) {
  test(`Phase 7 ${domain} keeps the actual third-register Gate and Write headings`, () => {
    const plan = fixture(domain);
    const document = documentFor(plan);
    for (const role of ['gate', 'write']) {
      const group = document.querySelector(`[data-role="${role}"] .register-row`);
      assert.equal(group.dataset.register, domains[domain][2]);
      assert.equal(group.querySelector('.proposition-card').dataset.register, domains[domain][2]);
    }
    assert.equal(document.querySelector('[data-role="read"] .register-row').dataset.register, domains[domain][1]);
    assertRegisterGroups(document);
  });
  test(`all ${domain} lane headings preserve card identity across the seven Phases`, () => {
    for (let phase = 1; phase <= 7; phase++) {
      const plan = fixture(domain, phase);
      const before = structuredClone(plan);
      assertRegisterGroups(documentFor(plan));
      assert.deepEqual(plan, before);
    }
  });
}
test('Order offers Commit only on Gate and one Traverse on each Committal Write', () => {
  const plan = fixture('settlement');
  const consumed = node('consumed-source', 'concord');
  plan.workspace.registers.concord.push(consumed);
  plan.write_options.push(consumed); // Stale display data cannot invent an action.
  const document = documentFor(plan);
  const gate = document.querySelector('[data-role="gate"]');
  const write = document.querySelector('[data-role="write"]');
  const buttons = role => [...role.querySelectorAll('button')].map(button => button.textContent.trim());
  assert.equal(buttons(gate).filter(label => label === 'Commit').length, 1);
  assert.equal(buttons(write).filter(label => label === 'Traverse').length, 1);
  assert.ok(!buttons(gate).includes('Traverse'));
  assert.ok(!buttons(write).includes('Commit'));
  assert.doesNotMatch(gate.textContent + write.textContent, /Support|Refute|Instrument|consumed-source/);
});
test('different Horizon boundary and provenance stay separate without entering structural lineage', () => {
  const plan = fixture('emergence');
  const document = documentFor(plan);
  assert.equal(document.querySelector('[aria-label="Cycle Horizon"] [data-proposition-id]').dataset.propositionId, 'boundary-emergence');
  assert.equal(document.querySelector('[aria-label="Participant provenance"] [data-proposition-id]').dataset.propositionId, 'structural-origin');
  assert.match(document.querySelector('[aria-label="Cycle Horizon"]').textContent, /Concord · Committal/);
  assert.match(document.querySelector('[aria-label="Participant provenance"]').textContent, /Canon · Committal/);
  assert.ok(ui.chronologicalLineage(plan.lineage).every(card => card.id !== 'boundary-emergence'));
  assert.ok(ui.workspaceLanes(plan).flatMap(lane => lane.cards).every(card => card.id !== 'boundary-emergence'));
  assert.ok(document.querySelector('.tabler-icon-sun-high'));
  assert.ok(documentFor(fixture('settlement')).querySelector('.tabler-icon-sun-wind'));
});
test('post-Commit continuation naturally shares the boundary, provenance, and Gate IDs', () => {
  const plan = fixture('emergence', 1, true);
  const document = documentFor(plan);
  for (const section of ['Cycle Horizon', 'Participant provenance']) {
    assert.equal(document.querySelector(`[aria-label="${section}"] [data-proposition-id]`).dataset.propositionId, plan.gate.id);
  }
  assert.equal(document.querySelector('[data-role="gate"] .register-row').dataset.register, 'concord');
  assert.equal(document.querySelector('[data-role="write"] .register-row').dataset.register, 'activity');
  assertRegisterGroups(document);
});
test('Read does not borrow a historical analogous register, while supplied mixed cards keep separate headings', () => {
  const plan = fixture('emergence');
  plan.lineage = [node('historical-stance', 'stance')]; plan.workspace.registers = {}; plan.read_options = [];
  assert.equal(ui.workspaceLanes(plan).find(lane => lane.role === 'read').cards.length, 0);
  const historical = node('historical-concord', 'concord', 'committal');
  plan.write_options.push(historical);
  plan.schema.$defs.actions['proposition.traverse'].properties.proposition_id.enum.push(historical.id);
  const document = documentFor(plan);
  assert.equal(document.querySelectorAll('[data-role="write"]').length, 2);
  assertRegisterGroups(document);
  assert.equal(historical.register, 'concord');
});
test('RTK Query fetch and refresh retain independent Cycle Horizons and historical register identities', async () => {
  const { configureStore, commonsApi } = ui;
  const store = configureStore({ reducer: { [commonsApi.reducerPath]: commonsApi.reducer }, middleware: getDefault => getDefault().concat(commonsApi.middleware) });
  const originals = { Request: globalThis.Request, fetch: globalThis.fetch, document: globalThis.document };
  globalThis.document = { cookie: '' };
  globalThis.Request = class extends originals.Request { constructor(input, options) { super(typeof input === 'string' && input.startsWith('/') ? 'http://localhost' + input : input, options); } };
  const states = { nova: { status: 'ready', plan: fixture('settlement') }, vela: { status: 'ready', plan: fixture('emergence') } };
  globalThis.fetch = async request => new Response(JSON.stringify({ data: states[request.url.includes('/nova/') ? 'nova' : 'vela'] }), { headers: { 'Content-Type': 'application/json' } });
  try {
    const params = id => ({ slug: 'shared', participantId: id });
    for (const id of ['nova', 'vela']) await store.dispatch(commonsApi.endpoints.getParticipantWorkspace.initiate(params(id))).unwrap();
    states.vela.plan = fixture('emergence', 1, true);
    await store.dispatch(commonsApi.endpoints.getParticipantWorkspace.initiate(params('vela'), { forceRefetch: true })).unwrap();
    for (const id of ['nova', 'vela']) {
      const cached = commonsApi.endpoints.getParticipantWorkspace.select(params(id))(store.getState()).data;
      assert.deepEqual(cached, states[id]);
      const document = documentFor(cached.plan);
      assertRegisterGroups(document);
      assert.equal(document.querySelector('.operational-workspace').dataset.domain, id === 'nova' ? 'settlement' : 'emergence');
    }
    const cached = commonsApi.endpoints.getParticipantWorkspace.select(params('nova'))(store.getState()).data.plan;
    assert.equal(cached.gate.register, 'concord');
    assert.equal(cached.workspace.provenance.register, 'canon');
    const staleDisplayAlias = { ...cached, workspace: { ...cached.workspace, domain: 'emergence' } };
    assert.equal(ui.workspaceDomain(staleDisplayAlias), 'settlement');
    assertRegisterGroups(documentFor(staleDisplayAlias, 'mediate'));
  } finally {
    store.dispatch(commonsApi.util.resetApiState());
    Object.assign(globalThis, originals);
  }
});
test('only an explicit root is labelled Origin when opening details are absent', () => {
  const plan = fixture('emergence');
  plan.workspace.horizon.opening_proposition = null;
  assert.match(documentFor(plan).querySelector('[aria-label="Cycle Horizon"]').textContent, /Opening Committal details unavailable/);
  plan.workspace.horizon = { ...plan.workspace.horizon, sequence: 1, parent_context_horizon_id: null, opened_by_proposition_id: null };
  assert.match(documentFor(plan).querySelector('[aria-label="Cycle Horizon"]').textContent, /Origin Horizon · no opening Committal/);
});
