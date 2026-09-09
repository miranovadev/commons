import { test } from 'node:test';
import assert from 'node:assert/strict';
import { actionPropositions, actionTargetIds } from '../../resources/js/participationOptions.ts';

const archived = { id: 'historical', register: 'frontier', tier: 'expressive', statement: 'Still attestable' };
const unavailable = { ...archived, id: 'unavailable' };
const plan = {
  gate: { ...archived, id: 'gate' },
  phase: { kind: 'persist', write_register: 'frontier' },
  attestation_options: [archived],
  write_options: [],
  workspace: { registers: { frontier: [unavailable] } },
  schema: { $defs: { actions: { 'attestation.create': {
    properties: { proposition_id: { enum: ['historical'] } },
  } } } },
};

test('attestation controls and form include eligible superseded targets absent from write heads and workspace', () => {
  assert.deepEqual(actionPropositions(plan, 'attestation.create'), [archived]);
  assert.equal(actionTargetIds(plan, 'attestation.create').has('historical'), true);
  assert.equal(actionTargetIds(plan, 'attestation.create').has('unavailable'), false);
});

test('no attestation action means no permitted buttons or form targets', () => {
  assert.deepEqual(actionPropositions({ ...plan, schema: {} }, 'attestation.create'), []);
});

test('revision and attestation selectors use separate schema permissions', () => {
  assert.deepEqual(actionPropositions(plan, 'proposition.revise'), []);
});

test('Traverse and Bias instruments use their own authorized historical target sets', () => {
  const grounded = { ...archived, id: 'reconciled' };
  const navigation = {
    ...plan, traversal_options: [archived], instrument_options: [grounded],
    schema: { $defs: { actions: {
      'proposition.traverse': { properties: { proposition_id: { enum: [archived.id] } } },
      'instrument.execute': { properties: { proposition_id: { enum: [grounded.id] } } },
    } } },
  };
  assert.deepEqual(actionPropositions(navigation, 'proposition.traverse'), [archived]);
  assert.deepEqual(actionPropositions(navigation, 'instrument.execute'), [grounded]);
  assert.deepEqual(actionPropositions(navigation, 'proposition.revise'), []);
});

const { attestationDirections, writePropositions } = await import('../../resources/js/participationOptions.ts');

for (const effective of [null, 'support', 'refute']) {
  test(`Write directions follow the schema for ${effective ?? 'untouched'} descendants`, () => {
    const directions = ['refute', 'support'].filter((direction) => direction !== effective);
    const localPlan = {
      ...plan, write_options: [archived],
      schema: { $defs: { actions: {
        'attestation.create': {
          properties: { proposition_id: { enum: [archived.id] }, direction: { enum: ['support', 'refute'] } },
          anyOf: directions.map((direction) => ({ properties: { proposition_id: { enum: [archived.id] }, direction: { enum: [direction] } } })),
        },
        ...(effective ? { 'proposition.traverse': { properties: { proposition_id: { enum: [archived.id] } } } } : {}),
      } } },
    };
    assert.deepEqual(attestationDirections(localPlan, archived.id), directions);
    assert.deepEqual(attestationDirections(localPlan, unavailable.id), []);
    assert.deepEqual(writePropositions(localPlan), [archived]);
    assert.deepEqual(writePropositions({ ...localPlan, write_options: [], gate_options: [unavailable], instrument_options: [unavailable] }), []);
    assert.deepEqual(writePropositions({ ...localPlan, schema: {} }), []);
  });
}
