import { Box, Button, Group, Menu, SimpleGrid, Stack, Text, ThemeIcon } from '@mantine/core';
import { IconBolt, IconEye, IconGitBranch, IconPencil, IconRefresh, IconRoute, IconSeedling, IconTool, IconWhirl } from '@tabler/icons-react';
import { Fragment, useEffect, useMemo, useState } from 'react';
import { domainForRegister, metaForRegister } from '../registers';
import { actionPropositions, attestationDirections, writePropositions } from '../participationOptions';
import { LineagePanel } from './LineagePanel';
import { PropositionCard } from './PropositionCard';
import { PropositionInspector } from './PropositionInspector';
import type {
  ActionName,
  ContributionInitialValues,
  ContributionInput,
  DomainName,
  Identifier,
  Observables,
  ParticipantPlan,
  PhaseKind,
  Proposition,
  PropositionSelection,
  RegisterName,
} from '../types';

type LaneRole = 'read' | 'gate' | 'write';
type InspectHandler = (selection: PropositionSelection) => void;
type ActionHandler = (action: ActionName, initialValues?: ContributionInitialValues) => void;

interface Lane {
  key: string;
  role: LaneRole;
  register?: RegisterName;
  virtual?: boolean;
  meta?: typeof provenanceMeta;
  featured?: Proposition;
}

const roleMeta = {
  read: { label: 'Read', description: 'Where we came from', icon: IconEye },
  gate: { label: 'Gate', description: 'What this move supersedes', icon: IconRoute },
  write: { label: 'Write', description: 'Where we can go', icon: IconPencil },
};
const provenanceMeta = { label: 'Provenance', description: 'The inherited proposition from which this cycle begins.', color: 'indigo', icon: IconSeedling };
const actionMeta = {
  'proposition.variant': { label: 'Variant', description: 'Open a new expressive branch from the current Gate.', icon: IconGitBranch },
  'proposition.reclassify': { label: 'Reclassify', description: 'Carry the Gate into this classification register.', icon: IconRefresh },
  'proposition.commit': { label: 'Commit', description: 'Write the expressive Gate into the committal structure.', icon: IconBolt },
} as const;
type SupersessionAction = keyof typeof actionMeta;
const cardActions = new Set<ActionName>(['attestation.create', 'instrument.execute', 'proposition.reconcile', 'proposition.revise', 'proposition.traverse']);

