import { Box, Button, Group, SimpleGrid, Stack, Text, ThemeIcon } from '@mantine/core';
import {
  IconBolt,
  IconEye,
  IconGitBranch,
  IconPencil,
  IconRefresh,
  IconRoute,
  IconSeedling,
  IconTool,
  IconWhirl,
} from '@tabler/icons-react';
import { useMemo, useState } from 'react';
import { domainForRegister, metaForRegister } from '../registers';
import { LineagePanel } from './LineagePanel';
import { PropositionCard } from './PropositionCard';
import { PropositionInspector } from './PropositionInspector';

const roleMeta = {
  read: { label: 'Read', description: 'Where we came from', icon: IconEye },
  gate: { label: 'Gate', description: 'What this move supersedes', icon: IconRoute },
  write: { label: 'Write', description: 'Where we can go', icon: IconPencil },
};

const provenanceMeta = {
  label: 'Provenance',
  description: 'The inherited proposition from which this cycle begins.',
  color: 'indigo',
  icon: IconSeedling,
};

const actionMeta = {
  'proposition.variant': { label: 'Create a variant', description: 'Open a new expressive branch from the current Gate.', icon: IconGitBranch },
  'proposition.reclassify': { label: 'Reclassify the Gate', description: 'Carry the Gate into this classification register.', icon: IconRefresh, mirrorsGate: true },
  'proposition.reclassify_with_instrument': { label: 'Reclassify the Gate', description: 'Ground the Gate while carrying it into this register.', icon: IconRefresh, mirrorsGate: true },
  'proposition.commit': { label: 'Commit the Gate', description: 'Write the expressive Gate into the committal structure.', icon: IconBolt, mirrorsGate: true },
};

const cardActions = new Set([
  'attestation.create',
  'instrument.execute',
  'proposition.reconcile',
  'proposition.revise',
  'proposition.select',
]);

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
  const phaseNumber = plan.phase.number || phaseNumberFor(plan.phase.kind);
  const actions = availableActions.filter((action) => actionMeta[action] || cardActions.has(action));
  const [inspection, setInspection] = useState(null);

  const registerItems = useMemo(() => Object.fromEntries(
    Object.entries(plan.workspace?.registers || {}).map(([register, items]) => [register, items.map((item) => resolve(item, byId))]),
  ), [byId, plan.workspace?.registers]);
  const lineage = useMemo(() => (plan.lineage || [])
    .map((item) => resolve(item, byId))
    .filter((item) => item.id !== gate.id), [byId, gate.id, plan.lineage]);
  const writeOptions = useMemo(() => (plan.write_options || []).map((item) => resolve(item, byId)), [byId, plan.write_options]);
  const revisionOptions = useMemo(() => (plan.revision_options || []).map((item) => resolve(item, byId)), [byId, plan.revision_options]);
  const gateOptions = useMemo(() => (plan.gate_options || []).map((item) => resolve(item, byId)).filter((item) => item.id !== gate.id), [byId, gate.id, plan.gate_options]);

  const lanes = useMemo(() => buildLanes({
    phaseNumber,
    domainName,
    writeRegister: plan.phase.write_register,
    gate,
    provenance,
  }), [phaseNumber, domainName, plan.phase.write_register, gate, provenance]);

  return (
    <Box className="operational-workspace" data-domain={domainName} data-phase={phaseNumber}>
      <LineagePanel lineage={lineage} onInspect={setInspection} />
      <Box className="operational-workspace__body">
        <Stack className="operational-board" gap="md">
          {lanes.map((lane) => (
            <OperationalRow
              key={`${phaseNumber}-${lane.key}`}
              lane={lane}
              gate={gate}
              lineage={lineage}
              propositions={lane.register ? registerItems[lane.register] || [] : []}
              gateOptions={gateOptions}
              writeOptions={writeOptions}
              revisionOptions={revisionOptions}
              actions={actions}
              onInspect={setInspection}
              onAction={onAction}
            />
          ))}
        </Stack>
        <PropositionInspector selection={inspection} onClose={() => setInspection(null)} />
      </Box>
    </Box>
  );
}

