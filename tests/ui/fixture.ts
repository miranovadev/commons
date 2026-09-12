import type { ActionName, ActionSchema, ParticipantPlan, PhaseKind, Proposition } from '../../resources/js/types';
const params = new URLSearchParams(location.search);
const kind = (params.get('phase') || 'order') as PhaseKind;
const support = params.get('support') === 'negative' ? 'negative' : 'positive';
const active = params.get('status') === 'active';
const closureSatisfied = params.get('status') !== 'inactive';
const number = { distinguish: 1, persist: 2, constrain: 3, disperse: 4, mediate: 5, bias: 6, order: 7 }[kind];
const registerIndex = number === 7 ? 2 : (number - 1) % 3;
const write = (['signal', 'stance', 'concord'] as const)[registerIndex];
const gateRegister = (['concord', 'signal', 'stance'] as const)[registerIndex];
const readRegister = (['stance', 'concord', 'signal'] as const)[registerIndex];
const participant = { id: 'claire', callsign: 'Claire', designation: 'Exploring a shared cognitive substrate', max_read_count: 25, owned_by_user: true, position: { cycle_sequence: 2, cycle_completed: false, phase: { number } } };
const titles = ['Preserve transparency and protected dissent in shared decisions.', 'Make the reasoning behind every commitment available for review.', 'Let participants explore alternatives without erasing their history.', 'Keep space for local disagreement as the shared structure develops.'];
function proposition(index: number, register = write): Proposition {
  const negative = index % 4, positive = index % 3;
  return { id: 'p-' + index, register, tier: 'expressive', statement: titles[index % titles.length] + '\nA longer account of this idea, its implications and the evidence that supports it. '.repeat(15),
    title: titles[index % titles.length], participant: { id: 'author-' + index % 3, callsign: ['Vela', 'Luna', 'Claire'][index % 3] },
    transition: { type: ['variant', 'reclassify', 'revise'][index % 3], from: gateRegister, to: register },
    observables: { correspondence: index % 2 ? -.25 : .4, chirality: 0, closure: -.3 },
    attestation_counts: { support: support === 'positive' ? positive : negative, refute: support === 'positive' ? negative : positive },
    attestation_summary: { support_direction: support, retained_direction: index % 2 ? 'positive' : 'negative', positive, negative }, instruments: [] };
}
const gate = { ...proposition(98, gateRegister), id: 'gate' };
const provenance = { ...proposition(97, 'concord'), id: 'origin', tier: 'committal' as const, title: 'A shared cognitive system should preserve transparency, protected dissent and common ground.', observables: { correspondence: -1, chirality: -1, closure: -1 } };
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
  action('proposition.reclassify', {});
  action('proposition.revise', { proposition_id: { enum: cards.map(p => p.id) }, statement: {} }, ['proposition_id', 'statement']);
}
if ([4, 6].includes(number)) action('instrument.execute', { proposition_id: { enum: cards.map(p => p.id) }, instrument: {} }, ['proposition_id', 'instrument']);
if (number === 5) action('proposition.reconcile', { other_proposition_id: { enum: candidates.slice(1).map(p => p.id) } }, ['other_proposition_id']);
if (number === 6) action('proposition.bias', { proposition_id: { enum: candidates.map(p => p.id) } }, ['proposition_id']);
if (number === 7 && active && cards.length) action('proposition.commit', { proposition_id: { enum: cards.map(p => p.id) } }, ['proposition_id']);
const plan: ParticipantPlan = { gate, phase: { id: 'phase-' + kind, kind, number, write_register: write },
  workspace: { domain: 'settlement', provenance, registers: { [readRegister]: [proposition(30, readRegister)] } },
  lineage: [provenance, ...Array.from({ length: 9 }, (_, index) => proposition(index + 20, readRegister))],
  read_options: Array.from({ length: 5 }, (_, index) => proposition(index + 30, readRegister)),
  write_options: cards, attestation_options: cards, traversal_options: cards, revision_options: cards, instrument_options: cards, gate_options: candidates,
  schema: { oneOf: Object.keys(actions).map(name => ({ $ref: '#/$defs/actions/' + name })), $defs: { actions } },
  commitment: number === 7 ? { admissible: active && cards.length > 0, closure_enforced: true, reasons: [], requirements: { attestation: active, receipt: active, closure: closureSatisfied },
    closure: { depth: -.2490561, cadence: -.6408194, closure: -.444, depth_direction: 'negative', cadence_direction: 'negative', history_proposition_id: 'origin', history_source: 'committed-ancestor' } } : null,
};
export const fixture = { participant, participation: { status: 'ready', plan, domain: 'settlement', horizon_sequence: 2,
  reason: { message: 'Commitment requires grounding and aligned Depth/Cadence directions. Use an action available in the current Phase.' },
  last_commitment: { domain_before: 'emergence', domain_after: 'settlement', closure_boundary: true, tension_before: 0, tension_after: -1, depth: -1, cadence: -1, depth_direction: 'negative', cadence_direction: 'negative' } } };

export const previewParticipants = [participant,
  { id: 'luna', callsign: 'Luna', designation: 'Waiting for a recovery Cycle', max_read_count: 25, owned_by_user: true, position: { cycle_sequence: 1, cycle_completed: true } },
  { id: 'vela', callsign: 'Vela', designation: 'Waiting for available Potential', max_read_count: 25, owned_by_user: true },
  { id: 'nova', callsign: 'Nova', designation: 'Origin entry preview', max_read_count: 25, owned_by_user: true },
];
export const previewStates: Record<string, any> = {
  claire: fixture.participation,
  luna: { status: 'waiting', entry: { mode: 'potential', selection: 'recovery', available: true, message: 'Recovery is available at the nearest viable Committal region.' } },
  vela: { status: 'waiting', entry: { mode: 'potential', available: false, message: 'No Potential Charge is currently available.' } },
  nova: { status: 'waiting', entry: { mode: 'origin', available: true, message: 'Create the first Committal Proposition.' } },
};
