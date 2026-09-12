import { Box, Button, Group, Stack, Text, ThemeIcon } from '@mantine/core';
import { IconArrowDown, IconArrowLeft, IconArrowRight, IconEye, IconPencil, IconRoute, IconSeedling, IconTool } from '@tabler/icons-react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { domainForRegister, metaForRegister } from '../registers';
import { actionPropositions, actionTargetIds, attestationDirections, writePropositions } from '../participationOptions';
import { directionSlots } from '../directionalPresentation';
import { DirectionalWhirl } from './DirectionalWhirl';
import { OperationIcon } from './OperationIcon';
import { PropositionNavigator } from './PropositionNavigator';
import { LineagePanel } from './LineagePanel';
import { PropositionCard } from './PropositionCard';
import { PropositionInspector } from './PropositionInspector';
import type { ActionName, ContributionInitialValues, ContributionInput, DomainName, Identifier, Observables, ParticipantPlan, PhaseKind, Proposition, PropositionSelection, RegisterName } from '../types';

type LaneRole = 'read' | 'gate' | 'write';
type ActionHandler = (action: ActionName, initialValues?: ContributionInitialValues) => void;
type InspectHandler = (selection: PropositionSelection) => void;
interface Lane { key: string; role: LaneRole; register?: RegisterName; virtual?: boolean; meta?: typeof provenanceMeta; featured?: Proposition; }
const roleMeta = {
  read: { label: 'Read', description: 'Where we came from', icon: IconEye },
  gate: { label: 'Gate', description: 'What this move supersedes', icon: IconRoute },
  write: { label: 'Write', description: 'Where we can go', icon: IconPencil },
};
const provenanceMeta = { label: 'Provenance', description: 'The inherited proposition from which this cycle begins.', color: 'indigo', icon: IconSeedling };
const generativeActions = { 'proposition.variant': 'Variant', 'proposition.bias': 'Bias' } as const;

