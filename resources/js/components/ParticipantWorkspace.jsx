import { Badge, Box, Button, Card, Group, ScrollArea, Stack, Text, ThemeIcon } from '@mantine/core';
import { IconBolt, IconEye, IconGitBranch, IconPencil, IconPlus, IconRefresh, IconRoute, IconTool, IconWhirl } from '@tabler/icons-react';
import { useMemo, useState } from 'react';
import { domainForRegister, metaForRegister } from '../registers';
import { LineagePanel } from './LineagePanel';
import { MeasurementRails } from './MeasurementRails';
import { PropositionCard } from './PropositionCard';
import { PropositionInspector } from './PropositionInspector';

const roles = [
  { name: 'read', label: 'Read', description: 'Where we came from', icon: IconEye },
  { name: 'gate', label: 'Gate', description: 'Where we are now', icon: IconRoute },
  { name: 'write', label: 'Write', description: 'Where we can go', icon: IconPencil },
];

const actionMeta = {
  'proposition.variant': { label: 'Create a variant', description: 'Open a new expressive branch from the current Gate.', icon: IconGitBranch },
  'proposition.revise': { label: 'Revise a proposition', description: 'Replace one of your active propositions in this register.', icon: IconPencil },
  'proposition.reclassify': { label: 'Reclassify the Gate', description: 'Carry the Gate into this classification register.', icon: IconRefresh, mirrorsGate: true },
  'proposition.reclassify_with_instrument': { label: 'Reclassify the Gate', description: 'Ground the Gate while carrying it into this register.', icon: IconRefresh, mirrorsGate: true },
  'proposition.reconcile': { label: 'Reconcile propositions', description: 'Resolve the Gate with another admissible proposition.', icon: IconRoute },
  'attestation.create': { label: 'Create an attestation', description: 'Support or refute an expressive proposition in this register.', icon: IconWhirl },
  'instrument.execute': { label: 'Use an instrument', description: 'Ground an expressive proposition with a verifiable receipt.', icon: IconTool },
  'proposition.commit': { label: 'Commit the Gate', description: 'Write the expressive Gate into the committal structure.', icon: IconBolt, mirrorsGate: true },
};

export function ParticipantWorkspace({ plan, availableActions, onAction }) {
  const propositions = useMemo(() => [
    plan.gate,
    plan.workspace?.provenance,
    ...(plan.lineage || []),
    ...Object.values(plan.workspace?.registers || {}).flat(),
    ...(plan.write_options || []),
    ...(plan.revision_options || []),
    ...(plan.gate_options || []),
  ].filter(Boolean), [plan]);
  const byId = useMemo(() => new Map(propositions.map((item) => [item.id, item])), [propositions]);
  const gate = resolve(plan.gate, byId);
  const provenance = resolve(plan.workspace?.provenance || plan.lineage?.find((item) => item.tier === 'committal') || gate, byId);
  const domainName = plan.workspace?.domain || domainForRegister(plan.phase.write_register);
  const assignments = useMemo(() => operationalAssignment(domainName, plan.phase.write_register, gate.register), [domainName, gate.register, plan.phase.write_register]);
  const actions = availableActions.filter((action) => actionMeta[action]);
  const canSelect = availableActions.includes('proposition.select');
  const [inspection, setInspection] = useState({ kind: 'gate', proposition: gate });

  const registerItems = useMemo(() => Object.fromEntries(
    Object.entries(plan.workspace?.registers || {}).map(([register, items]) => [register, items.map((item) => resolve(item, byId))]),
  ), [byId, plan.workspace?.registers]);

  const lineage = useMemo(() => (plan.lineage || []).map((item) => resolve(item, byId)), [byId, plan.lineage]);
  const writeOptions = useMemo(() => (plan.write_options || []).map((item) => resolve(item, byId)), [byId, plan.write_options]);
  const gateOptions = useMemo(() => (plan.gate_options || []).map((item) => resolve(item, byId)).filter((item) => item.id !== gate.id), [byId, gate.id, plan.gate_options]);

  return (
    <Box className="operational-workspace" data-domain={domainName}>
      <Box className="workspace-context">
        <HorizonProvenance proposition={provenance} onInspect={setInspection} />
      </Box>
      <Box className="operational-workspace__body">
        <Box className="operational-board">
          {roles.map((role) => (
            <OperationalLane
              key={role.name}
              role={role}
              register={assignments[role.name]}
              gate={gate}
              lineage={lineage}
              propositions={registerItems[assignments[role.name]] || []}
              gateOptions={gateOptions}
              writeOptions={writeOptions}
              actions={actions}
              canSelect={canSelect}
              onInspect={setInspection}
              onAction={onAction}
            />
          ))}
        </Box>
        <PropositionInspector selection={inspection} onClose={() => setInspection(null)} />
      </Box>
    </Box>
  );
}

