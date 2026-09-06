import { ActionIcon, Box, Divider, Group, ScrollArea, Stack, Text, Tooltip } from '@mantine/core';
import { IconChevronLeft, IconChevronRight, IconGitBranch, IconPencil, IconRefresh } from '@tabler/icons-react';
import { useEffect, useMemo, useRef } from 'react';
import { PropositionCard } from './PropositionCard';

const createMoves = {
  'proposition.variant': {
    label: 'Create a variant',
    description: 'Open a new expressive branch from the current Gate.',
    icon: IconGitBranch,
  },
  'proposition.revise': {
    label: 'Revise in this register',
    description: 'Revise one of your active Propositions in the write register.',
    icon: IconPencil,
  },
  'proposition.reclassify': {
    label: 'Reclassify the Gate',
    description: null,
    icon: IconRefresh,
  },
  'proposition.reclassify_with_instrument': {
    label: 'Reclassify the Gate',
    description: null,
    icon: IconRefresh,
  },
};

export const workspaceActionNames = new Set([
  ...Object.keys(createMoves),
  'proposition.select',
]);

export function ParticipantWorkspace({ propositions, plan, availableActions, onAction }) {
  const historyViewport = useRef(null);
  const writeViewport = useRef(null);
  const byId = useMemo(
    () => new Map(propositions.map((proposition) => [proposition.id, proposition])),
    [propositions],
  );
  const history = useMemo(() => {
    const items = (plan.lineage || [])
      .map((proposition) => byId.get(proposition.id) || proposition);

    if (!items.some((proposition) => proposition.id === plan.gate.id)) {
      items.unshift(byId.get(plan.gate.id) || plan.gate);
    }

    return items.reverse();
  }, [byId, plan.gate, plan.lineage]);
  const writeOptions = useMemo(
    () => (plan.write_options || [])
      .map((proposition) => byId.get(proposition.id) || proposition),
    [byId, plan.write_options],
  );
  const actionCards = availableActions.filter((action) => createMoves[action]);
  const canSelect = availableActions.includes('proposition.select');

  useEffect(() => {
    const frame = requestAnimationFrame(() => {
      const viewport = historyViewport.current;
      if (viewport) viewport.scrollTop = viewport.scrollHeight;
    });

    return () => cancelAnimationFrame(frame);
  }, [plan.gate.id, history.length]);

  function scrollWrite(direction) {
    writeViewport.current?.scrollBy({
      left: direction * Math.max(260, writeViewport.current.clientWidth * 0.8),
      behavior: 'smooth',
    });
  }

  return (
    <Stack gap="lg">
      <Box>
        <Group justify="space-between" mb="sm">
          <Box>
            <Text fw={650}>Readable history</Text>
            <Text size="xs" c="dimmed">Scroll upward from the Gate to follow its lineage.</Text>
          </Box>
          <Text size="xs" fw={650} c="indigo">
            {history.length} / {plan.perspectiveLimit || history.length} visible
          </Text>
        </Group>
        <ScrollArea
          viewportRef={historyViewport}
          className="participant-history"
          type="auto"
          offsetScrollbars
        >
          <Stack gap="sm" className="participant-history__lineage">
            {history.map((proposition, index) => {
              const next = history[index + 1];
              const connectsToNext = next?.parent_ids?.includes(proposition.id);

              return <Box key={proposition.id} className={`participant-history__item ${connectsToNext ? 'participant-history__item--connected' : ''}`}>
                <PropositionCard
                  proposition={proposition}
                  compact={proposition.id !== plan.gate.id}
                  selected={proposition.id === plan.gate.id}
                />
                {proposition.id === plan.gate.id && (
                  <Text ta="center" size="xs" c="indigo" fw={700} mt={6}>CURRENT GATE</Text>
                )}
              </Box>;
            })}
          </Stack>
        </ScrollArea>
      </Box>

      <Divider label="WRITE REGISTER" labelPosition="center" />

      <Box>
        <Group justify="space-between" mb="sm" wrap="nowrap">
          <Box>
            <Text fw={650}>Expressive possibilities</Text>
            <Text size="xs" c="dimmed">Swipe, scroll, or use the arrows to move across.</Text>
          </Box>
          <Group gap={6} wrap="nowrap">
            <Tooltip label="Previous possibility">
              <ActionIcon variant="default" aria-label="Previous possibility" onClick={() => scrollWrite(-1)}>
                <IconChevronLeft size={17} />
              </ActionIcon>
            </Tooltip>
            <Tooltip label="Next possibility">
              <ActionIcon variant="default" aria-label="Next possibility" onClick={() => scrollWrite(1)}>
                <IconChevronRight size={17} />
              </ActionIcon>
            </Tooltip>
          </Group>
        </Group>
        <ScrollArea
          viewportRef={writeViewport}
          type="auto"
          offsetScrollbars
          styles={{ viewport: { scrollSnapType: 'x mandatory' } }}
        >
          <Group wrap="nowrap" align="stretch" gap="sm" pb="sm">
            {writeOptions.map((proposition) => (
              <Box key={proposition.id} className="participant-write-card">
                <PropositionCard
                  proposition={proposition}
                  compact
                  onClick={canSelect
                    ? () => onAction('proposition.select', { proposition_id: proposition.id })
                    : undefined}
                />
              </Box>
            ))}
            {actionCards.map((action) => {
              const move = createMoves[action];
              const mirrorsGate = action.startsWith('proposition.reclassify');
              const preview = mirrorsGate
                ? { ...plan.gate, register: plan.phase.write_register, tier: 'expressive' }
                : {
                    register: plan.phase.write_register,
                    tier: 'expressive',
                    statement: move.description,
                  };

              return (
                <Box key={action} className="participant-write-card">
                  <PropositionCard
                    proposition={preview}
                    compact
                    wireframe
                    actionLabel={move.label}
                    actionIcon={move.icon}
                    onClick={() => onAction(action)}
                  />
                </Box>
              );
            })}
            {!writeOptions.length && !actionCards.length && (
              <Text size="sm" c="dimmed" py="lg">No expressive move is currently admissible.</Text>
            )}
          </Group>
        </ScrollArea>
      </Box>
    </Stack>
  );
}