function OperationalRow({ lane, gate, lineage, propositions, gateOptions, writeOptions, revisionOptions, actions, onInspect, onAction }) {
  const role = roleMeta[lane.role];
  const meta = lane.meta || metaForRegister(lane.register);
  const RegisterIcon = meta.icon;
  const RoleIcon = role.icon;
  const laneGate = lane.featured || gate;

  return (
    <Box className="operational-row" data-role={lane.role} data-lane={lane.key}>
      <Group className="operational-row__role" gap="sm" wrap="nowrap">
        <ThemeIcon color="gray" variant="transparent" size="md"><RoleIcon size={20} /></ThemeIcon>
        <Box>
          <Text fw={700} size="sm">{role.label}</Text>
          <Text size="10px" c="dimmed">{role.description}</Text>
        </Box>
      </Group>
      <Box
        className="register-row"
        data-virtual={lane.virtual || undefined}
        style={{
          '--register-color': `light-dark(var(--mantine-color-${meta.color}-7), var(--mantine-color-${meta.color}-4))`,
          '--register-bg-light': `var(--mantine-color-${meta.color}-0)`,
          '--register-bg-dark': `var(--mantine-color-${meta.color}-light)`,
        }}
      >
        <Group className="register-row__header" gap="xs" wrap="nowrap">
          <ThemeIcon color={meta.color} variant="light" size="sm"><RegisterIcon size={15} /></ThemeIcon>
          <Text className="register-row__title" size="xs" fw={750}>{meta.label}</Text>
          <Text size="xs" c="dimmed" visibleFrom="sm">{meta.description}</Text>
        </Group>
        {lane.role === 'read' && (
          <ReadContent
            register={lane.register}
            gate={gate}
            featured={lane.featured}
            propositions={propositions}
            lineage={lineage}
            onInspect={onInspect}
          />
        )}
        {lane.role === 'gate' && <GateContent gate={laneGate} onInspect={onInspect} />}
        {lane.role === 'write' && (
          <WriteContent
            register={lane.register}
            gate={gate}
            writeOptions={writeOptions}
            revisionOptions={revisionOptions}
            gateOptions={gateOptions}
            actions={actions}
            onInspect={onInspect}
            onAction={onAction}
          />
        )}
      </Box>
    </Box>
  );
}

function ReadContent({ register, gate, featured, propositions, lineage, onInspect }) {
  const currentRead = featured
    || propositions.find((item) => item.id !== gate.id)
    || lineage.find((item) => item.register === register)
    || lineage[0];

  return currentRead
    ? <PropositionCard proposition={currentRead} compact onClick={() => onInspect({ kind: 'read', proposition: currentRead })} />
    : <EmptyState text="No readable proposition is exposed in this register." />;
}

function GateContent({ gate, onInspect }) {
  return <PropositionCard proposition={gate} selected compact onClick={() => onInspect({ kind: 'gate', proposition: gate })} />;
}

function WriteContent({ register, gate, writeOptions, revisionOptions, gateOptions, actions, onInspect, onAction }) {
  const revisionIds = new Set(revisionOptions.map((item) => item.id));
  const reconcileIds = new Set(gateOptions.map((item) => item.id));
  const existing = uniqueById([...writeOptions, ...gateOptions]);
  const moveActions = actions.filter((action) => actionMeta[action]);

  return (
    <Stack className="write-row" gap="md">
      {existing.length > 0 && (
        <Box>
          <Text className="lane-eyebrow" mb="xs">AVAILABLE PROPOSITIONS</Text>
          <SimpleGrid className="write-row__cards" cols={{ base: 1, md: 2, xl: 3 }} spacing="sm">
            {existing.map((proposition) => (
              <WriteOptionCard
                key={proposition.id}
                proposition={proposition}
                canAttest={actions.includes('attestation.create') && writeOptions.some((item) => item.id === proposition.id)}
                canRevise={actions.includes('proposition.revise') && revisionIds.has(proposition.id)}
                canReconcile={actions.includes('proposition.reconcile') && reconcileIds.has(proposition.id)}
                canInstrument={actions.includes('instrument.execute') && writeOptions.some((item) => item.id === proposition.id)}
                canSelect={actions.includes('proposition.select') && writeOptions.some((item) => item.id === proposition.id)}
                onInspect={onInspect}
                onAction={onAction}
              />
            ))}
          </SimpleGrid>
        </Box>
      )}
      {moveActions.length > 0 && (
        <Box>
          <Text className="lane-eyebrow" mb="xs">CREATE FROM THE GATE</Text>
          <SimpleGrid className="write-row__moves" cols={{ base: 1, sm: 2, lg: 3 }} spacing="sm">
            {moveActions.map((action) => {
              const meta = actionMeta[action];
              const actionType = action.replace('proposition.', '').replace('_with_instrument', '');
              const preview = meta.mirrorsGate
                ? { ...gate, register, tier: action === 'proposition.commit' ? 'committal' : 'expressive' }
                : { register, tier: 'expressive', statement: meta.description, title: meta.label };
              preview.transition = { type: actionType, from: gate.register, to: register, source_id: gate.id };

              return (
                <PropositionCard
                  key={action}
                  proposition={preview}
                  compact
                  wireframe
                  actionLabel={meta.label}
                  actionIcon={meta.icon}
                  onClick={() => onAction(action)}
                />
              );
            })}
          </SimpleGrid>
        </Box>
      )}
      {!existing.length && !moveActions.length && <EmptyState text="No admissible move is currently exposed." />}
    </Stack>
  );
}