export function ParticipantWorkspace({ plan, availableActions, onAction, onSubmit, busy = false, error, status }: {
  plan: ParticipantPlan; availableActions: ActionName[]; onAction: ActionHandler;
  onSubmit?: (input: ContributionInput) => Promise<boolean>; busy?: boolean; error?: unknown; status?: ReactNode;
}) {
  const propositions = useMemo<Proposition[]>(() => [
    plan.gate, plan.workspace?.provenance, ...(plan.lineage || []), ...(plan.read_options || []),
    ...Object.values(plan.workspace?.registers || {}).flatMap(items => items || []), ...(plan.write_options || []),
    ...(plan.traversal_options || []), ...(plan.instrument_options || []), ...(plan.revision_options || []),
    ...(plan.gate_options || []), ...(plan.attestation_options || []),
  ].filter((item): item is Proposition => Boolean(item)), [plan]);
  const byId = useMemo(() => new Map<Identifier, Proposition>(propositions.map(item => [item.id, item])), [propositions]);
  const gate = resolve(plan.gate, byId);
  const provenance = resolve(plan.workspace?.provenance || plan.lineage?.find(item => item.tier === 'committal') || gate, byId);
  const domainName = plan.workspace?.domain || domainForRegister(plan.phase.write_register);
  const phaseNumber = plan.phase.number || phaseNumberFor(plan.phase.kind);
  const [inspection, setInspection] = useState<PropositionSelection | null>(null);
  useEffect(() => setInspection(null), [plan.phase.id]);
  const lineage = (plan.lineage || []).map(item => resolve(item, byId)).filter(item => item.id !== gate.id);
  const lanes = buildLanes({ phaseNumber, domainName, writeRegister: plan.phase.write_register, gate, provenance });
  const writeOptions = writePropositions(plan);
  const targets = useMemo(() => new Map(availableActions.map(action => [action, actionTargetIds(plan, action)])), [plan, availableActions]);
  const candidates = plan.phase.kind === 'bias' ? actionPropositions(plan, 'proposition.bias')
    : plan.phase.kind === 'mediate' ? actionPropositions(plan, 'proposition.reconcile') : [];
  const allowed = (action: ActionName) => availableActions.includes(action) && Boolean(plan.schema?.$defs?.actions?.[action]);
  function card(proposition: Proposition, kind: string) {
    return <PropositionCard key={proposition.id} proposition={proposition} committalObservables={provenance.observables}
      compact flow={kind} onClick={() => setInspection({ kind, proposition })} />;
  }
  return <Box className="operational-workspace" data-domain={domainName} data-phase={phaseNumber}>
    <Box className="operational-workspace__body">
      <Box component="fieldset" disabled={busy} className="operational-board" aria-busy={busy}>
        <LineagePanel lineage={lineage} committalObservables={provenance.observables} onInspect={setInspection} />
        <Box className="operational-regions">
          {lanes.map(lane => {
            const meta = lane.meta || metaForRegister(lane.register);
            const role = roleMeta[lane.role]; const RoleIcon = role.icon; const RegisterIcon = meta.icon;
            const registerItems = lane.register ? plan.workspace?.registers?.[lane.register] || [] : [];
            const singleRead = lane.featured || registerItems.find(item => item.id !== gate.id) || lineage.find(item => item.register === lane.register) || lineage[0];
            const reads = plan.phase.kind === 'mediate' ? plan.read_options || [] : singleRead ? [singleRead] : [];
            const gateCards = plan.phase.kind === 'bias' ? candidates : [lane.featured || gate, ...candidates.filter(item => item.id !== gate.id)];
            return <Box component="section" className="operational-row" data-role={lane.role} data-lane={lane.key} key={`${phaseNumber}-${lane.key}`}
              aria-label={role.label} style={{ '--register-color': `light-dark(var(--mantine-color-${meta.color}-7), var(--mantine-color-${meta.color}-4))`,
                '--register-bg-light': `var(--mantine-color-${meta.color}-0)`, '--register-bg-dark': `var(--mantine-color-${meta.color}-light)` }}>
              <Group className="operational-row__role" gap={7} wrap="nowrap">
                <RoleIcon size={17} /><Box><Text fw={600} size="xs">{role.label}</Text><Text size="10px" c="dimmed">{role.description}</Text></Box>
              </Group>
              <Box className="register-row" data-virtual={lane.virtual || undefined}>
                <Group className="register-row__header" gap="xs" wrap="nowrap">
                  <ThemeIcon color={meta.color} variant="light" size="sm"><RegisterIcon size={15} /></ThemeIcon>
                  <Text className="register-row__title" size="xs" fw={700}>{meta.label}</Text>
                  <Text size="xs" c="dimmed" visibleFrom="sm" truncate>{meta.description}</Text>
                </Group>
                <Box className="register-row__content">
                  {lane.role === 'read' && (reads.length ? <PropositionNavigator label="Read Propositions" resetKey={plan.phase.id}>{reads.map(item => card(resolve(item, byId), 'read'))}</PropositionNavigator>
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
                      {item.id === gate.id && allowed('proposition.reclassify') && <Group className="write-option-card__context-actions" gap={4}>
                        <Button size="compact-xs" variant="subtle" color="gray" leftSection={<OperationIcon operation="reclassify" />}
                          onClick={() => onAction('proposition.reclassify')}>Reclassify</Button>
                      </Group>}
                    </Box>)}
                  </PropositionNavigator> : <EmptyState text="No eligible Gate Proposition is available here." />)}
                  {lane.role === 'write' && <Stack gap="xs" className="write-row">
                    {status}
                    {Object.keys(generativeActions).some(action => allowed(action as ActionName)) && <Group className="write-generative-actions" gap={6} aria-label="Generative Write actions">
                      <Text size="10px" c="dimmed">Generate</Text>
                      {Object.entries(generativeActions).filter(([action]) => allowed(action as ActionName)).map(([action, label]) => <Button key={action}
                        variant="light" color="gray" size="compact-xs" leftSection={<OperationIcon operation={action} />}
                        onClick={() => onAction(action as ActionName)}>{label}</Button>)}
                    </Group>}
                    {writeOptions.length ? <PropositionNavigator label="Write Propositions" resetKey={plan.phase.id}>
                      {writeOptions.map(proposition => <WriteOptionCard key={proposition.id} proposition={proposition}
                        directions={attestationDirections(plan, proposition.id)}
                        canAttest={allowed('attestation.create') && Boolean(targets.get('attestation.create')?.has(proposition.id))}
                        canRevise={allowed('proposition.revise') && Boolean(targets.get('proposition.revise')?.has(proposition.id))}
                        canInstrument={allowed('instrument.execute') && Boolean(targets.get('instrument.execute')?.has(proposition.id))}
                        canCommit={allowed('proposition.commit') && Boolean(targets.get('proposition.commit')?.has(proposition.id))}
                        canTraverse={allowed('proposition.traverse') && Boolean(targets.get('proposition.traverse')?.has(proposition.id))}
                        committalObservables={provenance.observables} onInspect={setInspection} onAction={onAction} />)}
                    </PropositionNavigator> : <EmptyState text="No existing Proposition is available in Write." />}
                  </Stack>}
                </Box>
              </Box>
              {lane.role !== 'write' && <IconArrowDown className="region-transition" size={24} stroke={2.2} aria-hidden="true" />}
            </Box>;
          })}
        </Box>
      </Box>
      <PropositionInspector selection={inspection} committalObservables={provenance.observables} onClose={() => setInspection(null)}
        plan={plan} onSubmit={onSubmit} loading={busy} error={error} />
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
    const traverse = canTraverse && !directions.includes(slot.label);
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
      {(canCommit || canRevise || canInstrument) && <Group className="write-option-card__context-actions" data-action-region="middle" gap={4} justify="center">
        {canCommit && <Button size="compact-xs" color="indigo" leftSection={<OperationIcon operation="commit" />} onClick={() => onAction('proposition.commit', { proposition_id: proposition.id })}>Commit</Button>}
        {canRevise && <Button size="compact-xs" variant="subtle" color="gray" leftSection={<OperationIcon operation="revise" />} onClick={() => onAction('proposition.revise', { proposition_id: proposition.id })}>Revise</Button>}
        {canInstrument && <Button size="compact-xs" variant="subtle" color="gray" leftSection={<IconTool size={13} />} onClick={() => onAction('instrument.execute', { proposition_id: proposition.id })}>Instrument</Button>}
      </Group>}
      {(canAttest || canTraverse) && <Box className="write-option-card__actions write-option-card__actions--directional" role="group" aria-label={`Attestation and traversal for ${proposition.title || proposition.statement.split('\n')[0]}`}>
        {slots.map(slot => <Box key={slot.side} className="write-option-card__side" data-action-region={slot.side}>{directionalAction(slot)}</Box>)}
      </Box>}
    </>}
  </Box>;
}
function EmptyState({ text }: { text: string }) { return <Box className="lane-empty"><Text size="xs" c="dimmed">{text}</Text></Box>; }

