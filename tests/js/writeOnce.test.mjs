import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { createRequire } from 'node:module';

const result = await build({ stdin: { contents: `
  import React from 'react';
  import { renderToStaticMarkup } from 'react-dom/server';
  import { MantineProvider } from '@mantine/core';
  import { ParticipantWorkspace } from './resources/js/components/ParticipantWorkspace';
  import { participationNotices } from './resources/js/components/ParticipationNotices';
  const render = element => renderToStaticMarkup(<MantineProvider env="test">{element}</MantineProvider>);
  export function workspace() {
    const gate = { id: 'gate', register: 'canon', tier: 'expressive', statement: 'Variant source' };
    const write = { id: 'write', register: 'activity', tier: 'expressive', statement: 'My existing Variant' };
    const actions = {
      'proposition.revise': { properties: { proposition_id: { enum: ['write'] } } },
      'instrument.execute': { properties: { proposition_id: { enum: ['write'] } } },
      'attestation.create': { properties: { proposition_id: { enum: ['write'] }, direction: { enum: ['support', 'refute'] } } },
    };
    const plan = { gate, phase: { id: 'return-cycle', number: 4, kind: 'disperse', write_register: 'activity' },
      write_options: [write], revision_options: [write], schema: { $defs: { actions } } };
    // Stale client action labels must never override the new authoritative schema.
    return render(<ParticipantWorkspace plan={plan} availableActions={['proposition.variant', ...Object.keys(actions)]} onAction={() => {}} />);
  }
  export function exhaustedCommitNotice() {
    const plan = { commitment: { requirements: { attestation: true, receipt: true, reinforcement: true, closure: true, write_opportunity: false } } };
    const notices = participationNotices({ plan, participation: { reason: { message: 'You have already authored a Commit in this row. Support an existing Write.' } }, canCommit: false, canRest: false, onRest: () => {} });
    return { title: notices[0].title, content: render(notices[0].content) };
  }
`, loader: 'tsx', resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);

test('returning Disperse exposes owned Write revision and support without another Variant', () => {
  const html = module.exports.workspace();
  const gate = html.slice(html.indexOf('data-role="gate"'), html.indexOf('data-role="write"'));
  const write = html.slice(html.indexOf('data-role="write"'));
  assert.doesNotMatch(html, />Variant<\/span>/);
  assert.doesNotMatch(gate, />Revise<\/span>|>Support<\/span>|>Refute<\/span>/);
  assert.match(write, /My existing Variant/);
  assert.match(write, />Revise<\/span>/);
  assert.match(write, />Support<\/span>/);
  assert.match(write, />Refute<\/span>/);
});

test('an exhausted Commit opportunity is explained without suggesting more grounding will restore it', () => {
  const notice = module.exports.exhaustedCommitNotice();
  assert.equal(notice.title, 'Commit already authored');
  assert.match(notice.content, /Support an existing Write/);
  assert.doesNotMatch(notice.content, /Grounding can be added/);
});
