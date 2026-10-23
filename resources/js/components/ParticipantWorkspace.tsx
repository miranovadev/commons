import { Box, Button, Group, Stack, Text, ThemeIcon } from '@mantine/core';
import { IconArrowDown, IconArrowLeft, IconArrowRight, IconEye, IconPencil, IconRoute, IconTool } from '@tabler/icons-react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { metaForRegister } from '../registers';
import { actionTargetIds, attestationDirections } from '../participationOptions';
import { phaseRegisterPreview } from '../phasePreview';
import { workspaceDomain, workspaceLanes, workspaceProvenance } from '../workspaceLanes';
import { HorizonContext } from './HorizonContext';
import { directionSlots } from '../directionalPresentation';
import { DirectionalWhirl } from './DirectionalWhirl';
import { OperationIcon } from './OperationIcon';
import { PropositionNavigator } from './PropositionNavigator';
import { ReceiptContextPanel } from './ReceiptContextPanel';
import { LineagePanel } from './LineagePanel';
import { PropositionCard } from './PropositionCard';
import { PropositionInspector } from './PropositionInspector';
import type { ActionName, ContributionInitialValues, ContributionInput, Observables, ParticipantPlan, PhaseKind, Proposition, PropositionSelection } from '../types';

type ActionHandler = (action: ActionName, initialValues?: ContributionInitialValues) => void;
type InspectHandler = (selection: PropositionSelection) => void;
const roleMeta = {
  read: { label: 'Read', description: 'Where we came from', icon: IconEye },
  gate: { label: 'Gate', description: 'What we can do here', icon: IconRoute },
  write: { label: 'Write', description: 'Where we can go', icon: IconPencil },
};

