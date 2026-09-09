import type { ActionName, ParticipantPlan, Proposition } from './types';

/** The schema is authoritative for both visible buttons and form choices. */
export function actionTargetIds(plan: ParticipantPlan, action: ActionName): Set<string> {
  const property = action === 'proposition.reconcile' ? 'other_proposition_id' : 'proposition_id';
  return new Set(plan.schema?.$defs?.actions?.[action]?.properties[property]?.enum ?? []);
}

export function actionPropositions(plan: ParticipantPlan, action: ActionName): Proposition[] {
  const allowed = actionTargetIds(plan, action);
  const candidates = [
    ...(plan.attestation_options ?? []), ...(plan.write_options ?? []),
    ...(plan.revision_options ?? []), ...(plan.gate_options ?? []),
    ...Object.values(plan.workspace?.registers ?? {}).flatMap((items) => items ?? []),
    plan.gate,
  ];
  return [...new Map(candidates.filter((item) => allowed.has(item.id)).map((item) => [item.id, item])).values()];
}
