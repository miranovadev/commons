import { Box, Button, Group, Menu, SimpleGrid, Stack, Text, ThemeIcon } from '@mantine/core';
import { IconBolt, IconEye, IconGitBranch, IconPencil, IconRefresh, IconRoute, IconSeedling, IconTool, IconWhirl } from '@tabler/icons-react';
import { Fragment, useMemo, useState } from 'react';
import { domainForRegister, metaForRegister } from '../registers';
import { actionPropositions } from '../participationOptions';
import { LineagePanel } from './LineagePanel';
import { PropositionCard } from './PropositionCard';
import { PropositionInspector } from './PropositionInspector';
import type {
  ActionName,
  ContributionInitialValues,
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

export function ParticipantWorkspace({ plan, availableActions, onAction }: {
  plan: ParticipantPlan;
  availableActions: ActionName[];
  onAction: ActionHandler;
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
  const registerItems = useMemo(() => Object.fromEntries(
    Object.entries(plan.workspace?.registers || {}).map(([register, items]) => [register, (items || []).map((item) => resolve(item, byId))]),
  ) as Partial<Record<RegisterName, Proposition[]>>, [byId, plan.workspace?.registers]);
  const lineage = useMemo(() => (plan.lineage || []).map((item) => resolve(item, byId)).filter((item) => item.id !== gate.id), [byId, gate.id, plan.lineage]);
  const writeOptions = useMemo(() => (plan.write_options || []).map((item) => resolve(item, byId)), [byId, plan.write_options]);
  const revisionOptions = useMemo(() => (plan.revision_options || []).map((item) => resolve(item, byId)), [byId, plan.revision_options]);
  const gateOptions = useMemo(() => (plan.gate_options || []).map((item) => resolve(item, byId)).filter((item) => item.id !== gate.id), [byId, gate.id, plan.gate_options]);
  const lanes = useMemo(() => buildLanes({ phaseNumber, domainName, writeRegister: plan.phase.write_register, gate, provenance }), [phaseNumber, domainName, plan.phase.write_register, gate, provenance]);

  return (
    <Box className="operational-workspace" data-domain={domainName} data-phase={phaseNumber}>
      <Box className="operational-workspace__body">
        <Stack className="operational-board" gap="md">
          <LineagePanel lineage={lineage} committalObservables={provenance.observables} onInspect={setInspection} />
          {lanes.map((lane) => (
            <Fragment key={`${phaseNumber}-${lane.key}`}>
              {lane.role === 'write' && supersessionActions.length > 0 && <SupersessionControl actions={supersessionActions} onAction={onAction} />}
              <OperationalRow
                lane={lane} gate={gate} lineage={lineage} committalObservables={provenance.observables}
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
        <PropositionInspector selection={inspection} committalObservables={provenance.observables} onClose={() => setInspection(null)} />
      </Box>
    </Box>
  );
}

interface OperationalRowProps {
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

function OperationalRow({ lane, gate, lineage, committalObservables, propositions, gateOptions, writeOptions, revisionOptions, attestationOptions, traversalOptions, instrumentOptions, actions, onInspect, onAction }: OperationalRowProps) {
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
        {lane.role === 'gate' && <GateContent gate={laneGate} committalObservables={committalObservables} onInspect={onInspect} />}
        {lane.role === 'write' && <WriteContent propositions={propositions} writeOptions={writeOptions} revisionOptions={revisionOptions} gateOptions={gateOptions} attestationOptions={attestationOptions} traversalOptions={traversalOptions} instrumentOptions={instrumentOptions} actions={actions} committalObservables={committalObservables} onInspect={onInspect} onAction={onAction} />}
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

function GateContent({ gate, committalObservables, onInspect }: {
  gate: Proposition;
  committalObservables?: Observables;
  onInspect: InspectHandler;
}) {
  return <PropositionCard proposition={gate} committalObservables={committalObservables} selected compact flow="gate" onClick={() => onInspect({ kind: 'gate', proposition: gate })} />;
}

function WriteContent({ propositions, writeOptions, revisionOptions, gateOptions, attestationOptions, traversalOptions, instrumentOptions, actions, committalObservables, onInspect, onAction }: {
  propositions: Proposition[];
  writeOptions: Proposition[];
  revisionOptions: Proposition[];
  gateOptions: Proposition[];
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
  const reconcileIds = new Set(gateOptions.map((item) => item.id));
  const existing = uniqueById([...propositions, ...writeOptions, ...gateOptions, ...attestationOptions, ...traversalOptions, ...instrumentOptions]);
  return (
    <Stack className="write-row" gap="md">
      {existing.length > 0 && (
        <Box>
          <Text className="lane-eyebrow" mb="xs">REGISTER PROPOSITIONS</Text>
          <SimpleGrid className="write-row__cards" cols={{ base: 1, md: 2, xl: 3 }} spacing="sm">
            {existing.map((proposition) => <WriteOptionCard
              key={proposition.id} proposition={proposition}
              canAttest={actions.includes('attestation.create') && attestationIds.has(proposition.id)}
              canRevise={actions.includes('proposition.revise') && revisionIds.has(proposition.id)}
              canReconcile={actions.includes('proposition.reconcile') && reconcileIds.has(proposition.id)}
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

function WriteOptionCard({ proposition, canAttest, canRevise, canReconcile, canInstrument, canTraverse, committalObservables, onInspect, onAction }: {
  proposition: Proposition;
  canAttest: boolean;
  canRevise: boolean;
  canReconcile: boolean;
  canInstrument: boolean;
  canTraverse: boolean;
  committalObservables?: Observables;
  onInspect: InspectHandler;
  onAction: ActionHandler;
}) {
  const hasActions = canAttest || canRevise || canReconcile || canInstrument || canTraverse;
  return (
    <Box className="write-option-card" data-attested={proposition.attestation?.direction}>
      <PropositionCard proposition={proposition} committalObservables={committalObservables} compact flow="write" onClick={() => onInspect({ kind: 'write option', proposition })} />
      {(hasActions || proposition.attestation) && (
        <Group className="write-option-card__actions" gap={4} wrap="wrap">
          {canAttest && <>
            <Button size="compact-xs" variant={proposition.attestation?.direction === 'support' ? 'light' : 'subtle'} color="violet" leftSection={<IconWhirl size={14} className="attestation-direction-icon attestation-direction-icon--support" />} onClick={() => onAction('attestation.create', { proposition_id: proposition.id, direction: 'support' })}>Support</Button>
            <Button size="compact-xs" variant={proposition.attestation?.direction === 'refute' ? 'light' : 'subtle'} color="teal" leftSection={<IconWhirl size={14} className="attestation-direction-icon attestation-direction-icon--refute" />} onClick={() => onAction('attestation.create', { proposition_id: proposition.id, direction: 'refute' })}>Refute</Button>
          </>}
          {!canAttest && proposition.attestation && <Group gap={5} px={6}><IconWhirl size={14} className={`attestation-direction-icon attestation-direction-icon--${proposition.attestation.direction}`} /><Text size="xs" fw={650}>{proposition.attestation.direction === 'support' ? 'Supported' : 'Refuted'} by you</Text></Group>}
          {canRevise && <Button size="compact-xs" variant="subtle" color="gray" leftSection={<IconPencil size={13} />} onClick={() => onAction('proposition.revise', { proposition_id: proposition.id })}>Revise</Button>}
          {canReconcile && <Button size="compact-xs" variant="subtle" color="gray" leftSection={<IconRoute size={13} />} onClick={() => onAction('proposition.reconcile', { other_proposition_id: proposition.id })}>Reconcile</Button>}
          {canInstrument && <Button size="compact-xs" variant="subtle" color="gray" leftSection={<IconTool size={13} />} onClick={() => onAction('instrument.execute', { proposition_id: proposition.id })}>Instrument</Button>}
          {canTraverse && <Button size="compact-xs" variant="subtle" color="gray" onClick={() => onAction('proposition.traverse', { proposition_id: proposition.id })}>Traverse</Button>}
        </Group>
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
function uniqueById(items: Proposition[]): Proposition[] { return [...new Map(items.map((item) => [item.id, item])).values()]; }
function isSupersessionAction(action: ActionName): action is SupersessionAction { return action in actionMeta; }
