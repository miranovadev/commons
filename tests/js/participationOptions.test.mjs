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
    for (const direction of ['support', 'refute']) {
      assert.deepEqual(quickContribution(localPlan, 'attestation.create', { proposition_id: archived.id, direction, rationale: 'Keep this for the drawer' }),
        directions.includes(direction) ? { action: 'attestation.create', proposition_id: archived.id, direction } : null);
    }
    assert.deepEqual(quickContribution(localPlan, 'proposition.traverse', { proposition_id: archived.id }),
      effective ? { action: 'proposition.traverse', proposition_id: archived.id } : null);
    assert.equal(quickContribution(localPlan, 'attestation.create', { proposition_id: unavailable.id, direction: 'support' }), null);
    assert.equal(quickContribution(localPlan, 'proposition.revise', { proposition_id: archived.id }), null);
    assert.deepEqual(attestationDirections(localPlan, unavailable.id), []);
    assert.deepEqual(writePropositions(localPlan), [archived]);
    assert.deepEqual(writePropositions({ ...localPlan, write_options: [], gate_options: [unavailable], instrument_options: [unavailable] }), effective ? [archived] : []);
    assert.deepEqual(writePropositions({ ...localPlan, schema: {} }), []);
  });
}


test('Closure retains local expressive Writes independently of Commit eligibility and respects other schema actions', () => {
  const admissible = { ...archived, id: 'local-aligned', register: 'canon' };
  const divergent = { ...archived, id: 'local-divergent', register: 'canon' };
  const closure = {
    ...plan, phase: { kind: 'order', write_register: 'canon' },
    gate_options: [], write_options: [admissible, divergent],
    schema: { $defs: { actions: {
      'proposition.commit': { properties: { proposition_id: { enum: [admissible.id] } } },
    } } },
  };
  assert.deepEqual(writePropositions(closure), [admissible, divergent]);
  assert.deepEqual(actionPropositions(closure, 'proposition.commit'), [admissible]);
  assert.equal(actionTargetIds(closure, 'proposition.commit').has(divergent.id), false);
  assert.equal(actionTargetIds(closure, 'proposition.traverse').has(divergent.id), false);
  assert.deepEqual(writePropositions({ ...closure, schema: {} }), [admissible, divergent]);
  assert.equal(quickContribution(closure, 'proposition.commit', { proposition_id: admissible.id }), null);
});

test('Bias successful-route traversal remains a visible Write after its source is frozen', () => {
  const frozen = { ...archived, id: 'own-frozen-classification', register: 'canon' };
  const revisit = {
    ...plan, phase: { kind: 'bias', write_register: 'canon' }, write_options: [],
    traversal_options: [frozen],
    schema: { $defs: { actions: {
      'proposition.traverse': { properties: { proposition_id: { enum: [frozen.id] } } },
    } } },
  };
  assert.deepEqual(writePropositions(revisit), [frozen]);
  assert.deepEqual(quickContribution(revisit, 'proposition.traverse', { proposition_id: frozen.id }),
    { action: 'proposition.traverse', proposition_id: frozen.id });
});


test('Order adds schema-authorized homologous Committals without hiding local Commit or authorizing other actions', () => {
  const local = { ...archived, id: 'local-bias', register: 'canon' };
  const foreign = { ...local, id: 'homologous-commit', tier: 'committal' };
  const excluded = { ...foreign, id: 'opposite-commit' };
  const order = {
    ...plan, gate: local, phase: { kind: 'order', write_register: 'canon' },
    write_options: [local], traversal_options: [foreign, excluded],
    schema: { $defs: { actions: {
      'proposition.commit': { properties: { proposition_id: { enum: [local.id] } } },
      'proposition.traverse': { properties: { proposition_id: { enum: [foreign.id] } } },
    } } },
  };
  assert.deepEqual(writePropositions(order), [local, foreign]);
  assert.deepEqual(actionPropositions(order, 'proposition.commit'), [local]);
  assert.deepEqual(quickContribution(order, 'proposition.traverse', { proposition_id: foreign.id }),
    { action: 'proposition.traverse', proposition_id: foreign.id });
  assert.equal(quickContribution(order, 'proposition.traverse', { proposition_id: excluded.id }), null);
  assert.deepEqual(actionPropositions(order, 'attestation.create'), []);
  assert.deepEqual(actionPropositions(order, 'instrument.execute'), []);
});
