import { test } from 'node:test';
import assert from 'node:assert/strict';
import { actionPropositions, actionTargetIds, quickContribution } from '../../resources/js/participationOptions.ts';

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

test('Persist instruments expose only schema-authorized Writes, never nearby Gate options', () => {
  const persist = {
    ...plan, write_options: [archived], instrument_options: [archived],
    gate_options: [plan.gate, unavailable],
    schema: { $defs: { actions: {
      'instrument.execute': { properties: { proposition_id: { enum: [archived.id] } } },
    } } },
  };
  assert.deepEqual(actionPropositions(persist, 'instrument.execute'), [archived]);
  assert.deepEqual(writePropositions(persist), [archived]);
  assert.equal(actionTargetIds(persist, 'instrument.execute').has(plan.gate.id), false);
  assert.equal(actionTargetIds(persist, 'instrument.execute').has(unavailable.id), false);
  assert.deepEqual(actionPropositions({ ...persist, schema: {} }, 'instrument.execute'), []);
});

for (const effective of [null, 'positive', 'negative']) {
  test(`Write directions follow the schema for ${effective ?? 'untouched'} descendants`, () => {
    const directions = ['negative', 'positive'].filter((direction) => direction !== effective);
    const localPlan = {
      ...plan, write_options: [archived],
      schema: { $defs: { actions: {
        'attestation.create': {
          properties: { proposition_id: { enum: [archived.id] }, direction: { enum: ['positive', 'negative'] } },
          anyOf: directions.map((direction) => ({ properties: { proposition_id: { enum: [archived.id] }, direction: { enum: [direction] } } })),
        },
        ...(effective ? { 'proposition.traverse': { properties: { proposition_id: { enum: [archived.id] } } } } : {}),
      } } },
    };
    assert.deepEqual(attestationDirections(localPlan, archived.id), directions);
    for (const direction of ['positive', 'negative']) {
      assert.deepEqual(quickContribution(localPlan, 'attestation.create', { proposition_id: archived.id, direction, rationale: 'Keep this for the drawer' }),
        directions.includes(direction) ? { action: 'attestation.create', proposition_id: archived.id, direction } : null);
    }
    assert.deepEqual(quickContribution(localPlan, 'proposition.traverse', { proposition_id: archived.id }),
      effective ? { action: 'proposition.traverse', proposition_id: archived.id } : null);
    assert.equal(quickContribution(localPlan, 'attestation.create', { proposition_id: unavailable.id, direction: 'positive' }), null);
    assert.equal(quickContribution(localPlan, 'proposition.revise', { proposition_id: archived.id }), null);
    assert.deepEqual(attestationDirections(localPlan, unavailable.id), []);
    assert.deepEqual(writePropositions(localPlan), [archived]);
    assert.deepEqual(writePropositions({ ...localPlan, write_options: [], gate_options: [unavailable], instrument_options: [unavailable] }), []);
    assert.deepEqual(writePropositions({ ...localPlan, schema: {} }), []);
  });
}


test('Closure Write cards and form select authorized cross-branch sources independently of the current Gate', () => {
  const candidate = { ...archived, id: 'other-branch', register: 'canon' };
  const closure = {
    ...plan, phase: { kind: 'order', write_register: 'canon' },
    write_options: [candidate, unavailable],
    schema: { $defs: { actions: {
      'proposition.commit': { properties: { proposition_id: { enum: [candidate.id] } } },
    } } },
  };
  assert.deepEqual(writePropositions(closure), [candidate]);
  assert.deepEqual(actionPropositions(closure, 'proposition.commit'), [candidate]);
  assert.equal(actionTargetIds(closure, 'proposition.commit').has(plan.gate.id), false);
  assert.equal(quickContribution(closure, 'proposition.commit', { proposition_id: candidate.id }), null);
});
