import { test, after } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { build, stop } from 'esbuild';
import { createRequire } from 'node:module';
after(() => stop());
const result = await build({ stdin: { contents: `
  import React from 'react';
  import { renderToStaticMarkup } from 'react-dom/server';
  import { MantineProvider } from '@mantine/core';
  import { PropositionCard } from './resources/js/components/PropositionCard';
  import { InspectorContent } from './resources/js/components/PropositionInspector';
  import { ClosureHistoryChart, FieldStateChart } from './resources/js/components/InspectorCharts';
  export { specialBadgesFor } from './resources/js/components/SpecialPropositionBadges';
  export { closureDirectionColor } from './resources/js/components/InspectorCharts';
  export function render(kind, proposition) {
    return renderToStaticMarkup(<MantineProvider env="test">{kind === 'participant' ? <InspectorContent selection={{ kind: 'write option', proposition, projectedObservables: { correspondence: -.6, chirality: -.6, closure: -.6 } }} /> : kind === 'closure'
      ? <ClosureHistoryChart proposition={proposition} /> : kind === 'field'
      ? <FieldStateChart proposition={proposition} /> : <PropositionCard proposition={proposition} density={kind} graph mini />}</MantineProvider>);
  }
`, loader: 'tsx', resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const { render, specialBadgesFor, closureDirectionColor } = module.exports;
const node = { id: 'selected', tier: 'committal', register: 'concord', statement: 'Selected Proposition' };

test('both node densities share all event definitions, labels and icons', () => {
  for (const [domain_transition, label, icon] of [['settlement-emergence', 'Settlement → Emergence', 'sunrise'], ['emergence-settlement', 'Emergence → Settlement', 'sunset']]) {
    const proposition = { ...node, tragedy: true, renewal: true, confluence: true, domain_transition };
    assert.deepEqual(specialBadgesFor(proposition).map(b => b.label), ['Tragedy', 'Renewal', 'Confluence', 'Committal', label]);
    for (const density of ['detailed', 'topology']) {
      const html = render(density, proposition);
      for (const badge of ['Tragedy', 'Renewal', 'Confluence', 'Committal', label]) {
        assert.ok(html.includes(`aria-label="${badge}" title="${badge}"`));
      }
      for (const name of ['droplet-filled', 'seedling', 'arrows-join', 'bolt', icon]) assert.ok(html.includes(`tabler-icon-${name}`));
    }
  }
});

test('expressive tension, arbitrary parents and unverified transitions never invent an event', () => {
  const proposition = { ...node, tier: 'expressive', field: { tension: -1 }, parent_ids: ['a', 'b'],
    context_horizon: { domain: 'emergence' }, domain_transition: 'emergence-settlement' };
  assert.deepEqual(specialBadgesFor(proposition), []);
  assert.deepEqual(specialBadgesFor(node).map(b => b.type), ['committal']);
});

test('Closure direction colors follow supplied hysteretic direction, including numeric zero', () => {
  for (const [depth_direction, cadence_direction, color] of [
    ['positive', 'positive', 'indigo.4'], ['negative', 'negative', 'indigo.9'],
    ['positive', 'negative', 'gray.6'], ['negative', 'positive', 'gray.6'],
  ]) {
    const point = { proposition_id: 'selected', depth: 0, cadence: 0, depth_direction, cadence_direction };
    assert.equal(closureDirectionColor(point), color);
    const html = render('closure', { ...node, closure_history: { points: [point], current: point, source: 'selected-committal' } });
    assert.equal((html.match(new RegExp(`data-color="${color}"`, 'g')) || []).length, 2);
    assert.ok(html.includes(`Depth: ${depth_direction} direction`));
    assert.ok(html.includes(`Cadence: ${cadence_direction} direction`));
    assert.match(html, /Depth history: 0.00/);
    assert.match(html, /Cadence history: 0.00/);
  }
});

test('missing Closure data is explicit and never fabricated as zero', () => {
  assert.match(render('closure', node), /history is unavailable/);
  assert.doesNotMatch(render('closure', node), /data-direction/);
});

test('Field State uses persisted Measurements and Tension independently', () => {
  const html = render('field', { ...node, observables: { correspondence: .8, chirality: .6, closure: .4 }, field: { tension: -.7 } });
  for (const value of ['Correspondence: +0.80', 'Chirality: +0.60', 'Closure: +0.40', 'Tension: -0.70']) assert.ok(html.includes(value));
  assert.match(html, /Settlement −1, Balanced 0, Emergence \+1/);
  assert.doesNotMatch(html, /color-red|color-green/);
  const missing = render('field', { ...node, observables: { correspondence: 1, chirality: 1, closure: 1 } });
  assert.match(missing, /Tension: Unavailable/);
});

test('responsive card and smooth navigation respect reduced motion, helper cannot be selected', async () => {
  const css = await readFile('resources/css/substrate-graph.css', 'utf8');
  assert.match(css, /\.substrate-graph-hint\s*\{[^}]*user-select: none;[^}]*-webkit-user-select: none/s);
  assert.match(css, /transition: transform 240ms ease-out/);
  assert.match(css, /@media \(max-width: 48em\)\s*\{\s*\.graph-inspector\s*\{[^}]*bottom: .5rem/s);
  assert.match(css, /prefers-reduced-motion: reduce[^}]*\.substrate-graph-canvas\[data-animating\][^}]*transition: none/s);
});

test('participant preview inspection retains its existing projected field readout', () => {
  const html = render('participant', { ...node, observables: { correspondence: .2, chirality: .2, closure: .2 } });
  assert.match(html, /Tension: -0.60/);
  assert.match(html, /Structural delta/);
  assert.doesNotMatch(html, /role="tablist"/);
});