export function ParticipantWorkspace({ plan, availableActions, onAction, onSubmit, busy = false, error }: {
  plan: ParticipantPlan;
  availableActions: ActionName[];
  onAction: ActionHandler;
  onSubmit?: (input: ContributionInput) => Promise<boolean>;
  busy?: boolean;
  error?: unknown;
}) {
  const propositions = useMemo<Proposition[]>(() => [
    plan.gate, plan.workspace?.provenance, ...(plan.lineage || []),
    ...Object.values(plan.workspace?.registers || {}).flatMap((items) => items || []), ...(plan.write_options || []),
    ...(plan.traversal_options || []), ...(plan.instrument_options || []),
    ...(plan.revision_options || []), ...(plan.gate_options || []), ...(plan.attestation_options || []),
  ].filter((item): item is Proposition => Boolean(item)), [plan]);
  const byId = useMemo(() => new Map<Identifier, Proposition>(propositions.map((item) => [item.id, item])), [propositions]);
  const gate = resolve(plan.gate, byId);
  const provenance = resolve(plan.workspace?.provenance || plan.lineage?.find((item) => item.tier === 'committal') || gate, byId);
  const domainName = plan.workspace?.domain || domainForRegister(plan.phase.write_register);
  const phaseNumber = plan.phase.number || phaseNumberFor(plan.phase.kind);
  const actions = availableActions.filter((action) => isSupersessionAction(action) || cardActions.has(action));
  const supersessionActions = actions.filter(isSupersessionAction);
  const propositionActions = actions.filter((action) => !isSupersessionAction(action));
  const [inspection, setInspection] = useState<PropositionSelection | null>(null);
  useEffect(() => setInspection(null), [plan.phase.id]);
  const registerItems = useMemo(() => Object.fromEntries(
    Object.entries(plan.workspace?.registers || {}).map(([register, items]) => [register, (items || []).map((item) => resolve(item, byId))]),
  ) as Partial<Record<RegisterName, Proposition[]>>, [byId, plan.workspace?.registers]);
  const lineage = useMemo(() => (plan.lineage || []).map((item) => resolve(item, byId)).filter((item) => item.id !== gate.id), [byId, gate.id, plan.lineage]);
  const writeOptions = useMemo(() => writePropositions(plan), [plan]);
  const revisionOptions = useMemo(() => (plan.revision_options || []).map((item) => resolve(item, byId)), [byId, plan.revision_options]);
  const gateOptions = useMemo(() => ['mediate', 'bias'].includes(plan.phase.kind) ? actionPropositions(plan, 'proposition.reconcile') : [], [plan]);
  const lanes = useMemo(() => buildLanes({ phaseNumber, domainName, writeRegister: plan.phase.write_register, gate, provenance }), [phaseNumber, domainName, plan.phase.write_register, gate, provenance]);

  return (
    <Box className="operational-workspace" data-domain={domainName} data-phase={phaseNumber}>
      <Box className="operational-workspace__body">
        <Stack renderRoot={(props) => <fieldset {...props} disabled={busy} />} className="operational-board" gap="md" style={{ border: 0 }} aria-busy={busy}>
          <LineagePanel lineage={lineage} committalObservables={provenance.observables} onInspect={setInspection} />
          {lanes.map((lane) => (
            <Fragment key={`${phaseNumber}-${lane.key}`}>
              {lane.role === 'write' && supersessionActions.length > 0 && <SupersessionControl actions={supersessionActions} onAction={onAction} />}
              <OperationalRow
                plan={plan} lane={lane} gate={gate} lineage={lineage} committalObservables={provenance.observables}
                propositions={lane.register ? registerItems[lane.register] || [] : []}
                gateOptions={gateOptions} writeOptions={writeOptions} revisionOptions={revisionOptions}
                attestationOptions={actionPropositions(plan, 'attestation.create')}
                traversalOptions={actionPropositions(plan, 'proposition.traverse')}
                instrumentOptions={actionPropositions(plan, 'instrument.execute')}
                actions={propositionActions} onInspect={setInspection} onAction={onAction}
              />
            </Fragment>
          ))}
        </Stack>
        <PropositionInspector selection={inspection} committalObservables={provenance.observables} onClose={() => setInspection(null)}
          plan={plan} onSubmit={onSubmit} loading={busy} error={error} />
      </Box>
    </Box>
  );
}

interface OperationalRowProps {
  plan: ParticipantPlan;
  lane: Lane;
  gate: Proposition;
  lineage: Proposition[];
  committalObservables?: Observables;
  propositions: Proposition[];
  gateOptions: Proposition[];
  writeOptions: Proposition[];
  revisionOptions: Proposition[];
  attestationOptions: Proposition[];
  traversalOptions: Proposition[];
  instrumentOptions: Proposition[];
  actions: ActionName[];
  onInspect: InspectHandler;
  onAction: ActionHandler;
}

function OperationalRow({ plan, lane, gate, lineage, committalObservables, propositions, gateOptions, writeOptions, revisionOptions, attestationOptions, traversalOptions, instrumentOptions, actions, onInspect, onAction }: OperationalRowProps) {
  const role = roleMeta[lane.role];
  const meta = lane.meta || metaForRegister(lane.register);
  const RegisterIcon = meta.icon;
  const RoleIcon = role.icon;
  const laneGate = lane.featured || gate;
  return (
    <Box className="operational-row" data-role={lane.role} data-lane={lane.key}>
      <Group className="operational-row__role" gap="sm" wrap="nowrap">
        <ThemeIcon color="gray" variant="transparent" size="md"><RoleIcon size={20} /></ThemeIcon>
        <Box><Text fw={700} size="sm">{role.label}</Text><Text size="10px" c="dimmed">{role.description}</Text></Box>
      </Group>
      <Box className="register-row" data-virtual={lane.virtual || undefined} style={{
        '--register-color': `light-dark(var(--mantine-color-${meta.color}-7), var(--mantine-color-${meta.color}-4))`,
        '--register-bg-light': `var(--mantine-color-${meta.color}-0)`,
        '--register-bg-dark': `var(--mantine-color-${meta.color}-light)`,
      }}>
        <Group className="register-row__header" gap="xs" wrap="nowrap">
          <ThemeIcon color={meta.color} variant="light" size="sm"><RegisterIcon size={15} /></ThemeIcon>
          <Text className="register-row__title" size="xs" fw={750}>{meta.label}</Text>
          <Text size="xs" c="dimmed" visibleFrom="sm">{meta.description}</Text>
        </Group>
        {lane.role === 'read' && <ReadContent register={lane.register} gate={gate} featured={lane.featured} propositions={propositions} lineage={lineage} committalObservables={committalObservables} onInspect={onInspect} />}
        {lane.role === 'gate' && <GateContent gate={laneGate} candidates={gateOptions} committalObservables={committalObservables} onInspect={onInspect} onAction={onAction} />}
        {lane.role === 'write' && <WriteContent plan={plan} writeOptions={writeOptions} revisionOptions={revisionOptions} attestationOptions={attestationOptions} traversalOptions={traversalOptions} instrumentOptions={instrumentOptions} actions={actions} committalObservables={committalObservables} onInspect={onInspect} onAction={onAction} />}
      </Box>
    </Box>
  );
}