function buildLanes({ phaseNumber, domainName, writeRegister, gate, provenance }: {
  phaseNumber: number;
  domainName: DomainName;
  writeRegister: RegisterName;
  gate: Proposition;
  provenance: Proposition;
}): Lane[] {
  if (phaseNumber === 1) return [
    { key: 'provenance-read', role: 'read', virtual: true, meta: provenanceMeta, featured: provenance },
    { key: 'provenance-gate', role: 'gate', virtual: true, meta: provenanceMeta, featured: gate },
    { key: writeRegister, role: 'write', register: writeRegister },
  ];
  if (phaseNumber === 2) return [
    { key: 'provenance', role: 'read', virtual: true, meta: provenanceMeta, featured: provenance },
    { key: `gate-${gate.register}`, role: 'gate', register: gate.register, featured: gate },
    { key: writeRegister, role: 'write', register: writeRegister },
  ];
  const assignments = operationalAssignment(domainName, writeRegister, gate.register);
  return [
    { key: assignments.read, role: 'read', register: assignments.read },
    { key: assignments.gate, role: 'gate', register: assignments.gate, featured: gate },
    { key: assignments.write, role: 'write', register: assignments.write },
  ];
}

function operationalAssignment(domainName: DomainName, writeRegister: RegisterName, gateRegister: RegisterName) {
  const registers: RegisterName[] = domainName === 'settlement' ? ['signal', 'stance', 'concord'] : ['activity', 'frontier', 'canon'];
  const writeIndex = registers.indexOf(writeRegister);
  if (writeIndex >= 0) return { read: registers[(writeIndex + 1) % 3], gate: registers[(writeIndex + 2) % 3], write: registers[writeIndex] };
  const gateIndex = registers.indexOf(gateRegister);
  if (gateIndex >= 0) return { read: registers[(gateIndex + 2) % 3], gate: registers[gateIndex], write: registers[(gateIndex + 1) % 3] };
  return { read: registers[0], gate: registers[1], write: registers[2] };
}

function phaseNumberFor(kind: PhaseKind) { return { distinguish: 1, persist: 2, constrain: 3, disperse: 4, mediate: 5, bias: 6, order: 7 }[kind]; }
function resolve(proposition: Proposition, byId: Map<Identifier, Proposition>): Proposition { return byId.get(proposition.id) || proposition; }
