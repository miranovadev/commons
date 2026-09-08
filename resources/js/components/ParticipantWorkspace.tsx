import { Box, Button, Group, Menu, SimpleGrid, Stack, Text, ThemeIcon } from '@mantine/core';
import { IconBolt, IconEye, IconGitBranch, IconPencil, IconRefresh, IconRoute, IconSeedling, IconTool, IconWhirl } from '@tabler/icons-react';
import { Fragment, useMemo, useState } from 'react';
import { domainForRegister, metaForRegister } from '../registers';
import { LineagePanel } from './LineagePanel';
import { PropositionCard } from './PropositionCard';
import { PropositionInspector } from './PropositionInspector';

const roleMeta = {
  read: { label: 'Read', description: 'Where we came from', icon: IconEye },
  gate: { label: 'Gate', description: 'What this move supersedes', icon: IconRoute },
  write: { label: 'Write', description: 'Where we can go', icon: IconPencil },
};
const provenanceMeta = { label: 'Provenance', description: 'The inherited proposition from which this cycle begins.', color: 'indigo', icon: IconSeedling };
const actionMeta = {
  'proposition.variant': { label: 'Variant', description: 'Open a new expressive branch from the current Gate.', icon: IconGitBranch },
  'proposition.reclassify': { label: 'Reclassify', description: 'Carry the Gate into this classification register.', icon: IconRefresh },
  'proposition.reclassify_with_instrument': { label: 'Reclassify', description: 'Ground the Gate while carrying it into this register.', icon: IconRefresh },
  'proposition.commit': { label: 'Commit', description: 'Write the expressive Gate into the committal structure.', icon: IconBolt },
};
const cardActions = new Set(['attestation.create', 'instrument.execute', 'proposition.reconcile', 'proposition.revise', 'proposition.select']);

export function ParticipantWorkspace({ plan, availableActions, onAction }: any) {
  const propositions = useMemo<any[]>(() => [
    plan.gate, plan.workspace?.provenance, ...(plan.lineage || []),
    ...(Object.values(plan.workspace?.registers || {}) as any[][]).flat(), ...(plan.write_options || []),
    ...(plan.revision_options || []), ...(plan.gate_options || []),
  ].filter(Boolean), [plan]);
  const byId = useMemo(() => new Map(propositions.map((item) => [item.id, item])), [propositions]);
  const gate = resolve(plan.gate, byId);
  const provenance = resolve(plan.workspace?.provenance || plan.lineage?.find((item) => item.tier === 'committal') || gate, byId);
  const domainName = plan.workspace?.domain || domainForRegister(plan.phase.write_register);
  const phaseNumber = plan.phase.number || phaseNumberFor(plan.phase.kind);
  const actions = availableActions.filter((action) => actionMeta[action] || cardActions.has(action));
  const supersessionActions = actions.filter((action) => actionMeta[action]);
  const propositionActions = actions.filter((action) => !actionMeta[action]);
  const [inspection, setInspection] = useState(null);
  const registerItems = useMemo(() => Object.fromEntries(
    Object.entries(plan.workspace?.registers || {}).map(([register, items]) => [register, (items as any[]).map((item) => resolve(item, byId))]),
  ), [byId, plan.workspace?.registers]);
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

function OperationalRow({ lane, gate, lineage, committalObservables, propositions, gateOptions, writeOptions, revisionOptions, actions, onInspect, onAction }: any) {
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
        {lane.role === 'write' && <WriteContent propositions={propositions} writeOptions={writeOptions} revisionOptions={revisionOptions} gateOptions={gateOptions} actions={actions} committalObservables={committalObservables} onInspect={onInspect} onAction={onAction} />}
      </Box>
    </Box>
  );
}

