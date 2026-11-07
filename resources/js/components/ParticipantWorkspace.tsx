import { Badge, Box, Button, Group, Paper, Stack, Text, Title } from '@mantine/core';
import type { ReactNode } from 'react';
import { actionTargetIds, attestationDirections } from '../participationOptions';
import { metaForRegister } from '../registers';
import type { ActionName, CognitiveProposition, ContributionInitialValues, ReadyParticipation } from '../types';
import { CognitiveReceiptPanel } from './CognitiveReceiptPanel';
import { PropositionNavigator } from './PropositionNavigator';

const roles = ['read', 'gate', 'write'] as const;
const labels: Record<ActionName, string> = {
  'attestation.create': 'Attest with rationale', 'instrument.execute': 'Instrument',
  'proposition.commit': 'Commit', 'proposition.reconcile': 'Reconcile', 'proposition.reclassify': 'Reclassify',
  'proposition.revise': 'Revise', 'proposition.traverse': 'Traverse', 'proposition.variant': 'Variant',
  'preparation.start': 'Prepare Artifacts', 'preparation.defer': 'Resolve without contribution',
  'acquisition.defer': 'Defer', 'participant.rest': 'Rest',
};

type ActionHandler = (action: ActionName, values?: ContributionInitialValues) => void;

export function ParticipantWorkspace({ plan, availableActions: offered, onAction, onForm, busy, completion, preparation, status }: {
  plan: ReadyParticipation; availableActions: ActionName[]; onAction: ActionHandler; onForm: ActionHandler;
  busy?: boolean; completion?: ReactNode; preparation?: ReactNode; status?: ReactNode;
}) {
  const { context } = plan;
  const availableActions = offered.filter(action => !!plan.schema.$defs?.actions?.[action]);
  const targets = new Map(availableActions.map(action => [action, actionTargetIds(plan, action)]));
  const visible = new Set(roles.flatMap(role => context[role].propositions.map(node => node.id)));
  const general = availableActions.filter(action => {
    // These have dedicated lifecycle controls.
    if (action === 'participant.rest' || action === 'preparation.defer') return false;
    const ids = targets.get(action)!;
    const props = plan.schema.$defs!.actions![action]!.properties;
    return (!props.proposition_id && !props.other_proposition_id) || [...ids].some(id => !visible.has(id));
  });
  return <Box className="operational-workspace">
    {preparation}
    <Box component="fieldset" disabled={busy} className="operational-board" aria-busy={busy}>
      {roles.map(role => {
        const register = context[role];
        return <Stack component="section" key={role} gap="xs" aria-label={role} data-role={role}>
          <Group gap="sm" align="baseline">
            <Title order={3} size="h4" tt="capitalize">{role}</Title>
            {role === 'write' && <Text size="sm">{metaForRegister(plan.phase.write_register).label}</Text>}
          </Group>
          {register.question && <Text fw={500}>{register.question}</Text>}
          {register.propositions.length > 0 ? <PropositionNavigator label={role} resetKey={plan.phase.id}>
            {register.propositions.map(node => <Paper key={node.id} component="article" withBorder p="md" radius="md" data-proposition-id={node.id}>
              <CognitiveCard node={node} acting={role === 'gate' && node.id === context.gate.acting_id} />
              <Group gap="xs" mt="xs">
                {availableActions.filter(action => targets.get(action)?.has(node.id)).map(action => {
                  if (action === 'attestation.create') return <Group key={action} gap="xs">
                    {attestationDirections(plan, node.id).map(direction => <Button size="xs" variant="light" key={direction}
                      onClick={() => onAction(action, { proposition_id: node.id, direction })}>{node.attestation?.direction === direction ? 'Reaffirm ' : ''}{direction === 'support' ? 'Support' : 'Refute'}</Button>)}
                    <Button size="xs" variant="subtle" onClick={() => onForm(action, { proposition_id: node.id })}>{labels[action]}</Button>
                  </Group>;
                  return <Group key={action} gap="xs"><Button size="xs" variant="light" onClick={() => onAction(action,
                    action === 'proposition.reconcile' ? { other_proposition_id: node.id } : { proposition_id: node.id })}>{labels[action]}</Button>
                    {action === 'proposition.traverse' && plan.schema.$defs?.actions?.[action]?.properties.receipt_options &&
                      <Button size="xs" variant="subtle" onClick={() => onForm(action, { proposition_id: node.id })}>Traverse with Receipt options</Button>}
                  </Group>;
                })}
              </Group>
            </Paper>)}
          </PropositionNavigator> : <Text size="sm" c="dimmed">No Propositions in {role}.</Text>}
          {role === 'write' && <>
            <Group gap="xs">{general.map(action => <Button key={action} variant="light" size="sm" onClick={() => onForm(action)}>{labels[action]}</Button>)}</Group>
            {status}
          </>}
        </Stack>;
      })}
      <CognitiveReceiptPanel context={context} />
    </Box>
    {completion}
  </Box>;
}

function CognitiveCard({ node, acting }: { node: CognitiveProposition; acting: boolean }) {
  const register = metaForRegister(node.register);
  return <Stack gap="xs" style={{ overflowWrap: 'anywhere' }}>
    <Group gap="xs"><Badge color={register.color} variant="light">{register.label}</Badge>
      {acting ? <Text size="xs">Acting Gate</Text> : node.relation && <Text size="xs" tt="capitalize">{node.relation}</Text>}
      {node.participant && <Text size="sm" fw={600}>{node.participant.callsign}</Text>}
    </Group>
    <Text style={{ whiteSpace: 'pre-wrap' }}>{node.statement}{node.statement_truncated && '…'}</Text>
    {node.attestation && <Text size="sm">Your Attestation · {node.attestation.direction}{node.attestation.rationale && ' · ' + node.attestation.rationale}</Text>}
    {node.explored && <Text size="xs">Explored by you</Text>}
    {(node.exhausted || node.continuation_exhausted) && <Text size="xs">{node.basin_exhausted ? 'Exhausted basin' : 'Exhausted continuation'}</Text>}
    {node.instruments?.map((item, index) => <Text size="xs" key={index}>{item.kind} · {item.status} · {item.reference} {item.failure_reason}</Text>)}
    <details><summary>Proposition details</summary><Text size="xs">{node.id} · {node.tier}</Text>
      {node.parent_ids?.map(id => <Text key={id} size="xs">Parent · {id}</Text>)}
      {node.receipts.map(id => <Text key={id} size="xs">Receipt · {id}</Text>)}
    </details>
  </Stack>;
}