function ReadContent({ register, gate, featured, propositions, lineage, committalObservables, onInspect }: {
  register?: RegisterName;
  gate: Proposition;
  featured?: Proposition;
  propositions: Proposition[];
  lineage: Proposition[];
  committalObservables?: Observables;
  onInspect: InspectHandler;
}) {
  const currentRead = featured || propositions.find((item) => item.id !== gate.id) || lineage.find((item) => item.register === register) || lineage[0];
  return currentRead
    ? <PropositionCard proposition={currentRead} committalObservables={committalObservables} compact flow="read" onClick={() => onInspect({ kind: 'read', proposition: currentRead })} />
    : <EmptyState text="No readable proposition is exposed in this register." />;
}

function GateContent({ gate, candidates, committalObservables, onInspect, onAction }: {
  gate: Proposition;
  candidates: Proposition[];
  onAction: ActionHandler;
  committalObservables?: Observables;
  onInspect: InspectHandler;
}) {
  return <Stack gap="sm">
    <PropositionCard proposition={gate} committalObservables={committalObservables} selected compact flow="gate" onClick={() => onInspect({ kind: 'gate', proposition: gate })} />
    {candidates.length > 0 && <SimpleGrid cols={{ base: 1, md: 2, xl: 3 }} spacing="sm">
      {candidates.map((candidate) => <Box key={candidate.id} className="write-option-card" data-reconcile-candidate={candidate.id}>
        <PropositionCard proposition={candidate} committalObservables={committalObservables} compact flow="gate" onClick={() => onInspect({ kind: 'gate counterpart', proposition: candidate })} />
        <Group className="write-option-card__actions" gap={4}>
          <Button size="compact-xs" variant="subtle" color="gray" leftSection={<IconRoute size={13} />} onClick={() => onAction('proposition.reconcile', { other_proposition_id: candidate.id })}>Reconcile</Button>
        </Group>
      </Box>)}
    </SimpleGrid>}
  </Stack>;
}

function WriteContent({ plan, writeOptions, revisionOptions, attestationOptions, traversalOptions, instrumentOptions, actions, committalObservables, onInspect, onAction }: {
  plan: ParticipantPlan;
  writeOptions: Proposition[];
  revisionOptions: Proposition[];
  attestationOptions: Proposition[];
  traversalOptions: Proposition[];
  instrumentOptions: Proposition[];
  actions: ActionName[];
  committalObservables?: Observables;
  onInspect: InspectHandler;
  onAction: ActionHandler;
}) {
  const attestationIds = new Set(attestationOptions.map((item) => item.id));
  const revisionIds = new Set(revisionOptions.map((item) => item.id));
  const existing = writeOptions;
  return (
    <Stack className="write-row" gap="md">
      {existing.length > 0 && (
        <Box>
          <Text className="lane-eyebrow" mb="xs">REGISTER PROPOSITIONS</Text>
          <SimpleGrid className="write-row__cards" cols={{ base: 1, md: 2, xl: 3 }} spacing="sm">
            {existing.map((proposition) => <WriteOptionCard
              key={proposition.id} proposition={proposition} directions={attestationDirections(plan, proposition.id)}
              canAttest={actions.includes('attestation.create') && attestationIds.has(proposition.id)}
              canRevise={actions.includes('proposition.revise') && revisionIds.has(proposition.id)}
              canInstrument={actions.includes('instrument.execute') && instrumentOptions.some((item) => item.id === proposition.id)}
              canTraverse={actions.includes('proposition.traverse') && traversalOptions.some((item) => item.id === proposition.id)}
              committalObservables={committalObservables} onInspect={onInspect} onAction={onAction}
            />)}
          </SimpleGrid>
        </Box>
      )}
      {!existing.length && <EmptyState text="No Proposition is visible in this register." />}
    </Stack>
  );
}

