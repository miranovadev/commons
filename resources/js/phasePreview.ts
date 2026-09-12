import { phaseMeta } from './phases';
import { domainForRegister } from './registers';
import type { ParticipantPlan, PhaseKind, Proposition, RegisterName } from './types';

export function canPreviewPhase(current: PhaseKind, selected: PhaseKind) {
  return phaseMeta[selected].number < phaseMeta[current].number;
}

/** Inspect only the server-resolved active history, never current options or Horizon cards. */
export function phaseRegisterPreview(plan: ParticipantPlan, selected: PhaseKind) {
  if (!canPreviewPhase(plan.phase.kind, selected)) return null;
  const domain = plan.workspace?.domain || domainForRegister(plan.phase.write_register);
  const registers: RegisterName[] = domain === 'settlement' ? ['signal', 'stance', 'concord'] : ['activity', 'frontier', 'canon'];
  // Order retains the Bias Write register, as in PhaseProgressionService.
  const position = { distinguish: 0, persist: 1, constrain: 2, disperse: 0, mediate: 1, bias: 2, order: 2 }[selected];
  const unique = [...new Map((plan.lineage || []).map(node => [node.id, node])).values()];
  const provenance = unique.find(node => node.tier === 'committal');
  return {
    kind: selected, number: phaseMeta[selected].number,
    read: { register: registers[(position + 1) % 3], cards: unique.filter(node => node.register === registers[(position + 1) % 3]) },
    gate: { register: registers[(position + 2) % 3], cards: unique.filter(node => node.register === registers[(position + 2) % 3]) },
    write: { register: registers[position], cards: unique.filter(node => node.register === registers[position]) },
    provenance: provenance ? [provenance] : [],
  };
}
