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
  import { AttestationCounts, PropositionContent } from './resources/js/components/PropositionContent';
  import { PropositionNavigator } from './resources/js/components/PropositionNavigator';
  import { WorkspaceStatus } from './resources/js/components/WorkspaceStatus';
  import { IconLock } from '@tabler/icons-react';
  export { directionSlots, attestationPresentation, attestationColors } from './resources/js/directionalPresentation';
  export { participantColor } from './resources/js/participantColor';
  export { navigationState, navigationTarget } from './resources/js/navigation';
  const render = element => renderToStaticMarkup(<MantineProvider env="test">{element}</MantineProvider>);
  export function summary(support, negative, positive) { return render(<AttestationCounts proposition={{ attestation_summary: { support_direction: support, negative, positive } }} />); }
  export function workspace(support, effective = null) {
    const gate = { id: 'gate', register: 'signal', tier: 'expressive', statement: 'Gate context' };
    const child = { id: 'child', register: 'stance', tier: 'expressive', statement: 'Write context', attestation_summary: { support_direction: support, negative: 2, positive: 3 } };
    const actions = {
      'proposition.variant': { properties: { statement: {} } },
      'proposition.reclassify': { properties: {} },
      'proposition.revise': { properties: { proposition_id: { enum: ['child'] } } },
      'proposition.traverse': { properties: { proposition_id: { enum: effective ? ['child'] : [] } } },
      'attestation.create': { properties: { proposition_id: { enum: ['child'] }, direction: { enum: ['support', 'refute'].filter(label => label !== effective) } } },
    };
    const plan = { gate, phase: { id: 'phase', number: 2, kind: 'persist', write_register: 'stance' },
      write_options: [child], revision_options: [child], attestation_options: [child], traversal_options: [child], schema: { $defs: { actions } } };
    return render(<ParticipantWorkspace plan={plan} availableActions={Object.keys(actions)} onAction={() => {}} />);
  }
  export function metadata(mode) { return render(<PropositionContent mode={mode} proposition={{ tier: 'expressive', register: 'concord', statement: 'The statement', transition: { type: 'reclassify', from: 'stance', to: 'concord' } }} />); }
  export function navigator() { return render(<PropositionNavigator label="Write"><div>Only card</div></PropositionNavigator>); }
  export function status() { return render(<WorkspaceStatus notices={[{ id: 'closure', title: 'Closure preview', color: 'indigo', icon: IconLock, content: <p>Full status content</p> }]} />); }
