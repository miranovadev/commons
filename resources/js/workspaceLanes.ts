import { actionPropositions, writePropositions } from './participationOptions';
import { phaseRegisterPreview } from './phasePreview';
import { domainForRegister } from './registers';
import type { ExhaustedAlternative, ParticipantPlan, PhaseKind, Proposition, RegisterName } from './types';

export type LaneRole = 'read' | 'gate' | 'write';
export interface WorkspaceLane { key: string; role: LaneRole; register?: RegisterName; cards: Proposition[]; history?: ExhaustedAlternative[]; }

/** The Cycle-owned Horizon is authoritative, including during overlapping Cycles. */
export function workspaceDomain(plan: ParticipantPlan) {
  return plan.workspace?.horizon?.domain || plan.workspace?.domain || domainForRegister(plan.phase.write_register);
}

export function workspaceProvenance(plan: ParticipantPlan) {
  return plan.workspace?.provenance || plan.lineage?.find(node => node.tier === 'committal') || plan.gate;
}

/** Roles describe operations; headings and grouping preserve each stored register. */
export function workspaceLanes(plan: ParticipantPlan, previewKind?: PhaseKind): WorkspaceLane[] {
  const preview = previewKind ? phaseRegisterPreview(plan, previewKind) : null;
  const roles = ['read', 'gate', 'write'] as const;
  if (preview) return roles.flatMap(role => {
    const inherited = (preview.number === 1 && role !== 'write') || (preview.number === 2 && role === 'read');
    const cards = inherited ? preview.provenance : preview[role].cards;
    return groupLane(role, cards, inherited ? cards[0]?.register : preview[role].register);
  });

  const registers: RegisterName[] = workspaceDomain(plan) === 'settlement' ? ['signal', 'stance', 'concord'] : ['activity', 'frontier', 'canon'];
  const phase = plan.phase.number;
  const provenance = workspaceProvenance(plan);
  const write = plan.phase.write_register;
  // Order reads Stance/Frontier, commits expressive Gates and traverses Committal Writes.
  const read = phase === 7 ? registers[1] : registers[(registers.indexOf(write) + 1) % 3];
  const inheritedRead = phase <= 2;
  const readCard = inheritedRead ? provenance
    : plan.workspace?.registers?.[read]?.find(node => node.register === read && node.id !== plan.gate.id)
      || plan.lineage?.find(node => node.register === read);
  const reads = ['mediate', 'order'].includes(plan.phase.kind) ? plan.read_options || [] : readCard ? [readCard] : [];
  const gates = plan.phase.kind === 'order' ? actionPropositions(plan, 'proposition.commit')
    : plan.phase.kind === 'bias' ? actionPropositions(plan, 'proposition.reclassify')
    : [plan.gate, ...(plan.phase.kind === 'mediate' ? actionPropositions(plan, 'proposition.reconcile') : [])];
  const lanes = [
    ...groupLane('read', reads, inheritedRead ? provenance.register : read),
    ...groupLane('gate', gates, plan.gate.register),
    ...groupLane('write', writePropositions(plan), write),
  ];
  // Historical context has no path into schema-driven action cards or form choices.
  for (const item of plan.exhausted_alternatives ?? []) {
    const lane = lanes.find(lane => lane.role === item.role && lane.register === item.register);
    if (lane) lane.history = [...(lane.history ?? []), item];
    else lanes.push({ key: `${item.role}-${item.register}`, role: item.role, register: item.register, cards: [], history: [item] });
  }
  return lanes;
}

function groupLane(role: LaneRole, cards: Proposition[], emptyRegister?: RegisterName): WorkspaceLane[] {
  const groups = new Map<RegisterName, Proposition[]>();
  for (const card of new Map(cards.map(node => [node.id, node])).values()) {
    groups.set(card.register, [...(groups.get(card.register) || []), card]);
  }
  return groups.size ? [...groups].map(([register, cards]) => ({ key: `${role}-${register}`, role, register, cards }))
    : [{ key: role, role, register: emptyRegister, cards: [] }];
}
