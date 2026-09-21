import type { ActionName, AttestationDirection, ContributionInitialValues, ContributionInput, ParticipantPlan, Proposition, ReceiptReference } from './types';

/** Only the workspace's fully specified, schema-authorized navigation actions bypass a form. */
export function quickContribution(plan: ParticipantPlan, action: ActionName, values: ContributionInitialValues = {}): ContributionInput | null {
  if (!values.proposition_id || !actionTargetIds(plan, action).has(values.proposition_id)) return null;
  if (action === 'proposition.traverse') return { action, proposition_id: values.proposition_id };
  if (action === 'attestation.create' && values.direction && attestationDirections(plan, values.proposition_id).includes(values.direction)) {
    return { action, proposition_id: values.proposition_id, direction: values.direction };
  }
  return null;
}

/** The schema is authoritative for both visible buttons and form choices. */
export function actionTargetIds(plan: ParticipantPlan, action: ActionName): Set<string> {
  const property = action === 'proposition.reconcile' ? 'other_proposition_id' : 'proposition_id';
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
  return (['refute', 'support'] as AttestationDirection[]).filter((direction) => directions.includes(direction));
}

/** The backend's forward frontier is the only source of Write cards. */
export function writePropositions(plan: ParticipantPlan): Proposition[] {
  if (plan.phase.kind === 'order') return [...new Map([
    ...(plan.write_options ?? []).filter(node => node.tier === 'expressive'),
    ...actionPropositions(plan, 'proposition.traverse').filter(node => node.tier === 'committal'),
  ].map(node => [node.id, node])).values()];
  const actionable = new Set(([
    'attestation.create', 'proposition.traverse', 'proposition.revise',
    'instrument.execute', 'proposition.commit',
  ] as ActionName[]).flatMap((action) => [...actionTargetIds(plan, action)]));
  return [...new Map([...(plan.write_options ?? []), ...actionPropositions(plan, 'instrument.execute'),
    ...actionPropositions(plan, 'proposition.traverse')].filter((item) => actionable.has(item.id) || item.spent === true)
    .map((item) => [item.id, item])).values()];
}

/** References carry identity only; the schema alone authorizes uptake. */
export function propositionReceiptReferences(plan: ParticipantPlan): Array<ReceiptReference & { proposition_id: string }> {
  const nodes = [plan.gate, ...(plan.lineage ?? []), ...(plan.read_options ?? []), ...(plan.gate_options ?? []), ...(plan.write_options ?? [])];
  return [...new Map(nodes.filter(Boolean).flatMap(node => (node.receipts ?? []).map(receipt => ({
    id: receipt.id, type: receipt.type, reference: receipt.reference, loaded: receipt.loaded, proposition_id: node.id,
  }))).map(receipt => [receipt.id, receipt])).values()];
}

export function receiptChoices(plan: ParticipantPlan): ReceiptReference[] {
  return [...new Map([...propositionReceiptReferences(plan), ...(plan.receipts ?? [])].map(receipt => [receipt.id, receipt])).values()];
}
