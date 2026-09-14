import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';
const result = await build({ stdin: { contents: `
  import React from 'react';
  import { renderToStaticMarkup } from 'react-dom/server';
  import { MantineProvider, Button } from '@mantine/core';
  import { MemoryRouter } from 'react-router-dom';
  import { CycleCompletion } from './resources/js/components/CycleCompletion';
  import { ParticipantWorkspace } from './resources/js/components/ParticipantWorkspace';
  export { chronologicalLineage } from './resources/js/lineage';
  export { actionTargetIds, writePropositions } from './resources/js/participationOptions';
  const render = element => renderToStaticMarkup(<MantineProvider env="test"><MemoryRouter>{element}</MemoryRouter></MantineProvider>);
  export function completion(state) {
    const calls = [];
    const element = CycleCompletion({ slug: 'test', participation: state, onEnter: () => calls.push('enter'), onOrigin: () => calls.push('origin') });
    function walk(node) {
      if (!React.isValidElement(node)) return;
      if (node.type === Button && node.props.onClick && !node.props.disabled) node.props.onClick();
      React.Children.forEach(node.props.children, walk);
    }
    walk(element);
    return { html: render(element), calls };
  }
  export function workspace(plan, complete = false) {
    return render(<ParticipantWorkspace plan={plan} availableActions={complete ? [] : Object.keys(plan.schema.$defs.actions)} onAction={() => {}}
      completion={complete ? <CycleCompletion slug="test" participation={{ status: 'waiting', entry: { mode: 'potential', available: true } }} onEnter={() => {}} onOrigin={() => {}} /> : undefined} />);
  }
`, loader: 'tsx', resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const ui = module.exports;
const node = (id, parents = [], tier = 'expressive') => ({ id, parent_ids: parents, tier, register: 'concord', statement: id });

test('lineage preserves the resolved server path across collapsed parents and misleading timestamps', () => {
  const provenance = { ...node('seed', [], 'committal'), created_at: '2030-01-01' };
  const result = { ...node('result', ['hard-input-a', 'hard-input-b']), created_at: '2020-01-01' };
  const head = node('head', ['result']);
  const chain = [provenance, result, head];
  assert.deepEqual(ui.chronologicalLineage(chain).map(item => item.id), ['seed', 'result', 'head']);
  assert.deepEqual(chain, [provenance, result, head]);
  assert.deepEqual(ui.chronologicalLineage([]), []);
});

test('completion navigates to the Substrate Participants and respects waiting, recovery and unavailable entry', () => {
  for (const selection of ['position', 'recovery']) {
    const result = ui.completion({ status: 'waiting', entry: { mode: 'potential', selection, available: true } });
    assert.deepEqual(result.calls, ['enter']);
    assert.match(result.html, /href="\/substrates\/test\/participants"/);
    assert.match(result.html, /Return to Participants/);
    assert.match(result.html, /Enter another Cycle/);
  }
  for (const state of [{ status: 'ready', entry: { available: true } }, { status: 'waiting', entry: { mode: 'potential', available: false, message: 'Waiting for Potential' } }]) {
    const result = ui.completion(state);
    assert.deepEqual(result.calls, []);
    assert.match(result.html, /disabled/);
  }
});

test('Instrument-only schema targets render centrally in Constrain and Bias and disappear without authorization', () => {
  for (const [kind, number] of [['constrain', 3], ['bias', 6]]) {
    const target = node('instrument-only');
    const plan = { phase: { id: 'phase', kind, number, write_register: 'concord' }, gate: node('gate'), write_options: [], instrument_options: [target, node('forbidden')],
      schema: { $defs: { actions: { 'instrument.execute': { properties: { proposition_id: { enum: [target.id] } } } } } } };
    assert.deepEqual(ui.writePropositions(plan).map(n => n.id), [target.id]);
    const html = ui.workspace(plan);
    assert.match(html, /instrument-only/);
    assert.doesNotMatch(html, /forbidden/);
    const footer = html.slice(html.indexOf('write-option-card__actions--directional'));
    assert.ok(footer.indexOf('data-action-region="left"') < footer.indexOf('>Instrument</span>'));
    assert.ok(footer.indexOf('>Instrument</span>') < footer.indexOf('data-action-region="right"'));
    const completed = ui.workspace(plan, true);
    assert.match(completed, /instrument-only/);
    assert.match(completed, /Cycle complete/);
    assert.doesNotMatch(completed, />Instrument<\/span>|>Support<\/span>|>Refute<\/span>|Generate/);
    plan.schema.$defs.actions = {};
    assert.doesNotMatch(ui.workspace(plan), /instrument-only|>Instrument<\/span>/);
  }
});