function HorizonProvenance({ proposition, onInspect }) {
  const register = metaForRegister(proposition.register);
  return (
    <Card
      component="button"
      type="button"
      withBorder
      padding="sm"
      className="horizon-provenance__card"
      onClick={() => onInspect({ kind: 'provenance', proposition })}
    >
      <Box className="horizon-provenance__identity" miw={0}>
        <Group gap={6} mb={4}>
          <Badge size="xs" color="indigo" variant="filled">Committal</Badge>
          <Badge size="xs" color={register.color} variant="light">{register.label}</Badge>
          <Text size="10px" c="dimmed">#{shortId(proposition.id)}</Text>
        </Group>
        <Text size="xs" fw={800} c="indigo">Horizon Provenance</Text>
        <Text size="sm" fw={650} lineClamp={2}>{proposition.title || proposition.statement}</Text>
      </Box>
      <Box className="horizon-provenance__measurements">
        <MeasurementRails observables={proposition.observables} compact />
      </Box>
    </Card>
  );
}

function OperationalLane({ role, register, gate, lineage, propositions, gateOptions, writeOptions, actions, canSelect, onInspect, onAction }) {
  const meta = metaForRegister(register);
  const RegisterIcon = meta.icon;
  const RoleIcon = role.icon;

  return (
    <Box className="operational-lane" data-role={role.name}>
      <Group className="operational-lane__role" justify="center" gap={7} wrap="nowrap">
        <RoleIcon size={17} />
        <Box>
          <Text fw={650} size="sm">{role.label}</Text>
          <Text size="10px" c="dimmed">{role.description}</Text>
        </Box>
      </Group>
      <Box
        className="register-shell"
        key={register}
        style={{
          '--register-color': `light-dark(var(--mantine-color-${meta.color}-7), var(--mantine-color-${meta.color}-4))`,
          '--register-bg-light': `var(--mantine-color-${meta.color}-0)`,
          '--register-bg-dark': `var(--mantine-color-${meta.color}-light)`,
        }}
      >
        <Group className="register-shell__header" gap="sm" wrap="nowrap">
          <span className="register-shell__accent" />
          <ThemeIcon color={meta.color} variant="transparent" size="lg"><RegisterIcon size={24} /></ThemeIcon>
          <Box>
            <Text className="register-shell__title" fw={750}>{meta.label}</Text>
            <Text size="xs" c="dimmed">{meta.description}</Text>
          </Box>
        </Group>
        {role.name === 'read' && <ReadContent register={register} gate={gate} propositions={propositions} lineage={lineage} onInspect={onInspect} />}
        {role.name === 'gate' && <GateContent gate={gate} gateOptions={gateOptions} propositions={propositions} onInspect={onInspect} canSelect={canSelect} />}
        {role.name === 'write' && <WriteContent register={register} gate={gate} writeOptions={writeOptions} actions={actions} canSelect={canSelect} onInspect={onInspect} onAction={onAction} />}
      </Box>
    </Box>
  );
}

function ReadContent({ register, gate, propositions, lineage, onInspect }) {
  const currentRead = propositions.find((item) => item.id !== gate.id) || lineage.find((item) => item.register === register) || lineage[0];
  return (
    <Stack className="lane-content" gap="sm">
      <Text className="lane-eyebrow">CURRENT READ</Text>
      {currentRead ? <PropositionCard proposition={currentRead} compact onClick={() => onInspect({ kind: 'read', proposition: currentRead })} /> : <EmptyState text="No readable proposition is exposed in this register." />}
      <LineagePanel lineage={lineage} onInspect={onInspect} />
    </Stack>
  );
}

