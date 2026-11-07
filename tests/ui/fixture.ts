import type { ActionName, ActionSchema, CognitiveProposition, PhaseKind, ReadyParticipation, RegisterName } from '../../resources/js/types';
const params = new URLSearchParams(location.search);
const domain = params.get('domain') === 'emergence' ? 'emergence' : 'settlement';
const registers: RegisterName[] = domain === 'emergence' ? ['activity', 'frontier', 'canon'] : ['signal', 'stance', 'concord'];
const kind = (params.get('phase') || 'order') as PhaseKind;
const active = params.get('status') === 'active';
const number = { distinguish: 1, persist: 2, constrain: 3, disperse: 4, mediate: 5, bias: 6, order: 7 }[kind];
const registerIndex = number === 7 ? 2 : (number - 1) % 3;
const write = registers[registerIndex];
const gateRegister = number === 7 ? write : registers[(registerIndex + 2) % 3];
const readRegister = registers[number === 7 ? 0 : (registerIndex + 1) % 3];
const participant = { id: 'claire', callsign: domain === 'emergence' ? 'Vela' : 'Nova', designation: domain === 'emergence' ? 'Continuing in Emergence H3' : 'Completing a Settlement Cycle in H2', max_read_count: 25, owned_by_user: true, position: { cycle_sequence: 2, cycle_completed: false, phase: { number } } };
const titles = ['Preserve transparency and protected dissent in shared decisions.', 'Make the reasoning behind every commitment available for review.', 'Let participants explore alternatives without erasing their history.', 'Keep space for local disagreement as the shared structure develops.'];
function proposition(index: number, register = write): CognitiveProposition {
  return { id: 'p-' + index, register, tier: 'expressive', statement: titles[index % titles.length],
    participant: { id: 'author-' + index % 3, callsign: ['Vela', 'Luna', 'Claire'][index % 3] }, receipts: [] };
}
const gate = proposition(98, gateRegister);
const cards = Array.from({ length: Number(params.get('cards') || 12) }, (_, index) => proposition(index));
const candidates = [gate, ...Array.from({ length: 5 }, (_, index) => proposition(index + 40, gateRegister))];
const actions: Partial<Record<ActionName, ActionSchema>> = {};
function action(name: ActionName, properties: ActionSchema['properties'], required: string[] = []) {
  actions[name] = { title: name.replace('proposition.', ''), properties: { action: { const: name }, ...properties }, required: ['action', ...required] as ActionSchema['required'] };
}
action('attestation.create', { proposition_id: { enum: cards.map(p => p.id) }, direction: { enum: ['support', 'refute'] }, rationale: {} }, ['proposition_id', 'direction']);
if (number <= 3) {
  actions['attestation.create']!.anyOf = cards.map((p, index) => ({ properties: { proposition_id: { enum: [p.id] }, direction: { enum: index % 2 ? ['support'] : ['refute'] } } }));
  action('proposition.traverse', { proposition_id: { enum: cards.map(p => p.id) } }, ['proposition_id']);
}
if ([1, 2, 4].includes(number)) action('proposition.variant', { statement: {} }, ['statement']);
if ([2, 3].includes(number)) {
  action('proposition.revise', { proposition_id: { enum: cards.map(p => p.id) }, statement: {} }, ['proposition_id', 'statement']);
}
if ([3, 4, 6, 7].includes(number)) action('instrument.execute', { proposition_id: { enum: cards.map(p => p.id) }, instrument: {} }, ['proposition_id', 'instrument']);
if (number === 5) action('proposition.reconcile', { other_proposition_id: { enum: candidates.slice(1).map(p => p.id) }, statement: {} }, ['other_proposition_id', 'statement']);
if (number === 6) action('proposition.reclassify', { proposition_id: { enum: [gate.id] }, receipt_id: { enum: ['evidence'] }, statement: {} }, ['proposition_id', 'receipt_id', 'statement']);
if (number === 7 && active && cards.length) action('proposition.commit', { proposition_id: { enum: cards.map(p => p.id) } }, ['proposition_id']);
const phase = { id: 'phase-' + kind, kind, number, write_register: write };
const participation: ReadyParticipation = {
  status: 'ready', participant, phase, domain, cycle: { id: 'cycle', sequence: 2 },
  context_horizon_id: 'horizon', horizon_sequence: 2, current_proposition_id: gate.id, last_commitment: null,
  entry: null, reason: null, preparation: null,
  context: { participant, receipts: [], read: { propositions: [
    { ...proposition(30, readRegister), relation: 'causal' },
    ...(kind === 'mediate' ? candidates.slice(1).map(node => ({ ...node, relation: 'counterpart' as const })) : []),
  ] }, gate: { acting_id: gate.id, propositions: [gate] },
  write: { question: 'What can you express here?', propositions: cards } },
  schema: { oneOf: Object.keys(actions).map(name => ({ $ref: '#/$defs/actions/' + name })), $defs: { actions } },
};
export const fixture = { participant, participation };

export const previewParticipants = [participant,
  { id: 'luna', callsign: 'Luna', designation: 'Waiting for a recovery Cycle', max_read_count: 25, owned_by_user: true, position: { cycle_sequence: 1, cycle_completed: true } },
  { id: 'vela', callsign: 'Lyra', designation: 'Waiting for available Potential', max_read_count: 25, owned_by_user: true },
  { id: 'nova', callsign: 'Mira', designation: 'Origin entry preview', max_read_count: 25, owned_by_user: true },
];
export const previewStates: Record<string, any> = {
  claire: fixture.participation,
  luna: { participant: previewParticipants[1], status: 'waiting', entry: { mode: 'potential', selection: 'recovery', available: true, message: 'Recovery is available at the nearest viable Committal region.' } },
  vela: { participant: previewParticipants[2], status: 'waiting', entry: { mode: 'potential', available: false, message: 'No Potential Charge is currently available.' } },
  nova: { participant: previewParticipants[3], status: 'waiting', entry: { mode: 'origin', available: true, message: 'Create the first Committal Proposition.' } },
};
