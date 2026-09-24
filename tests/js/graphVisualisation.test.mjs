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
  import { HorizonContext } from './resources/js/components/HorizonContext';
  export function provenance(proposition) {
    return renderToStaticMarkup(<MantineProvider env="test"><HorizonContext domain="emergence" provenance={proposition} onInspect={() => {}} /></MantineProvider>);
  }
  export { buildGraphStructure, buildGraphLayout, graphDensities, isGraphDensity } from './resources/js/graphLayout';
  export { graphParents, causalAncestors, lineageVisibility, graphNavigation, nextInLineage, rememberLineagePath } from './resources/js/graphTopology';
  export { attestationColors } from './resources/js/directionalPresentation';
  export { resolveGraphOverlays, graphOverlayOptions } from './resources/js/graphOverlays';
  export { activityAt, participantsByPosition } from './resources/js/graphParticipants';
  import { resolveGraphOverlays } from './resources/js/graphOverlays';
  import { GraphTensionPalette } from './resources/js/graphPresentation';
  import { PropositionCard } from './resources/js/components/PropositionCard';
  export function card(proposition, mode) {
    return renderToStaticMarkup(<MantineProvider env="test"><GraphTensionPalette value={mode === 'tension' ? 'analytical' : 'default'}><PropositionCard proposition={proposition} graph mini
      overlay={resolveGraphOverlays([proposition], mode).get(proposition.id)} /></GraphTensionPalette></MantineProvider>);
  }
  export function render(propositions, selectedId, participants = []) {
    return renderToStaticMarkup(<MantineProvider env="test"><PropositionGraph propositions={propositions} initialFocusId={selectedId} participants={participants} /></MantineProvider>);
  }