function GateContent({ gate, gateOptions, propositions, onInspect, canSelect }) {
  const candidates = uniqueById([...gateOptions, ...propositions.filter((item) => item.id !== gate.id)]);
  return (
    <ScrollArea className="lane-scroll" type="auto" offsetScrollbars>
      <Stack className="lane-content" gap="sm">
        <Text className="lane-eyebrow">SELECTED GATE</Text>
        <PropositionCard proposition={gate} selected compact onClick={() => onInspect({ kind: 'gate', proposition: gate })} />
        {candidates.length > 0 && <Text className="lane-eyebrow" mt="xs">OTHER CANDIDATES AT THIS LEVEL</Text>}
        {candidates.map((proposition) => (
          <PropositionCard key={proposition.id} proposition={proposition} compact muted onClick={() => onInspect({ kind: 'gate candidate', proposition })} />
        ))}
        {canSelect && candidates.length > 0 && <Text size="10px" c="dimmed">Inspecting a candidate does not select it. Selection remains an explicit contribution.</Text>}
      </Stack>
    </ScrollArea>
  );
}

function WriteContent({ register, gate, writeOptions, actions, canSelect, onInspect, onAction }) {
  return (
    <Stack className="write-lane" gap={0}>
      <ScrollArea className="lane-scroll" type="auto" offsetScrollbars>
        <Stack className="lane-content" gap="sm">
          <Group justify="space-between"><Text className="lane-eyebrow">AVAILABLE MOVES</Text><Text size="xs" c="dimmed">{actions.length + writeOptions.length}</Text></Group>
          {actions.map((action) => {
            const meta = actionMeta[action];
            const preview = meta.mirrorsGate ? { ...gate, register, tier: action === 'proposition.commit' ? 'committal' : 'expressive' } : { register, tier: 'expressive', statement: meta.description, title: meta.label };
            return <PropositionCard key={action} proposition={preview} compact wireframe actionLabel={meta.label} actionIcon={meta.icon} onClick={() => onInspect({ kind: 'available move', title: meta.label, proposition: preview })} />;
          })}
          {writeOptions.map((proposition) => <PropositionCard key={proposition.id} proposition={proposition} compact onClick={() => onInspect({ kind: 'write option', proposition })} />)}
          {!actions.length && !writeOptions.length && <EmptyState text="No admissible move is currently exposed." />}
        </Stack>
      </ScrollArea>
      <Button className="write-lane__create" leftSection={<IconPlus size={16} />} color={metaForRegister(register).color} disabled={!actions.length} onClick={() => actions[0] && onAction(actions[0])}>Create Proposition</Button>
      {canSelect && writeOptions.length > 0 && <Text size="10px" c="dimmed" px="sm" pb="xs">Existing write options remain inspectable; selection is performed through the contribution flow.</Text>}
    </Stack>
  );
}

function EmptyState({ text }) {
  return <Box className="lane-empty"><Text size="xs" c="dimmed" ta="center">{text}</Text></Box>;
}

function operationalAssignment(domainName, writeRegister, gateRegister) {
  const registers = domainName === 'settlement' ? ['signal', 'stance', 'concord'] : ['activity', 'frontier', 'canon'];
  const writeIndex = registers.indexOf(writeRegister);
  if (writeIndex >= 0) return { read: registers[(writeIndex + 1) % 3], gate: registers[(writeIndex + 2) % 3], write: registers[writeIndex] };
  const gateIndex = registers.indexOf(gateRegister);
  if (gateIndex >= 0) return { read: registers[(gateIndex + 2) % 3], gate: registers[gateIndex], write: registers[(gateIndex + 1) % 3] };
  return { read: registers[0], gate: registers[1], write: registers[2] };
}

function resolve(proposition, byId) { return proposition ? byId.get(proposition.id) || proposition : {}; }
function uniqueById(items) { return [...new Map(items.filter(Boolean).map((item) => [item.id, item])).values()]; }
function shortId(id) { return id == null ? '—' : String(id).slice(-6); }
