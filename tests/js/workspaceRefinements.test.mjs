import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
const result = await build({ stdin: { contents: `
  import React from 'react';
  import { renderToStaticMarkup } from 'react-dom/server';
  import { MantineProvider, Button } from '@mantine/core';
  import { ParticipantWorkspace } from './resources/js/components/ParticipantWorkspace';
  import { participationNotices } from './resources/js/components/ParticipationNotices';
  import { WorkspaceStatus } from './resources/js/components/WorkspaceStatus';
  import { PhaseIdentity, PhaseProgress } from './resources/js/components/PhaseIdentity';
  import { ParticipantIdentity } from './resources/js/components/ParticipantIdentity';
  import { ParticipantSwitchRow } from './resources/js/components/ParticipantSwitcher';
  import { CycleEntryControl } from './resources/js/components/CycleEntryControl';
  import { PropositionCard } from './resources/js/components/PropositionCard';
  import { AttestationCounts } from './resources/js/components/PropositionContent';
  export { cycleEntryPresentation } from './resources/js/cycleEntry';
  export { switchableParticipants, participantPath, participantIsWaiting } from './resources/js/participantSelection';
  export { operationHistoryLabel } from './resources/js/operationHistory';
  export { attestationPresentation } from './resources/js/directionalPresentation';
  const render = element => renderToStaticMarkup(<MantineProvider env="test">{element}</MantineProvider>);
  const participant = { id: 'claire', callsign: 'Claire', designation: 'Additional participant details', max_read_count: 25 };
  const gate = { id: 'gate', statement: 'Current Gate', register: 'concord', tier: 'expressive' };
  export function notices(commit, closure) {
    return participationNotices({ participation: { status: 'ready' }, plan: { commitment: { requirements: { attestation: false, receipt: false, closure }, closure: { depth: 0, cadence: 0 } } }, canCommit: commit, canRest: false, onRest() {} });
  }
  export function nativeAdmissibility(plan) {
    return participationNotices({ participation: { status: 'ready' }, plan, canCommit: false, canRest: false, onRest() {} })
      .map(notice => ({ id: notice.id, satisfied: notice.satisfied, html: render(notice.content) }));
  }
  export function status(commit, closure) { return render(<WorkspaceStatus notices={notices(commit, closure)} />); }
  export function placement() {
    const plan = { gate, phase: { id: 'p', number: 7, kind: 'order', write_register: 'concord' }, schema: { $defs: { actions: {} } } };
    return render(<ParticipantWorkspace plan={plan} availableActions={[]} onAction={() => {}} status={<WorkspaceStatus notices={notices(false, false)} />} />);
  }
  export function phase(kind) { return render(<PhaseIdentity kind={kind} />); }
  export function identity() { return render(<ParticipantIdentity participant={participant} onSelect={() => {}} />); }
  export function card(tier, selected = false) { return render(<PropositionCard proposition={{ ...gate, tier }} selected={selected} />); }
  export function summary(frame, retained, count) { return render(<AttestationCounts proposition={{ attestation_summary: { support_direction: frame, retained_direction: retained, positive: count, negative: count } }} />); }
  function buttons(element) {
    if (!React.isValidElement(element)) return [];
    return [ ...(element.type === Button ? [element] : []), ...React.Children.toArray(element.props.children).flatMap(buttons) ];
  }
  export function entryInteraction(participation) {
    const calls = [];
    const element = CycleEntryControl({ participation, onEnter: () => calls.push('potential'), onOrigin: () => calls.push('origin') });
    buttons(element).forEach(button => button.props.onClick());
    return { calls, html: render(element) };
  }
  export function switchInteraction(waiting) {
    const calls = [];
    const element = ParticipantSwitchRow({ participant: { ...participant, position: waiting ? null : { cycle_sequence: 2, phase: { number: 5 } } }, current: true,
      onSelect: () => calls.push('claire'), onCheckEntry: () => calls.push('entry') });
    buttons(element).forEach(button => button.props.onClick());
    return { calls, html: render(element) };
  }
`, loader: 'tsx', resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);
const ui = module.exports;
const css = readFileSync('resources/css/workspace.css', 'utf8');

test('commit and Closure statuses independently follow actual availability, with open/closed locks', () => {
  for (const commit of [false, true]) for (const closure of [false, true, null]) {
    const notices = ui.notices(commit, closure);
    assert.equal(notices.find(n => n.id === 'commitment').color, commit ? 'indigo' : 'gray');
    assert.equal(notices.find(n => n.id === 'closure-preview').color, closure === true ? 'indigo' : 'gray');
    const html = ui.status(commit, closure);
    assert.match(html, commit ? /tabler-icon-lock / : /tabler-icon-lock-open/);
    assert.match(html, commit ? /Open Committable candidate/ : /Open No committable candidate/);
    if (commit) assert.doesNotMatch(html, /tabler-icon-lock-open/);
  }
  const html = ui.placement();
  assert.ok(html.indexOf('data-role="write"') < html.indexOf('aria-label="Workspace status"'));
  assert.ok(html.indexOf('aria-label="Workspace status"') < html.indexOf('No existing Proposition'));
});

test('late-phase notices expose backend component directions without granting actions from signs', () => {
  for (const sign of ['positive', 'negative']) {
    for (const admissible of [true, false]) {
      const state = { gate_id: 'gate', admissible, reason: admissible ? null : 'component_directions_disagree' };
      const notices = ui.nativeAdmissibility({
        correspondence_admissibility: { ...state, shape_topology_direction: sign, phase_profile_direction: sign },
        chirality_admissibility: { ...state, orientation_direction: sign, attestation_cadence_direction: sign },
        commitment: { requirements: { closure: admissible }, closure: { depth: 0, cadence: 0, depth_direction: sign, cadence_direction: sign } },
      });
      for (const [id, labels, action] of [
        ['correspondence-admissibility', ['Shape Topology', 'Phase Profile'], 'Reconcile'],
        ['chirality-admissibility', ['Orientation', 'Attestation Cadence'], 'Reclassify'],
        ['closure-preview', ['Depth', 'Cadence'], 'Commit'],
      ]) {
        const notice = notices.find(n => n.id === id);
        assert.equal(notice.satisfied, admissible);
        for (const label of labels) assert.ok(notice.html.includes(label));
        assert.ok(notice.html.includes(sign));
        if (!admissible) assert.ok(notice.html.includes(action + ' unavailable'));
        assert.doesNotMatch(notice.html, /<button/);
      }
    }
  }
  const [unavailable] = ui.nativeAdmissibility({ chirality_admissibility: {
    gate_id: 'gate', admissible: false, reason: 'component_directions_unavailable',
    orientation_direction: null, attestation_cadence_direction: null,
  } });
  assert.match(unavailable.html, /Component directions are unavailable/);
});

test('header provides a touch Phase info button, selectable identity, and desktop Phase progress', () => {
  for (const kind of ['distinguish', 'persist', 'constrain', 'disperse', 'mediate', 'bias', 'order']) {
    const html = ui.phase(kind);
    assert.equal([...html.matchAll(/aria-current="step"/g)].length, 1);
    const current = [...html.matchAll(/<button\b[^>]*data-state="current"[^>]*>/g)].map(match => match[0]);
    assert.equal(current.length, 1);
    assert.match(current[0], /aria-current="step"/);
    assert.match(html, /aria-label="About /);
    assert.match(html, /mantine-Stepper-root/);
  }
  assert.match(ui.identity(), /aria-label="Switch Participant: Claire"/);
  const mobile = css.slice(css.indexOf('@media (max-width:47.99em)'));
  assert.match(mobile, /participant-identity__designation,\.phase-caption \{ display:none/);
  assert.match(mobile, /phase-caption-info \{ display:inline-flex/);
  assert.match(css, /@media \(max-width:74\.99em\)\s*\{\s*\.phase-progress \{ display:none/);
});

test('neutral cards and completed-history operation wording do not masquerade as selected actions', () => {
  for (const tier of ['committal', 'expressive']) assert.doesNotMatch(ui.card(tier), /proposition-card--selected|proposition-card--indigo/);
  assert.match(ui.card('expressive', true), /proposition-card--selected/);
  for (const [key, label] of Object.entries({ reclassify: 'Reclassified', revise: 'Revised', commit: 'Committed', reconcile: 'Reconciled', disperse: 'Dispersed', mediate: 'Mediated', variant: 'Variant' })) assert.equal(ui.operationHistoryLabel(key), label);
  assert.match(css, /operation-badge\.mantine-Badge-root[^}]*color:var\(--mantine-color-dimmed\)/);
  assert.match(css, /data-reconcile-candidate[^}]*:has\(\.reconcile-action:focus-visible\)[^}]*opacity:\.78/);
});

test('tied counts retain the server direction and its regional label without spinning', () => {
  for (const frame of ['positive', 'negative']) for (const direction of ['positive', 'negative']) for (const count of [0, 3]) {
    const presentation = ui.attestationPresentation({ attestation_summary: { support_direction: frame, retained_direction: direction, positive: count, negative: count } });
    assert.equal(presentation.dominant, null);
    assert.equal(presentation.direction, direction);
    assert.equal(presentation.animate, false);
    assert.equal(presentation.label, frame === direction ? 'support' : 'refute');
    const html = ui.summary(frame, direction, count);
    assert.match(html, new RegExp('data-direction="' + direction + '" data-animated="false"'));
    assert.match(html, new RegExp('No count majority · ' + direction + ' direction persists'));
  }
});

test('Participant switching and entry controls preserve active, blocked, Origin and recovery states', () => {
  assert.deepEqual(ui.switchInteraction(false).calls, ['claire']);
  assert.deepEqual(ui.switchInteraction(true).calls, ['claire', 'entry']);
  assert.match(ui.switchInteraction(true).html, /Current/);
  assert.equal(ui.participantPath('test', 'luna'), '/substrates/test/participants/luna');
  assert.deepEqual(ui.switchableParticipants([{ id: 'mine', owned_by_user: true }, { id: 'other', owned_by_user: false }]).map(p => p.id), ['mine']);
  assert.equal(ui.switchableParticipants([{ owned_by_user: false }], true).length, 1);
  assert.equal(ui.participantIsWaiting({ position: { cycle_completed: true } }), true);
  assert.deepEqual(ui.entryInteraction({ status: 'ready', entry: { available: true, mode: 'potential' } }).calls, []);
  const blocked = ui.entryInteraction({ status: 'waiting', entry: { available: false, mode: 'potential', message: 'No Potential is available.' } });
  assert.deepEqual(blocked.calls, []);
  assert.match(blocked.html, /No Potential is available/);
  assert.deepEqual(ui.entryInteraction({ status: 'waiting', entry: { available: true, mode: 'origin' } }).calls, ['origin']);
  assert.deepEqual(ui.entryInteraction({ status: 'waiting', entry: { available: true, mode: 'continuity' } }).calls, ['potential']);
  const recovery = ui.entryInteraction({ status: 'waiting', entry: { available: true, mode: 'potential', selection: 'recovery' } });
  assert.deepEqual(recovery.calls, ['potential']);
  assert.match(recovery.html, /Begin recovery Cycle/);
  assert.deepEqual(ui.entryInteraction({ status: 'waiting', entry: { available: true, mode: 'potential', selection: 'position' } }).calls, ['potential']);
});

test('snap viewport has a zero horizontal origin and hides controls at the boundary', () => {
  assert.match(css, /proposition-navigator__viewport[^}]*padding:2px 0 5px/);
  assert.match(css, /proposition-navigator[^}]*\[hidden\][^}]*display:none/);
  const source = readFileSync('resources/js/components/PropositionNavigator.tsx', 'utf8');
  assert.match(source, /element.scrollLeft = 0/);
  assert.match(source, /hidden=\{!navigation.previous\}/);
  assert.match(source, /useLayoutEffect/);
});