function ReadContent({ register, gate, featured, propositions, lineage, committalObservables, onInspect }: any) {
  const currentRead = featured || propositions.find((item) => item.id !== gate.id) || lineage.find((item) => item.register === register) || lineage[0];
  return currentRead
    ? <PropositionCard proposition={currentRead} committalObservables={committalObservables} compact flow="read" onClick={() => onInspect({ kind: 'read', proposition: currentRead })} />
    : <EmptyState text="No readable proposition is exposed in this register." />;
}

function GateContent({ gate, committalObservables, onInspect }: any) {
  return <PropositionCard proposition={gate} committalObservables={committalObservables} selected compact flow="gate" onClick={() => onInspect({ kind: 'gate', proposition: gate })} />;
}

function WriteContent({ propositions, writeOptions, revisionOptions, gateOptions, actions, committalObservables, onInspect, onAction }: any) {
  const visibleIds = new Set(propositions.filter((item) => item.tier === 'expressive').map((item) => item.id));
  const revisionIds = new Set(revisionOptions.map((item) => item.id));
  const reconcileIds = new Set(gateOptions.map((item) => item.id));
  const existing = uniqueById([...propositions, ...writeOptions, ...gateOptions]);
  return (
    <Stack className="write-row" gap="md">
      {existing.length > 0 && (
        <Box>
          <Text className="lane-eyebrow" mb="xs">REGISTER PROPOSITIONS</Text>
          <SimpleGrid className="write-row__cards" cols={{ base: 1, md: 2, xl: 3 }} spacing="sm">
            {existing.map((proposition) => <WriteOptionCard
              key={proposition.id} proposition={proposition}
              canAttest={actions.includes('attestation.create') && visibleIds.has(proposition.id)}
              canRevise={actions.includes('proposition.revise') && revisionIds.has(proposition.id)}
              canReconcile={actions.includes('proposition.reconcile') && reconcileIds.has(proposition.id)}
              canInstrument={actions.includes('instrument.execute') && writeOptions.some((item) => item.id === proposition.id)}
              canSelect={actions.includes('proposition.select') && writeOptions.some((item) => item.id === proposition.id)}
              committalObservables={committalObservables} onInspect={onInspect} onAction={onAction}
            />)}
          </SimpleGrid>
        </Box>
      )}
      {!existing.length && <EmptyState text="No Proposition is visible in this register." />}
    </Stack>
  );
}

function WriteOptionCard({ proposition, canAttest, canRevise, canReconcile, canInstrument, canSelect, committalObservables, onInspect, onAction }: any) {
  const hasActions = canAttest || canRevise || canReconcile || canInstrument || canSelect;
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
          {canSelect && <Button size="compact-xs" variant="subtle" color="gray" onClick={() => onAction('proposition.select', { proposition_id: proposition.id })}>Select</Button>}
        </Group>
      )}
    </Box>
  );
}

function SupersessionControl({ actions, onAction }: any) {
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

function buildLanes({ phaseNumber, domainName, writeRegister, gate, provenance }: any) {
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

function operationalAssignment(domainName, writeRegister, gateRegister) {
  const registers = domainName === 'settlement' ? ['signal', 'stance', 'concord'] : ['activity', 'frontier', 'canon'];
  const writeIndex = registers.indexOf(writeRegister);
  if (writeIndex >= 0) return { read: registers[(writeIndex + 1) % 3], gate: registers[(writeIndex + 2) % 3], write: registers[writeIndex] };
  const gateIndex = registers.indexOf(gateRegister);
  if (gateIndex >= 0) return { read: registers[(gateIndex + 2) % 3], gate: registers[gateIndex], write: registers[(gateIndex + 1) % 3] };
  return { read: registers[0], gate: registers[1], write: registers[2] };
}

function phaseNumberFor(kind) { return { distinguish: 1, persist: 2, constrain: 3, disperse: 4, mediate: 5, bias: 6, order: 7 }[kind] || 0; }
function resolve(proposition, byId) { return proposition ? byId.get(proposition.id) || proposition : {}; }
function uniqueById(items: any[]): any[] { return [...new Map(items.filter(Boolean).map((item) => [item.id, item])).values()]; }