function WriteOptionCard({ proposition, directions, canAttest, canRevise, canInstrument, canTraverse, committalObservables, onInspect, onAction }: {
  proposition: Proposition;
  directions: Array<'refute' | 'support'>;
  canAttest: boolean;
  canRevise: boolean;
  canInstrument: boolean;
  canTraverse: boolean;
  committalObservables?: Observables;
  onInspect: InspectHandler;
  onAction: ActionHandler;
}) {
  const hasActions = canAttest || canRevise || canInstrument || canTraverse;
  function directionalAction(direction: 'refute' | 'support') {
    const attest = canAttest && directions.includes(direction);
    const traverse = canTraverse && !directions.includes(direction);
    if (!attest && !traverse) return null;
    return <Button size="compact-xs" variant="subtle" color={direction === 'support' ? 'violet' : 'teal'} leftSection={<IconWhirl size={14} className={`attestation-direction-icon attestation-direction-icon--${direction}`} />} onClick={() => traverse
      ? onAction('proposition.traverse', { proposition_id: proposition.id })
      : onAction('attestation.create', { proposition_id: proposition.id, direction })}>{traverse ? 'Traverse' : direction === 'support' ? 'Support' : 'Refute'}</Button>;
  }
  return (
    <Box className="write-option-card" data-attested={proposition.attestation?.direction}>
      <PropositionCard proposition={proposition} committalObservables={committalObservables} compact flow="write" onClick={() => onInspect({ kind: 'write option', proposition })} />
      {(hasActions || proposition.attestation) && (
        <Box className="write-option-card__actions write-option-card__actions--directional">
          <Box className="write-option-card__side" data-action-region="left">{directionalAction('refute')}</Box>
          <Group className="write-option-card__middle" data-action-region="middle" gap={4} justify="center" wrap="wrap">
            {canRevise && <Button size="compact-xs" variant="subtle" color="gray" leftSection={<IconPencil size={13} />} onClick={() => onAction('proposition.revise', { proposition_id: proposition.id })}>Revise</Button>}
            {canInstrument && <Button size="compact-xs" variant="subtle" color="gray" leftSection={<IconTool size={13} />} onClick={() => onAction('instrument.execute', { proposition_id: proposition.id })}>Instrument</Button>}
          </Group>
          <Box className="write-option-card__side" data-action-region="right">{directionalAction('support')}</Box>
        </Box>
      )}
    </Box>
  );
}

function SupersessionControl({ actions, onAction }: {
  actions: SupersessionAction[];
  onAction: ActionHandler;
}) {
  return (
    <Box className="operational-row operational-row--supersession" aria-label="Create a Supersession">
      <Box />
      <Group className="supersession-control" justify="center">
        <Menu position="bottom" withinPortal>
          <Menu.Target><Button size="compact-sm" variant="light" color="indigo" leftSection={<IconGitBranch size={14} />}>Supersession</Button></Menu.Target>
          <Menu.Dropdown>
            <Menu.Label>Create from the Gate</Menu.Label>
            {actions.map((action) => {
              const meta = actionMeta[action];
              const ActionIcon = meta.icon;
              return <Menu.Item key={action} leftSection={<ActionIcon size={15} />} onClick={() => onAction(action)}><Text size="sm" fw={650}>{meta.label}</Text><Text size="xs" c="dimmed">{meta.description}</Text></Menu.Item>;
            })}
          </Menu.Dropdown>
        </Menu>
      </Group>
    </Box>
  );
}

function EmptyState({ text }: { text: string }) { return <Box className="lane-empty"><Text size="xs" c="dimmed" ta="center">{text}</Text></Box>; }

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
function isSupersessionAction(action: ActionName): action is SupersessionAction { return action in actionMeta; }
