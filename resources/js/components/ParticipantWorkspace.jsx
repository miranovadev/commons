import {
  Badge,
  Box,
  Group,
  ScrollArea,
  Stack,
  Text,
  ThemeIcon,
  Tooltip,
  UnstyledButton,
} from '@mantine/core';
import {
  IconBolt,
  IconChevronLeft,
  IconChevronRight,
  IconGitBranch,
  IconPencil,
  IconRefresh,
  IconRoute,
  IconTool,
  IconWhirl,
} from '@tabler/icons-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { domainForRegister, metaForDomain, metaForRegister } from '../registers';
import { PropositionCard } from './PropositionCard';

const actionMeta = {
  'proposition.variant': {
    label: 'Create a variant',
    description: 'Open a new expressive branch from the current Gate.',
    icon: IconGitBranch,
  },
  'proposition.revise': {
    label: 'Revise a proposition',
    description: 'Replace one of your active propositions in this register.',
    icon: IconPencil,
  },
  'proposition.reclassify': {
    label: 'Reclassify the Gate',
    description: 'Carry the Gate into this classification register.',
    icon: IconRefresh,
    mirrorsGate: true,
  },
  'proposition.reclassify_with_instrument': {
    label: 'Reclassify the Gate',
    description: 'Ground the Gate while carrying it into this register.',
    icon: IconRefresh,
    mirrorsGate: true,
  },
  'proposition.reconcile': {
    label: 'Reconcile propositions',
    description: 'Resolve the Gate with another admissible proposition.',
    icon: IconRoute,
  },
  'attestation.create': {
    label: 'Create an attestation',
    description: 'Support or refute an expressive proposition in this register.',
    icon: IconWhirl,
  },
  'instrument.execute': {
    label: 'Use an instrument',
    description: 'Ground an expressive proposition with a verifiable receipt.',
    icon: IconTool,
  },
  'proposition.commit': {
    label: 'Commit the Gate',
    description: 'Write the expressive Gate into the committal structure.',
    icon: IconBolt,
    mirrorsGate: true,
  },
};