`, loader: 'tsx', resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const ui = module.exports;
const css = readFileSync('resources/css/workspace.css', 'utf8');

for (const support of ['positive', 'negative']) {
  test(`directional card controls and signed counts share the ${support} Support frame`, () => {
    const slots = ui.directionSlots(support);
    assert.deepEqual(slots.map(s => s.direction), ['negative', 'positive']);
    assert.deepEqual(slots.map(s => s.label), support === 'positive' ? ['refute', 'support'] : ['support', 'refute']);
    for (const slot of slots) assert.equal(slot.color, slot.label === 'support' ? 'violet' : 'blue.6');
    const markup = ui.workspace(support);
    const footer = markup.slice(markup.indexOf('write-option-card__actions--directional'));
    const buttons = [...footer.matchAll(/<button\b[\s\S]*?<\/button>/g)].map(match => match[0]);
    assert.equal(buttons.length, 2);
    buttons.forEach((button, index) => {
      const slot = slots[index];
      assert.ok(button.includes(`data-label="${slot.label}"`) && button.includes(`data-direction="${slot.direction}"`));
      assert.ok(button.includes(`--button-color:var(--mantine-color-${slot.label === 'refute' ? 'blue-6' : 'violet-light-color'})`));
      assert.ok(index === 0 ? button.indexOf('directional-whirl') < button.indexOf(`>${slot.text}</span>`) : button.indexOf('directional-whirl') > button.indexOf(`>${slot.text}</span>`));
    });
    const summary = ui.summary(support, 5, 2);
    assert.equal([...summary.matchAll(/class="directional-whirl"/g)].length, 1);
    assert.match(summary, /data-direction="negative" data-animated="true"/);
    assert.ok(summary.indexOf('−5') < summary.indexOf('directional-whirl'));
    assert.ok(summary.indexOf('+2') > summary.indexOf('directional-whirl'));
    for (const slot of slots) assert.ok(summary.includes(`aria-label="${slot.label}: ${slot.side === 'left' ? 5 : 2}"`));
  });
  test(`Traverse arrows point into the outer whirl for ${support} Support`, () => {
    for (const label of ['support', 'refute']) {
      const markup = ui.workspace(support, label);
      const footer = markup.slice(markup.indexOf('write-option-card__actions--directional'));
      const traverse = [...footer.matchAll(/<button\b[\s\S]*?<\/button>/g)].map(match => match[0]).find(button => button.includes('>Traverse</span>'));
      assert.ok(traverse);
      const side = ui.directionSlots(support).find(slot => slot.label === label).side;
      assert.match(traverse, new RegExp(`tabler-icon-arrow-${side}`));
      assert.ok(side === 'left' ? traverse.indexOf('directional-whirl') < traverse.indexOf('tabler-icon-arrow-left') : traverse.indexOf('tabler-icon-arrow-right') < traverse.indexOf('directional-whirl'));
    }
  });
}

test('legacy tied-count payloads without retained direction stay neutral; geometry follows only signed direction', () => {
  for (const count of [0, 3]) assert.match(ui.summary('negative', count, count), /data-direction="neutral" data-animated="false"/);
  assert.equal(ui.attestationPresentation({ attestation_counts: { support: 9, refute: 0 } }).dominant, null);
  assert.match(css, /\[data-direction="negative"\] \.directional-whirl__geometry\s*\{ transform:scaleX\(-1\)/);
  assert.match(css, /\[data-direction="negative"\]\s*\{ animation-direction:reverse/);
  assert.doesNotMatch(css, /data-label="refute"[^}]*scaleX/);
  assert.match(css, /data-label="support"[^}]*mantine-color-violet-7[^}]*mantine-color-violet-4/);
  assert.match(css, /data-label="refute"[^}]*mantine-color-blue-6/);
});

test('participant palette selection is deterministic and distinguishes the active callsigns', () => {
  for (const name of ['Claire', 'Luna', 'Vela']) assert.equal(ui.participantColor(name), ui.participantColor(` ${name.toUpperCase()} `));
  assert.equal(new Set(['Claire', 'Luna', 'Vela'].map(ui.participantColor)).size, 3);
});

test('navigation recognizes overflow, disables boundaries and advances by a card group', () => {
  assert.deepEqual(ui.navigationState(0, 900, 900), { overflow: false, previous: false, next: false });
  assert.deepEqual(ui.navigationState(0, 600, 1200), { overflow: true, previous: false, next: true });
  assert.deepEqual(ui.navigationState(599.5, 600, 1200), { overflow: true, previous: true, next: false });
  assert.equal(ui.navigationTarget(0, 620, [0, 310, 620, 930, 1240], 1), 620);
  assert.equal(ui.navigationTarget(620, 620, [0, 310, 620, 930, 1240], -1), 0);
  assert.equal(ui.navigationTarget(0, 360, [0, 334, 668], 1), 334);
  assert.doesNotMatch(ui.navigator(), /aria-label="(Previous|Next) Write"/);
});

test('Variant stays with Gate while revision stays with Write', () => {
  const html = ui.workspace('positive');
  const read = html.slice(html.indexOf('data-role="read"'), html.indexOf('data-role="gate"'));
  const gate = html.slice(html.indexOf('data-role="gate"'), html.indexOf('data-role="write"'));
  const write = html.slice(html.indexOf('data-role="write"'));
  assert.doesNotMatch(read, />Reclassify<\/span>|>Revise<\/span>|>Variant<\/span>/);
  assert.match(gate, />Reclassify<\/span>/);
  assert.doesNotMatch(gate, />Revise<\/span>/);
  assert.match(gate, />Variant<\/span>/);
  assert.doesNotMatch(html, /Generative Write actions|>Generate</);
  assert.doesNotMatch(write, />Variant<\/span>/);
  assert.match(write, /data-action-region="middle"[\s\S]*>Revise<\/span>/);
  assert.doesNotMatch(write, />Reclassify<\/span>|>Supersession<\/span>/);

});

test('compact metadata omits register transition prose while detailed inspection preserves it', () => {
  assert.doesNotMatch(ui.metadata('card'), /proposition-transition/);
  assert.doesNotMatch(ui.metadata('minimal'), /proposition-transition/);
  assert.match(ui.metadata('card'), /operation-badge/);
  assert.match(ui.metadata('detailed'), /proposition-transition/);
});

test('status chips preserve full titles and open details outside the workflow', () => {
  const html = ui.status();
  assert.match(html, /<aside[^>]*workspace-status/);
  assert.doesNotMatch(html, /<details/);
  assert.match(html, /aria-label="Open Closure preview"/);
  assert.doesNotMatch(html, /role="dialog"/);
  assert.match(css, /workspace-status__compact[^}]*overflow-x:auto/);
  assert.doesNotMatch(css, /participant-cycle-content:has\(\.workspace-status/);
});