export function ParticipantWorkspace({ plan, availableActions, onAction, onSubmit, busy = false, error, status, completion, previewKind }: {
  plan: ParticipantPlan; availableActions: ActionName[]; onAction: ActionHandler;
  onSubmit?: (input: ContributionInput) => Promise<boolean>; busy?: boolean; error?: unknown; status?: ReactNode; completion?: ReactNode; previewKind?: PhaseKind;
}) {
  const gate = plan.gate;
  const provenance = workspaceProvenance(plan);
  const domainName = workspaceDomain(plan);
  const preview = previewKind ? phaseRegisterPreview(plan, previewKind) : null;
  const phaseNumber = preview?.number || plan.phase.number || phaseNumberFor(plan.phase.kind);
  const [inspection, setInspection] = useState<PropositionSelection | null>(null);
  useEffect(() => setInspection(null), [plan.phase.id, previewKind]);
  const lineage = plan.lineage || [];
  const lanes = workspaceLanes(plan, previewKind);
  const targets = useMemo(() => new Map(availableActions.map(action => [action, actionTargetIds(plan, action)])), [plan, availableActions]);
  const allowed = (action: ActionName) => !preview && availableActions.includes(action) && Boolean(plan.schema?.$defs?.actions?.[action]);
  function card(proposition: Proposition, kind: string) {
    return <PropositionCard key={proposition.id} proposition={proposition} committalObservables={provenance.observables}
      compact flow={kind} onClick={() => setInspection({ kind, proposition })} />;
  }
  return <Box className="operational-workspace" data-domain={domainName} data-phase={phaseNumber} data-preview={Boolean(preview)}>
    <Box className="operational-workspace__body">
      <Box component="fieldset" disabled={busy} className="operational-board" aria-busy={busy}>
        {!preview && <HorizonContext horizon={plan.workspace?.horizon} domain={domainName} provenance={provenance} onInspect={setInspection} />}
        <LineagePanel lineage={lineage} committalObservables={provenance.observables} onInspect={setInspection} />
        {!preview && <ReceiptContextPanel plan={plan} />}
        {allowed('preparation.start') && <Button onClick={() => onAction('preparation.start')}>Prepare Artifacts</Button>}
        {!preview && plan.preparation_failures?.map(failure => <Box key={failure.execution_id} p="sm">
          <Text size="sm">Preparation failed · {failure.purpose}</Text>
          <Text size="xs">{failure.diagnostic.failure?.stage}: {failure.diagnostic.failure_reason}</Text>
          <Text size="xs" c="dimmed">No successful Preparation Receipt was created. Other lawful actions remain available; a later Cycle restores the allowance.</Text>
        </Box>)}
        <Box className="operational-regions">
          {lanes.map((lane, index) => {
            const meta = metaForRegister(lane.register);
            const role = roleMeta[lane.role]; const RoleIcon = role.icon; const RegisterIcon = meta.icon;
            const reads = lane.cards;
            const gateCards = lane.cards;
            const writeOptions = lane.cards;
            return <Box component="section" className="operational-row" data-role={lane.role} data-lane={lane.key} key={`${phaseNumber}-${lane.key}`}
              aria-label={role.label} style={{ '--register-color': `light-dark(var(--mantine-color-${meta.color}-7), var(--mantine-color-${meta.color}-4))`,
                '--register-bg-light': `var(--mantine-color-${meta.color}-0)`, '--register-bg-dark': `var(--mantine-color-${meta.color}-light)` }}>
              <Group className="operational-row__role" gap={7} wrap="nowrap">
                <RoleIcon size={17} /><Box><Text fw={600} size="xs">{role.label}</Text><Text size="10px" c="dimmed">{role.description}</Text></Box>
              </Group>
              <Box className="register-row" data-register={lane.register}>
                <Group className="register-row__header" gap="xs" wrap="nowrap">
                  <ThemeIcon color={meta.color} variant="light" size="sm"><RegisterIcon size={15} /></ThemeIcon>
                  <Text className="register-row__title" size="xs" fw={700}>{meta.label}</Text>
                  <Text size="xs" c="dimmed" visibleFrom="sm" truncate>{meta.description}</Text>
                </Group>
                {lane.role === 'write' && lanes.findIndex(item => item.role === 'write') === index && !preview && status && <Stack gap="xs" className="write-row__tools">{status}</Stack>}
                <Box className="register-row__content">
                  {preview ? (lane.cards.length ? <PropositionNavigator label={`${role.label} register preview`} resetKey={`${plan.phase.id}-${preview.kind}`}>
                    {lane.cards.map(item => card(item, 'register preview'))}
                  </PropositionNavigator> : <EmptyState text="No cards from this register are loaded." />) : <>
                  {lane.role === 'read' && (reads.length ? <PropositionNavigator label="Read Propositions" resetKey={plan.phase.id}>{reads.map(item => card(item, 'read'))}</PropositionNavigator>
                    : <EmptyState text="No readable Proposition is available here." />)}
                  {lane.role === 'gate' && (gateCards.length ? <PropositionNavigator label="Gate Propositions" resetKey={plan.phase.id}>
                    {gateCards.map(item => <Box key={item.id} className="write-option-card" data-reconcile-candidate={plan.phase.kind === 'mediate' && item.id !== gate.id ? item.id : undefined}
                      data-bias-candidate={plan.phase.kind === 'bias' ? item.id : undefined}>
                      {plan.phase.kind === 'mediate' && <Text size="10px" c="dimmed" className="gate-card-context">{item.id === gate.id ? 'Current Gate · statement retained' : 'Reconciling counterpart'}</Text>}
                      {card(item, 'gate')}
                      {item.id !== gate.id && plan.phase.kind === 'mediate' && allowed('proposition.reconcile') && targets.get('proposition.reconcile')?.has(item.id) && <Group className="write-option-card__context-actions" gap={4}>
                        <Button size="compact-xs" variant="subtle" color="gray" className="reconcile-action" leftSection={<OperationIcon operation="reconcile" />}
                          onClick={() => onAction('proposition.reconcile', { other_proposition_id: item.id })}>Reconcile</Button>
                      </Group>}
                      {item.id === gate.id && allowed('instrument.execute') && targets.get('instrument.execute')?.has(item.id) && <Group className="write-option-card__context-actions" gap={4}>
                        <Button size="compact-xs" variant="subtle" color="gray" leftSection={<IconTool size={13} />}
                          onClick={() => onAction('instrument.execute', { proposition_id: item.id })}>Instrument</Button>
                      </Group>}
                      {item.id === gate.id && allowed('proposition.variant') && <Group className="write-option-card__context-actions" gap={4}>
                        <Button size="compact-xs" variant="subtle" color="gray" leftSection={<OperationIcon operation="variant" />}
                          onClick={() => onAction('proposition.variant')}>Variant</Button>
                      </Group>}
                      {allowed('proposition.commit') && targets.get('proposition.commit')?.has(item.id) && <Group className="write-option-card__context-actions" gap={4}>
                        <Button size="compact-xs" color="indigo" leftSection={<OperationIcon operation="commit" />}
                          onClick={() => onAction('proposition.commit', { proposition_id: item.id })}>Commit</Button>
                      </Group>}
                      {allowed('proposition.reclassify') && targets.get('proposition.reclassify')?.has(item.id) && <Group className="write-option-card__context-actions" gap={4}>
                        <Button size="compact-xs" variant="subtle" color="gray" leftSection={<OperationIcon operation="reclassify" />}
                          onClick={() => onAction('proposition.reclassify', { proposition_id: item.id })}>Reclassify</Button>
                      </Group>}
                    </Box>)}
                  </PropositionNavigator> : <EmptyState text="No eligible Gate Proposition is available here." />)}
                  {lane.role === 'write' && <Stack gap="xs" className="write-row">
                    {writeOptions.length ? <PropositionNavigator label="Write Propositions" resetKey={plan.phase.id}>
                      {writeOptions.map(proposition => <WriteOptionCard key={proposition.id} proposition={proposition}
                        directions={attestationDirections(plan, proposition.id)}
                        canAttest={allowed('attestation.create') && Boolean(targets.get('attestation.create')?.has(proposition.id))}
                        canRevise={allowed('proposition.revise') && Boolean(targets.get('proposition.revise')?.has(proposition.id))}
                        canInstrument={allowed('instrument.execute') && Boolean(targets.get('instrument.execute')?.has(proposition.id))}
                        canCommit={allowed('proposition.commit') && Boolean(targets.get('proposition.commit')?.has(proposition.id))}
                        canTraverse={allowed('proposition.traverse') && Boolean(targets.get('proposition.traverse')?.has(proposition.id))}
                        committalObservables={provenance.observables} onInspect={setInspection} onAction={onAction} />)}
                    </PropositionNavigator> : !lane.history?.length && <EmptyState text="No existing Proposition is available in Write." />}
                  </Stack>}
                  {Boolean(lane.history?.length) && <Stack gap="xs" p="xs" aria-label={`Exhausted ${role.label} alternatives`}>
                    {lane.history!.map(item => <Box key={item.id} data-exhausted-id={item.id}>
                      <Text size="xs" c="dimmed" fw={600}>{item.basin_exhausted ? 'Horizon exhausted · Committal retained' : 'Failed continuation · Context only'}</Text>
                      <Text size="sm" c="dimmed">{item.statement}</Text>
                    </Box>)}
                  </Stack>}
                  </>}
                </Box>
              </Box>
              {lane.role === 'write' && index === lanes.length - 1 && completion}
              {lane.role !== 'write' && <IconArrowDown className="region-transition" size={24} stroke={2.2} aria-hidden="true" />}
            </Box>;
          })}
        </Box>
      </Box>
      <PropositionInspector selection={inspection} committalObservables={provenance.observables} onClose={() => setInspection(null)}
        plan={preview ? undefined : plan} onSubmit={preview ? undefined : onSubmit} loading={busy} error={error} />
    </Box>
  </Box>;
}