export function ParticipantWorkspace({
  plan,
  availableActions,
  onAction,
}) {
  const propositions = useMemo(() => [
    plan.gate,
    plan.workspace?.provenance,
    ...Object.values(plan.workspace?.registers || {}).flat(),
    ...(plan.write_options || []),
    ...(plan.revision_options || []),
    ...(plan.gate_options || []),
  ].filter(Boolean), [plan]);
  const byId = useMemo(
    () => new Map(propositions.map((proposition) => [proposition.id, proposition])),
    [propositions],
  );
  const domainName = plan.workspace?.domain
    || domainForRegister(plan.phase.write_register);
  const domain = metaForDomain(domainName);
  const DomainIcon = domain.icon;
  const ProjectionIcon = domain.projection.icon;
  const registers = domain.registers;
  const initialRegister = registers.includes(plan.phase.write_register)
    ? plan.phase.write_register
    : registers[0];
  const [activeRegister, setActiveRegister] = useState(initialRegister);
  const boardViewport = useRef(null);
  const scrollTimer = useRef(null);
  const provenance = resolveProposition(
    plan.workspace?.provenance
      || plan.lineage?.find((proposition) => proposition.tier === 'committal')
      || plan.gate,
    byId,
  );

  const registerItems = useMemo(
    () => Object.fromEntries(registers.map((register) => [
      register,
      (plan.workspace?.registers?.[register] || [])
        .map((proposition) => resolveProposition(proposition, byId)),
    ])),
    [byId, plan.workspace?.registers, registers],
  );
  const writeOptions = useMemo(
    () => (plan.write_options || [])
      .map((proposition) => resolveProposition(proposition, byId)),
    [byId, plan.write_options],
  );
  const actions = availableActions.filter((action) => actionMeta[action]);
  const canSelect = availableActions.includes('proposition.select');

  useEffect(() => {
    setActiveRegister(initialRegister);
    requestAnimationFrame(() => scrollToRegister(initialRegister, false));
  }, [domainName, initialRegister]);

  useEffect(() => () => clearTimeout(scrollTimer.current), []);

  function scrollToRegister(register, smooth = true) {
    const viewport = boardViewport.current;
    const column = viewport?.querySelector(`[data-register="${register}"]`);
    if (!viewport || !column) return;

    setActiveRegister(register);
    if (viewport.scrollWidth <= viewport.clientWidth) return;

    viewport.scrollTo({
      left: column.offsetLeft,
      behavior: smooth ? 'smooth' : 'auto',
    });
  }

  function trackRegister() {
    clearTimeout(scrollTimer.current);
    scrollTimer.current = setTimeout(() => {
      const viewport = boardViewport.current;
      if (!viewport?.clientWidth || viewport.scrollWidth <= viewport.clientWidth) return;

      const index = Math.max(
        0,
        Math.min(registers.length - 1, Math.round(viewport.scrollLeft / viewport.clientWidth)),
      );
      setActiveRegister(registers[index]);
    }, 80);
  }

  return (
    <Box className="cycle-workspace" data-domain={domainName}>
      <Group className="cycle-workspace__domain" justify="space-between" wrap="nowrap">
        <Group gap="sm" wrap="nowrap">
          <ThemeIcon color="indigo" variant="light" size="xl" radius="xl">
            <DomainIcon size={22} />
          </ThemeIcon>
          <Box>
            <Group gap="xs">
              <Text fw={750} size="lg">{domain.label}</Text>
              <Badge variant="light" color="indigo">Phase {plan.phase.kind}</Badge>
              <Badge
                variant="light"
                color={domainName === 'emergence' ? 'orange' : 'cyan'}
                leftSection={<ProjectionIcon size={12} />}
              >
                {domain.projection.label}
              </Badge>
            </Group>
            <Text size="sm" c="dimmed">{domain.description}</Text>
          </Box>
        </Group>
        <Stack gap={2} align="flex-end" visibleFrom="sm">
          <Text size="xs" fw={700} tt="uppercase" c="dimmed">Current operation</Text>
          <Text size="sm" fw={600}>{plan.phase.description}</Text>
        </Stack>
      </Group>

      <Box className="cycle-workspace__provenance">
        <Text className="cycle-workspace__eyebrow" size="xs">HORIZON PROVENANCE</Text>
        <Box className="cycle-workspace__provenance-card">
          <PropositionCard proposition={provenance} compact />
        </Box>
      </Box>

      <RegisterConnectors registers={registers} />

      <Group className="register-tabs" grow gap={6} hiddenFrom="sm">
        {registers.map((register, index) => {
          const meta = metaForRegister(register);
          const Icon = meta.icon;

          return (
            <Tooltip key={register} label={meta.label}>
              <UnstyledButton
                className="register-tab"
                data-active={activeRegister === register || undefined}
                style={{ '--register-color': `var(--mantine-color-${meta.color}-6)` }}
                aria-label={`Show ${meta.label} register`}
                onClick={() => scrollToRegister(register)}
              >
                {index < registers.indexOf(activeRegister)
                  ? <IconChevronLeft size={13} />
                  : index > registers.indexOf(activeRegister)
                    ? <IconChevronRight size={13} />
                    : null}
                <Icon size={17} />
                <Text size="xs" fw={700}>{meta.label}</Text>
              </UnstyledButton>
            </Tooltip>
          );
        })}
      </Group>

      <Box
        ref={boardViewport}
        className="register-board"
        onScroll={trackRegister}
      >
        <Box className="register-board__columns">
          {registers.map((register) => (
            <RegisterColumn
              key={register}
              register={register}
              propositions={registerItems[register]}
              writeOptions={writeOptions}
              gate={resolveProposition(plan.gate, byId)}
              writeRegister={plan.phase.write_register}
              actions={actions}
              canSelect={canSelect}
              onAction={onAction}
            />
          ))}
        </Box>
      </Box>
    </Box>
  );
}

function RegisterConnectors({ registers }) {
  return (
    <svg
      className="register-connectors"
      viewBox="0 0 900 56"
      preserveAspectRatio="none"
      aria-hidden="true"
    >
      {registers.map((register, index) => {
        const meta = metaForRegister(register);
        const target = 150 + index * 300;

        return (
          <path
            key={register}
            d={`M 450 0 C 450 24, ${target} 24, ${target} 56`}
            fill="none"
            stroke={`var(--mantine-color-${meta.color}-6)`}
            strokeWidth="2"
            vectorEffect="non-scaling-stroke"
          />
        );
      })}
    </svg>
  );
}

