import { test, after } from 'node:test';
import assert from 'node:assert/strict';
import { build, stop } from 'esbuild';
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';

after(stop);
const bundle = await build({ stdin: { contents: `
  import React from 'react';
  import { renderToStaticMarkup } from 'react-dom/server';
  import { DEFAULT_THEME } from '@mantine/core';
  import { SubstrateTheme, substrateTheme } from './resources/js/SubstrateTheme';
  import { AttestationDirectionControl } from './resources/js/components/AttestationDirectionControl';
  import { AttestationCounts } from './resources/js/components/PropositionContent';
  import { SubstratePresentationFields } from './resources/js/components/SubstratePresentationFields';
  import { resolvePresentation, presentationFor } from './resources/js/substratePresentation';
  import { directionSlots } from './resources/js/directionalPresentation';
  export { resolvePresentation, presentationFor, directionSlots, substrateTheme, DEFAULT_THEME };
  export function render(presentation, frame = 'positive') {
    return renderToStaticMarkup(<SubstrateTheme presentation={presentation}>
      <AttestationDirectionControl value="positive" positiveDirection={frame} onChange={() => {}} />
      <AttestationCounts proposition={{ attestation_summary: { positive_direction: frame, positive: 3, negative: 2 } }} />
      <SubstratePresentationFields value={presentation} onChange={() => {}} />
    </SubstrateTheme>);
  }
`, loader: 'tsx', resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
const module = { exports: {} };
new Function('require', 'module', 'exports', bundle.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const ui = module.exports;
const custom = ui.resolvePresentation({ primary_color: 'orange', positive: { label: 'Agree', color: 'green' }, negative: { label: 'Challenge', color: 'red' } });

test('existing Substrates use indigo, Support/violet and Refute/blue defaults', () => {
  const defaults = ui.presentationFor({ configuration: {} });
  assert.deepEqual(defaults, { primary_color: 'indigo', positive: { label: 'Support', color: 'violet' }, negative: { label: 'Refute', color: 'blue' } });
  assert.equal(ui.presentationFor({ configuration: { presentation: { positive: { label: 'Yes' } } } }).positive.color, 'violet');
  const html = ui.render(defaults);
  assert.match(html, /aria-label="Support: 3"/);
  assert.match(html, /aria-label="Refute: 2"/);
});

for (const frame of ['positive', 'negative']) {
  test(`custom labels and colours preserve canonical values and positions in the ${frame} region frame`, () => {
    const defaults = ui.directionSlots(frame);
    const slots = ui.directionSlots(frame, custom);
    assert.deepEqual(slots.map(({ side, direction, label }) => ({ side, direction, label })),
      defaults.map(({ side, direction, label }) => ({ side, direction, label })));
    const html = ui.render(custom, frame);
    assert.match(html, /aria-label="Agree"/);
    assert.match(html, /aria-label="Challenge"/);
    assert.match(html, /value="positive"/);
    assert.match(html, /value="negative"/);
    assert.match(html, /mantine-color-green/);
    assert.match(html, /aria-label="Agree: [23]"/);
    assert.match(html, /aria-label="Challenge: [23]"/);
    assert.doesNotMatch(html, /aria-label="Support|aria-label="Refute/);
  });
}

test('one selected palette supplies all primary shades and both attestation palettes', () => {
  const theme = ui.substrateTheme(custom);
  assert.deepEqual(theme.colors.primary, ui.DEFAULT_THEME.colors.orange);
  assert.deepEqual(theme.colors.attestationPositive, ui.DEFAULT_THEME.colors.green);
  assert.deepEqual(theme.colors.attestationNegative, ui.DEFAULT_THEME.colors.red);
  const css = readFileSync('resources/css/workspace.css', 'utf8');
  assert.match(css, /data-domain="emergence"[^}]*background:var\(--mantine-color-primary-0\)/);
  assert.match(css, /data-domain="settlement"[^}]*background:var\(--mantine-color-primary-9\)/);
  assert.deepEqual(ui.substrateTheme(ui.presentationFor(undefined)).colors.primary, ui.DEFAULT_THEME.colors.indigo,
    'leaving a Substrate returns to defaults');
});