`, loader: 'tsx', resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false,
  jsx: 'automatic', loader: { '.css': 'empty' } });
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const { buildGraphStructure, buildGraphLayout, graphDensities, isGraphDensity, render, card, graphParents, causalAncestors, lineageVisibility, graphNavigation, nextInLineage, rememberLineagePath, attestationColors, resolveGraphOverlays, graphOverlayOptions, activityAt, participantsByPosition } = module.exports;
const node = (id, parent_ids = [], domain = 'emergence') => ({ id, title: id, statement: id, parent_ids,
  register: 'activity', tier: 'expressive', context_horizon: { domain } });
const nodes = [node('origin'), node('left', ['origin'], 'settlement'), node('right', ['origin']),
  node('reconciled', ['left', 'right']), node('descendant', ['reconciled']), node('unrelated', ['origin'])];

test('historical graph markers are explicit, independent and never inferred from Charge', () => {
  const both = render([{ ...node('event'), tragedy: true, renewal: true }]);
  assert.match(both, /aria-label="Tragedy" title="Tragedy"/);
  assert.match(both, /aria-label="Renewal" title="Renewal"/);
  assert.match(both, /tabler-icon-droplet-filled/);
  assert.match(both, /tabler-icon-seedling/);
  assert.doesNotMatch(both, /provenance-root-icon|data-event="provenance"/);
  const zero = render([{ ...node('zero'), field: { charge: 0, tension: 1, potential_charge: 0, status: 'current', potential_status: 'current' } }]);
  assert.doesNotMatch(zero, /graph-history-markers|aria-label="Tragedy"|aria-label="Renewal"/);
  assert.doesNotMatch(render([{ ...node('first'), tragedy: true }]), /aria-label="Renewal"/);
  assert.doesNotMatch(render([{ ...node('exit'), renewal: true }]), /aria-label="Tragedy"/);
});

test('Provenance retains its exact reference and title with only a root-node icon', () => {
  const html = module.exports.provenance(node('same-context'));
  assert.match(html, /provenance-root-icon/);
  assert.match(html, /<circle cx="6" cy="12" r="3"/);
  assert.match(html, /d="M9 12h12"/);
  assert.match(html, />Provenance</);
  assert.match(html, /data-proposition-id="same-context"/);
  assert.doesNotMatch(html, /tabler-icon-seedling|graph-history-marker/);
});

test('selection follows all ancestral branches, deduplicates and excludes descendants and siblings', () => {
  const parents = graphParents(nodes);
  assert.deepEqual([...causalAncestors(parents, 'reconciled')].sort(), ['left', 'origin', 'right']);
  assert.equal(causalAncestors(parents, null).size, 0);
  const fromChildren = graphParents([{ ...node('a'), children: [{ id: 'b' }] }, node('b')]);
  assert.deepEqual([...causalAncestors(fromChildren, 'b')], ['a']);
  fromChildren.get('a').add('b');
  assert.deepEqual([...causalAncestors(fromChildren, 'b')], ['a'], 'corrupt cycles cannot select themselves or loop');
});

test('graph retains accessible Domain, content, register and distinct selection without silhouettes', () => {
  const html = render(nodes, 'reconciled');
  assert.doesNotMatch(html, /tabler-icon-sun-wind|tabler-icon-sun-high|proposition-card__domain/);
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
  assert.deepEqual(attestationColors, { support: 'violet', refute: 'blue.6' });
  const css = await readFile('resources/css/workspace.css', 'utf8');
  assert.match(css, /data-label="refute"[^}]+blue-6/);
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
    assert.doesNotMatch(html, /tabler-icon-sun-high/);
    assert.match(html, new RegExp(`data-tension-palette="${mode === 'tension' ? 'analytical' : 'default'}"`));
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
  assert.match(card(proposition, 'tension'), /aria-label="Tension: —"/);
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

test('focus styling desaturates unrelated cards and thickens the selected border in place', async () => {
  const css = await readFile('resources/css/substrate-graph.css', 'utf8');
  assert.match(css, /data-lineage="unrelated"[^}]*opacity: .5; filter: grayscale\(1\)/);
  const selected = css.slice(css.indexOf('.substrate-graph-node .proposition-card--mini.proposition-card--selected'));
  assert.match(selected, /border-width: 3px/);
  assert.match(selected, /outline: none/);
  assert.match(selected, /box-shadow: none/);
  const field = await readFile('resources/css/workspace.css', 'utf8');
  assert.match(field, /data-sign="positive"[^}]+indigo-6[^}]+indigo-3/);
  assert.match(field, /data-sign="negative"[^}]+indigo-9[^}]+indigo-5/);
  assert.match(field, /data-tension-palette="analytical"\]\[data-sign="negative"[^}]+blue/);
  assert.match(field, /data-tension-palette="analytical"\]\[data-sign="positive"[^}]+violet/);
});

test('available Potential survives unavailable Tension and Charge in the projection', () => {
  const html = card({ ...node('partial'), field: { tension: null, charge: null, potential_charge: .6 } }, 'potential-charge');
  assert.match(html, /aria-label="Potential Charge: 0.60"/);
});


test('lineage navigation uses the canonical transition source and stable forward ordering', () => {
  const input = [node('origin'), { ...node('b', ['origin']), created_at: '2026-01-02' },
    { ...node('a', ['origin']), created_at: '2026-01-01' },
    { ...node('reconciled', ['a', 'b']), transition: { source_id: 'b' } }];
  const nav = graphNavigation(input, graphParents(input));
  assert.equal(nav.previous.get('reconciled'), 'b');
  assert.equal(nextInLineage(nav.children, 'origin', new Map()), 'a');
  const remembered = rememberLineagePath(nav.previous, 'reconciled');
  assert.equal(nextInLineage(nav.children, 'origin', remembered), 'b');
  assert.deepEqual(graphNavigation([...input].reverse(), graphParents(input)), nav);
  const missing = [{ ...node('child', ['missing', 'origin']), transition: { source_id: 'missing' } }, node('origin')];
  assert.equal(graphNavigation(missing, graphParents(missing)).previous.has('child'), false);
  const cycle = [node('a', ['b']), node('b', ['a'])];
  assert.equal(graphNavigation(cycle, graphParents(cycle)).previous.size, 0);
});


test('density changes geometry without changing topology, including a 500-node graph', () => {
  const input = Array.from({ length: 500 }, (_, i) => node(String(i), i ? [String(Math.floor((i - 1) / 3))] : []));
  const structure = buildGraphStructure(input);
  const detailed = buildGraphLayout(structure, 'detailed');
  const compact = buildGraphLayout(structure, 'topology');
  assert.deepEqual(detailed.nodes.map(n => n.proposition.id), compact.nodes.map(n => n.proposition.id));
  assert.equal(compact.nodes.length, 500);
  assert.deepEqual(detailed.edges.map(({ from, to }) => [from, to]), compact.edges.map(({ from, to }) => [from, to]));
  assert.equal(compact.edges.length, 499);
  assert.ok(compact.width < detailed.width / 2 && compact.height < detailed.height / 2);
  for (const [density, layout] of [['detailed', detailed], ['topology', compact]]) {
    const target = layout.nodes.find(n => n.proposition.id === '1');
    assert.ok(layout.edges.find(e => e.to === '1').path.endsWith(`${target.x + graphDensities[density].nodeWidth / 2} ${target.y}`));
  }
  assert.equal(isGraphDensity('topology'), true);
  assert.equal(isGraphDensity('future-zoom'), false);
  assert.equal(buildGraphLayout(buildGraphStructure([])).nodes.length, 0);
});