function RegisterColumn({
  register,
  propositions,
  writeOptions,
  gate,
  writeRegister,
  actions,
  canSelect,
  onAction,
}) {
  const meta = metaForRegister(register);
  const Icon = meta.icon;
  const isGate = gate.register === register;
  const isWrite = writeRegister === register;
  const visiblePropositions = isGate
    ? []
    : isWrite
      ? writeOptions
      : propositions.filter((proposition) => proposition.id !== gate.id);
  const role = isGate && isWrite ? 'GATE + WRITE' : isGate ? 'GATE' : isWrite ? 'WRITE' : 'READ';
  const columnActions = isWrite ? actions : [];

  return (
    <Box
      className="register-column"
      data-register={register}
      data-gate={isGate || undefined}
      data-write={isWrite || undefined}
      style={{ '--register-color': `var(--mantine-color-${meta.color}-6)` }}
    >
      <Group className="register-column__header" justify="space-between" wrap="nowrap">
        <Group gap="sm" wrap="nowrap">
          <ThemeIcon color={meta.color} variant="light" radius="md">
            <Icon size={18} />
          </ThemeIcon>
          <Box>
            <Text fw={750}>{meta.label}</Text>
            <Text size="xs" c="dimmed">{meta.description}</Text>
          </Box>
        </Group>
        <Badge size="xs" color={meta.color} variant={isGate || isWrite ? 'filled' : 'light'}>
          {role}
        </Badge>
      </Group>

      {isGate ? (
        <Box className="register-column__gate">
          <Box>
            <Text className="cycle-workspace__eyebrow" size="xs" ta="center">CURRENT GATE</Text>
            <PropositionCard proposition={gate} selected />
          </Box>
          {isWrite && (
            <ActionCards
              actions={columnActions}
              register={register}
              gate={gate}
              onAction={onAction}
              compact
            />
          )}
        </Box>
      ) : (
        <ScrollArea className="register-column__scroll" type="auto" offsetScrollbars>
          <Stack gap="sm" p="md">
            {isWrite && (
              <ActionCards
                actions={columnActions}
                register={register}
                gate={gate}
                onAction={onAction}
              />
            )}
            {visiblePropositions.map((proposition) => (
              <PropositionCard
                key={proposition.id}
                proposition={proposition}
                compact
                onClick={isWrite && canSelect
                  ? () => onAction('proposition.select', { proposition_id: proposition.id })
                  : undefined}
              />
            ))}
            {!visiblePropositions.length && !columnActions.length && (
              <RegisterEmptyState isWrite={isWrite} />
            )}
          </Stack>
        </ScrollArea>
      )}
    </Box>
  );
}

function ActionCards({ actions, register, gate, onAction, compact = false }) {
  if (!actions.length) return null;

  return (
    <Stack gap="sm" className={compact ? 'register-actions register-actions--compact' : 'register-actions'}>
      {!compact && <Text className="cycle-workspace__eyebrow" size="xs">AVAILABLE MOVES</Text>}
      {actions.map((action) => {
        const meta = actionMeta[action];
        const preview = meta.mirrorsGate
          ? {
              ...gate,
              register,
              tier: action === 'proposition.commit' ? 'committal' : 'expressive',
            }
          : {
              register,
              tier: 'expressive',
              statement: meta.description,
            };

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
    </Stack>
  );
}

function RegisterEmptyState({ isWrite }) {
  return (
    <Stack className="register-empty" gap={4} align="center" justify="center">
      <Text fw={650} size="sm">{isWrite ? 'No admissible move' : 'No visible propositions'}</Text>
      <Text size="xs" c="dimmed" ta="center">
        {isWrite
          ? 'This Phase does not currently expose another contribution here.'
          : 'This register has no propositions in the active Context Horizon.'}
      </Text>
    </Stack>
  );
}

function resolveProposition(proposition, byId) {
  if (!proposition) return {};

  return byId.get(proposition.id) || proposition;
}
