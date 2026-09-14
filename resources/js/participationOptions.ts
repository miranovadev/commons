import type { ActionName, AttestationDirection, ContributionInitialValues, ContributionInput, ParticipantPlan, Proposition } from './types';

/** Only the workspace's fully specified, schema-authorized navigation actions bypass a form. */
export function quickContribution(plan: ParticipantPlan, action: ActionName, values: ContributionInitialValues = {}): ContributionInput | null {
  if ((action === 'receipt.read' || action === 'receipt.replay')) return values.receipt_id && actionTargetIds(plan, action).has(values.receipt_id) ? { action, receipt_id: values.receipt_id } : null;
  if (!values.proposition_id || !actionTargetIds(plan, action).has(values.proposition_id)) return null;
  if (action === 'proposition.traverse') return { action, proposition_id: values.proposition_id };
  if (action === 'attestation.create' && values.direction && attestationDirections(plan, values.proposition_id).includes(values.direction)) {
    return { action, proposition_id: values.proposition_id, direction: values.direction };
  }
  return null;
}

/** The schema is authoritative for both visible buttons and form choices. */
export function actionTargetIds(plan: ParticipantPlan, action: ActionName): Set<string> {
  const property = (action === 'receipt.read' || action === 'receipt.replay') ? 'receipt_id' : action === 'proposition.reconcile' ? 'other_proposition_id' : 'proposition_id';
  return new Set(plan.schema?.$defs?.actions?.[action]?.properties[property]?.enum ?? []);
}

export function actionPropositions(plan: ParticipantPlan, action: ActionName): Proposition[] {
  const allowed = actionTargetIds(plan, action);
  const candidates = [
    ...(plan.traversal_options ?? []), ...(plan.instrument_options ?? []),
    ...(plan.attestation_options ?? []), ...(plan.write_options ?? []),
    ...(plan.revision_options ?? []), ...(plan.gate_options ?? []),
    ...Object.values(plan.workspace?.registers ?? {}).flatMap((items) => items ?? []),
    plan.gate,
  ];
  return [...new Map(candidates.filter((item) => allowed.has(item.id)).map((item) => [item.id, item])).values()];
}

/** Read the same per-target direction branches validated by the server. */
export function attestationDirections(plan: ParticipantPlan, propositionId?: string): AttestationDirection[] {
  if (!propositionId || !actionTargetIds(plan, 'attestation.create').has(propositionId)) return [];
  const schema = plan.schema?.$defs?.actions?.['attestation.create'];
  const choices = schema?.anyOf;
  const directions = choices
    ? choices.filter((choice) => choice.properties.proposition_id?.enum?.includes(propositionId))
      .flatMap((choice) => choice.properties.direction?.enum ?? [])
    : schema?.properties.direction?.enum ?? [];
  return (['negative', 'positive'] as AttestationDirection[]).filter((direction) => directions.includes(direction));
}

/** The backend's forward frontier is the only source of Write cards. */
export function writePropositions(plan: ParticipantPlan): Proposition[] {
  const actionable = new Set(([
    'attestation.create', 'proposition.traverse', 'proposition.revise',
    'instrument.execute', 'proposition.commit',
  ] as ActionName[]).flatMap((action) => [...actionTargetIds(plan, action)]));
  return [...new Map([...(plan.write_options ?? []), ...actionPropositions(plan, 'instrument.execute')].filter((item) => actionable.has(item.id))
    .map((item) => [item.id, item])).values()];
}