function WriteOptionCard({ proposition, directions, canAttest, canRevise, canInstrument, canTraverse, canCommit, committalObservables, onInspect, onAction }: {
  proposition: Proposition; directions: Array<'refute' | 'support'>; canAttest: boolean; canRevise: boolean;
  canInstrument: boolean; canTraverse: boolean; canCommit: boolean; committalObservables?: Observables; onInspect: InspectHandler; onAction: ActionHandler;
}) {
  const hasActions = canAttest || canRevise || canInstrument || canTraverse || canCommit;
  const slots = directionSlots(proposition.attestation_summary?.support_direction);
  function directionalAction(slot: typeof slots[number]) {
    const attest = canAttest && directions.includes(slot.label);
    const traverse = proposition.tier === 'expressive' && canTraverse && !directions.includes(slot.label);
    if (!attest && !traverse) return null;
    const whirl = <DirectionalWhirl direction={slot.direction} size={17} />;
    const portal = <Group gap={2} wrap="nowrap">{slot.side === 'left' ? <>{whirl}<IconArrowLeft size={14} aria-hidden="true" /></>
      : <><IconArrowRight size={14} aria-hidden="true" />{whirl}</>}</Group>;
    return <Button size="compact-xs" variant="subtle" color={slot.color} className="directional-action attestation-semantic" data-label={slot.label}
      data-direction={slot.direction || 'unknown'}
      leftSection={slot.side === 'left' ? traverse ? portal : whirl : undefined}
      rightSection={slot.side === 'right' ? traverse ? portal : whirl : undefined}
      onClick={() => traverse ? onAction('proposition.traverse', { proposition_id: proposition.id })
        : onAction('attestation.create', { proposition_id: proposition.id, direction: slot.label })}>{traverse ? 'Traverse' : slot.text}</Button>;
  }
  return <Box className="write-option-card" data-attested={proposition.attestation?.direction}>
    <PropositionCard proposition={proposition} committalObservables={committalObservables} compact flow="write" onClick={() => onInspect({ kind: 'write option', proposition })} />
    {hasActions && <>
      {proposition.tier === 'committal' && canTraverse && <Group className="write-option-card__context-actions" gap={4} justify="center">
        <Button size="compact-xs" variant="subtle" color="indigo" leftSection={<IconArrowRight size={14} />}
          onClick={() => onAction('proposition.traverse', { proposition_id: proposition.id })}>Traverse</Button>
      </Group>}
      {(canCommit || canRevise) && <Group className="write-option-card__context-actions" data-action-region="middle" gap={4} justify="center">
        {canCommit && <Button size="compact-xs" color="indigo" leftSection={<OperationIcon operation="commit" />} onClick={() => onAction('proposition.commit', { proposition_id: proposition.id })}>Commit</Button>}
        {canRevise && <Button size="compact-xs" variant="subtle" color="gray" leftSection={<OperationIcon operation="revise" />} onClick={() => onAction('proposition.revise', { proposition_id: proposition.id })}>Revise</Button>}
      </Group>}
      {proposition.tier === 'expressive' && (canAttest || canTraverse || canInstrument) && <Box className="write-option-card__actions write-option-card__actions--directional" role="group" aria-label={`Attestation and traversal for ${proposition.title || proposition.statement.split('\n')[0]}`}>
        <Box className="write-option-card__side" data-action-region="left">{directionalAction(slots[0])}</Box>
        <Box data-action-region="instrument">{canInstrument && <Button size="compact-xs" variant="subtle" color="gray" leftSection={<IconTool size={13} />} onClick={() => onAction('instrument.execute', { proposition_id: proposition.id })}>Instrument</Button>}</Box>
        <Box className="write-option-card__side" data-action-region="right">{directionalAction(slots[1])}</Box>
      </Box>}
    </>}
  </Box>;
}
function EmptyState({ text }: { text: string }) { return <Box className="lane-empty"><Text size="xs" c="dimmed">{text}</Text></Box>; }

function phaseNumberFor(kind: PhaseKind) { return { distinguish: 1, persist: 2, constrain: 3, disperse: 4, mediate: 5, bias: 6, order: 7 }[kind]; }
