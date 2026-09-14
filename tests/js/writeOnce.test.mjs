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
  export function workspace(canRevise = true, persist = false) {
    const gate = { id: 'gate', register: 'canon', tier: 'expressive', statement: 'Variant source' };
    const write = { id: 'write', register: 'activity', tier: 'expressive', statement: 'My existing Variant' };
    const actions = {
      ...(canRevise ? { 'proposition.revise': { properties: { proposition_id: { enum: ['write'] } } } } : {}),
      'instrument.execute': { properties: { proposition_id: { enum: ['write'] } } },
      'attestation.create': { properties: { proposition_id: { enum: ['write'] }, direction: { enum: ['support', 'refute'] } } },
    };
    if (persist) {
      gate.register = 'activity';
      write.register = 'frontier';
      actions['proposition.traverse'] = { properties: { proposition_id: { enum: ['write'] } } };
    }
    const plan = { gate, phase: { id: 'return-cycle', number: persist ? 2 : 4, kind: persist ? 'persist' : 'disperse', write_register: write.register },
      write_options: [write], revision_options: [write], schema: { $defs: { actions } } };
    // Stale client action labels must never override the new authoritative schema.
    return render(<ParticipantWorkspace plan={plan} availableActions={['proposition.variant', ...Object.keys(actions)]} onAction={() => {}} />);
  }
  export function exhaustedCommitNotice() {
    const plan = { commitment: { requirements: { attestation: true, receipt: true, reinforcement: true, closure: true, write_opportunity: false } } };
    const notices = participationNotices({ plan, participation: { reason: { message: 'This source has already been committed. Continue through its Committal result.' } }, canCommit: false, canRest: false, onRest: () => {} });
    return { title: notices[0].title, content: render(notices[0].content) };
  }
`, loader: 'tsx', resolveDir: process.cwd() }, bundle: true, platform: 'node', format: 'cjs', write: false, jsx: 'automatic' });
const module = { exports: {} };
new Function('require', 'module', 'exports', result.outputFiles[0].text)(createRequire(import.meta.url), module, module.exports);

test('returning Disperse exposes authorized revision and support without another Variant', () => {
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
  assert.equal(notice.title, 'Source already committed');
  assert.match(notice.content, /Continue through its Committal result/);
  assert.doesNotMatch(notice.content, /Grounding can be added/);
});

test('an owned Write without authorized Revise retains support controls but hides revision', () => {
  const html = module.exports.workspace(false);
  assert.doesNotMatch(html, />Revise<\/span>/);
  assert.match(html, />Support<\/span>/);
  assert.match(html, />Refute<\/span>/);
});

test('Persist hides unavailable Variant and Revise while retaining schema-authorized support', () => {
  const html = module.exports.workspace(false, true);
  assert.doesNotMatch(html, />Variant<\/span>|>Revise<\/span>|>Rest<\/span>/);
  assert.match(html, />Support<\/span>/);
  assert.match(html, />Refute<\/span>/);
  assert.match(html, />Instrument<\/span>/);
});
