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
  export { activityAt, participantsByPosition } from './resources/js/graphParticipants';
  import { resolveGraphOverlays } from './resources/js/graphOverlays';
  import { PropositionCard } from './resources/js/components/PropositionCard';
  export function card(proposition, mode) {
    return renderToStaticMarkup(<MantineProvider env="test"><PropositionCard proposition={proposition} graph mini
      overlay={resolveGraphOverlays([proposition], mode).get(proposition.id)} /></MantineProvider>);
  }
  export function render(propositions, selectedId, participants = []) {
    return renderToStaticMarkup(<MantineProvider env="test"><PropositionGraph propositions={propositions} initialFocusId={selectedId} participants={participants} /></MantineProvider>);
  }
`, loader: 'tsx', resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false,
  jsx: 'automatic', loader: { '.css': 'empty' } });
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const { render, card, graphParents, causalAncestors, attestationColors, resolveGraphOverlays, graphOverlayOptions, activityAt, participantsByPosition } = module.exports;
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

test('position grouping retains inactive participants and keeps identity and overflow activity accessible', async () => {
  const participants = ['Nova', 'Vela', 'Luna', 'Sera', 'Claire'].map((callsign, i) => ({ id: String(i), callsign,
    current_proposition_id: 'origin', is_active: i === 1 || i === 4, activity_expires_at: null }));
  assert.equal(participantsByPosition([...participants, { id: 'unplaced', current_proposition_id: null }]).get('origin').length, 5);
  const html = render(nodes, 'reconciled', participants);
  assert.match(html, /aria-label="Nova: inactive"/);
  assert.match(html, /aria-label="Vela: active"/);
  assert.match(html, /aria-label="Claire: active"/);
  assert.match(html, />\+1<\/p>/);
  assert.equal((html.match(/class="graph-participant__activity"/g) || []).length, 2);
  const css = await readFile('resources/css/substrate-graph.css', 'utf8');
  assert.match(css, /grayscale\(1\)/);
  assert.match(css, /prefers-reduced-motion: reduce[\s\S]*graph-participant__activity \{ animation: none/);
});

test('lease expiry uses the server clock and leaves the last canonical position visible', () => {
  const server = Date.parse('2050-01-01T00:00:00Z');
  const snapshot = { server_time: '2050-01-01T00:00:00Z', received_at: 100,
    participants: [{ id: 'p', callsign: 'Nova', current_proposition_id: 'prepared-p1', is_active: true, activity_expires_at: server + 1000 }] };
  assert.equal(activityAt(snapshot, 500)[0].is_active, true);
  const expired = activityAt(snapshot, 1100)[0];
  assert.equal(expired.is_active, false);
  assert.equal(expired.current_proposition_id, 'prepared-p1');
});
