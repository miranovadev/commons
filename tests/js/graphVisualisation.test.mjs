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
  import { PropositionGraph } from './resources/js/components/PropositionGraph';
  export { graphParents, causalAncestors } from './resources/js/graphTopology';
  export { attestationColors } from './resources/js/directionalPresentation';
  export { resolveGraphOverlays, graphOverlayOptions } from './resources/js/graphOverlays';
  import { resolveGraphOverlays } from './resources/js/graphOverlays';
  import { PropositionCard } from './resources/js/components/PropositionCard';
  export function card(proposition, mode) {
    return renderToStaticMarkup(<MantineProvider env="test"><PropositionCard proposition={proposition} graph mini
      overlay={resolveGraphOverlays([proposition], mode).get(proposition.id)} /></MantineProvider>);
  }
  export function render(propositions, selectedId) {
    return renderToStaticMarkup(<MantineProvider env="test"><PropositionGraph propositions={propositions} initialFocusId={selectedId} /></MantineProvider>);
  }
`, loader: 'tsx', resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false,
  jsx: 'automatic', loader: { '.css': 'empty' } });
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const { render, card, graphParents, causalAncestors, attestationColors, resolveGraphOverlays, graphOverlayOptions } = module.exports;
const node = (id, parent_ids = [], domain = 'emergence') => ({ id, title: id, statement: id, parent_ids,
  register: 'activity', tier: 'expressive', context_horizon: { domain } });
const nodes = [node('origin'), node('left', ['origin'], 'settlement'), node('right', ['origin']),
  node('reconciled', ['left', 'right']), node('descendant', ['reconciled']), node('unrelated', ['origin'])];

test('selection follows all ancestral branches, deduplicates and excludes descendants and siblings', () => {
  const parents = graphParents(nodes);
  assert.deepEqual([...causalAncestors(parents, 'reconciled')].sort(), ['left', 'origin', 'right']);
  assert.equal(causalAncestors(parents, null).size, 0);
  const fromChildren = graphParents([{ ...node('a'), children: [{ id: 'b' }] }, node('b')]);
  assert.deepEqual([...causalAncestors(fromChildren, 'b')], ['a']);
  fromChildren.get('a').add('b');
  assert.deepEqual([...causalAncestors(fromChildren, 'b')], ['a'], 'corrupt cycles cannot select themselves or loop');
});

test('graph composes decorative canonical Domain icons, content, register and distinct selection', () => {
  const html = render(nodes, 'reconciled');
  assert.match(html, /tabler-icon-sun-wind/);
  assert.match(html, /tabler-icon-sun-high/);
  assert.match(html, /class="[^"]*proposition-card__domain[^"]*"[^>]*aria-hidden="true"/);
  assert.match(html, /Settlement Domain/);
  assert.match(html, /Emergence Domain/);
  assert.match(html, /data-proposition-id="reconciled"[^>]*aria-pressed="true"/);
  assert.equal((html.match(/data-lineage="ancestor"/g) || []).length, 3);
  assert.equal((html.match(/data-lineage="selected"/g) || []).length, 1);
  assert.equal((html.match(/data-lineage="unrelated"/g) || []).length, 2);
  assert.equal((html.match(/<path[^>]*data-lineage="true"/g) || []).length, 4);
  assert.match(html, />reconciled<\/p>/);
  assert.doesNotMatch(render([{ ...node('unknown'), context_horizon: null }]), /proposition-card__domain/);
});

test('canonical Refute is Blue and Support stays Violet in CSS and component presentation', async () => {
  assert.deepEqual(attestationColors, { support: 'violet', refute: 'blue' });
  const css = await readFile('resources/css/workspace.css', 'utf8');
  assert.match(css, /data-label="refute"[^}]+blue-8[^}]+blue-4/);
  assert.match(css, /data-label="support"[^}]+violet/);
  const panel = await readFile('resources/js/components/AttestationPanel.tsx', 'utf8');
  assert.doesNotMatch(panel, /teal/);
});

test('overlay registry supplies every lens and participation stays readable with zeroes and extreme outliers', () => {
  assert.deepEqual(graphOverlayOptions.map(o => o.value), ['none', 'participation', 'potential-charge', 'tension', 'register']);
  const input = [0, 1, 5, 20, 1e9].map((participation_count, i) => ({ ...node(String(i)), participation_count }));
  const values = [...resolveGraphOverlays(input, 'participation').values()].map(o => o.intensity);
  assert.equal(values[0], 0);
  assert.ok(values[1] >= .16 && values[2] > values[1] && values[3] > values[2]);
  assert.equal(values[4], 1);
  assert.equal(resolveGraphOverlays([node('zero')], 'participation').get('zero').intensity, 0);
  assert.equal(resolveGraphOverlays([], 'participation').size, 0);
  assert.match(render(nodes), /Visualisation/);
});

test('canonical field values drive overlays and readouts even if raw observables disagree', () => {
  const proposition = { ...node('measured'), observables: { correspondence: 1, chirality: 1, closure: 1 },
    field: { tension: -.75, charge: .25, potential_charge: .42, status: 'current', potential_status: 'current', potential_basis: 'proposition' } };
  const resolve = mode => resolveGraphOverlays([proposition], mode).get(proposition.id);
  assert.deepEqual(resolve('tension'), { color: 'blue', intensity: .75, label: 'Tension: -0.75' });
  assert.equal(resolve('potential-charge').intensity, .42);
  assert.equal(resolve('register').color, 'red');
  for (const mode of graphOverlayOptions.map(o => o.value)) {
    const html = card(proposition, mode);
    assert.match(html, /tabler-icon-sun-high/);
    assert.match(html, /aria-label="Potential Charge: 0.42"/);
    assert.match(html, /aria-label="Tension: -0.75"/);
  }
  proposition.field.tension = .9;
  assert.equal(resolve('tension').color, 'violet');
  proposition.field.tension = .001;
  assert.ok(resolve('tension').intensity < .01);
  proposition.field.potential_charge = null;
  assert.match(card(proposition, 'potential-charge'), /awaiting measurement/);
  proposition.field.tension = null;
  assert.match(card(proposition, 'tension'), /Field awaiting complete measurement/);
});