function WriteOptionCard({ proposition, canAttest, canRevise, canReconcile, canInstrument, canSelect, onInspect, onAction }) {
  const hasActions = canAttest || canRevise || canReconcile || canInstrument || canSelect;

  return (
    <Box className="write-option-card">
      <PropositionCard proposition={proposition} compact onClick={() => onInspect({ kind: 'write option', proposition })} />
      {hasActions && (
        <Group className="write-option-card__actions" gap={4} wrap="wrap">
          {canAttest && (
            <>
              <Button
                size="compact-xs"
                variant="subtle"
                color="violet"
                leftSection={<IconWhirl size={14} className="attestation-direction-icon attestation-direction-icon--support" />}
                onClick={() => onAction('attestation.create', { proposition_id: proposition.id, direction: 'support' })}
              >
                Support
              </Button>
              <Button
                size="compact-xs"
                variant="subtle"
                color="teal"
                leftSection={<IconWhirl size={14} className="attestation-direction-icon attestation-direction-icon--refute" />}
                onClick={() => onAction('attestation.create', { proposition_id: proposition.id, direction: 'refute' })}
              >
                Refute
              </Button>
            </>
          )}
          {canRevise && <Button size="compact-xs" variant="subtle" color="gray" leftSection={<IconPencil size={13} />} onClick={() => onAction('proposition.revise', { proposition_id: proposition.id })}>Revise</Button>}
          {canReconcile && <Button size="compact-xs" variant="subtle" color="gray" leftSection={<IconRoute size={13} />} onClick={() => onAction('proposition.reconcile', { other_proposition_id: proposition.id })}>Reconcile</Button>}
          {canInstrument && <Button size="compact-xs" variant="subtle" color="gray" leftSection={<IconTool size={13} />} onClick={() => onAction('instrument.execute', { proposition_id: proposition.id })}>Instrument</Button>}
          {canSelect && <Button size="compact-xs" variant="subtle" color="gray" onClick={() => onAction('proposition.select', { proposition_id: proposition.id })}>Select</Button>}
        </Group>
      )}
    </Box>
  );
}

function EmptyState({ text }) {
  return <Box className="lane-empty"><Text size="xs" c="dimmed" ta="center">{text}</Text></Box>;
}

function buildLanes({ phaseNumber, domainName, writeRegister, gate, provenance }) {
  if (phaseNumber === 1) {
    return [
      { key: 'provenance-read', role: 'read', virtual: true, meta: provenanceMeta, featured: provenance },
      { key: 'provenance-gate', role: 'gate', virtual: true, meta: provenanceMeta, featured: gate },
      { key: writeRegister, role: 'write', register: writeRegister },
    ];
  }

  if (phaseNumber === 2) {
    return [
      { key: 'provenance', role: 'read', virtual: true, meta: provenanceMeta, featured: provenance },
      { key: `gate-${gate.register}`, role: 'gate', register: gate.register, featured: gate },
      { key: writeRegister, role: 'write', register: writeRegister },
    ];
  }

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

function phaseNumberFor(kind) {
  return { distinguish: 1, persist: 2, constrain: 3, disperse: 4, mediate: 5, bias: 6, order: 7 }[kind] || 0;
}

function resolve(proposition, byId) { return proposition ? byId.get(proposition.id) || proposition : {}; }
function uniqueById(items) { return [...new Map(items.filter(Boolean).map((item) => [item.id, item])).values()]; }
