import { Box, Collapse, Group, ScrollArea, Stack, Text, UnstyledButton } from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { IconChevronDown, IconGitBranch } from '@tabler/icons-react';
import { metaForRegister } from '../registers';
import { PropositionCard } from './PropositionCard';

export function LineagePanel({ lineage = [], onInspect }) {
  const [opened, { toggle }] = useDisclosure(false);

  return (
    <Box className="lineage-panel" data-opened={opened || undefined}>
      <UnstyledButton className="lineage-panel__toggle" onClick={toggle} aria-expanded={opened}>
        <Group justify="space-between" wrap="nowrap">
          <Group gap="sm" wrap="nowrap">
            <IconGitBranch size={18} />
            <Box>
              <Text fw={700} size="sm">Lineage</Text>
              <Text size="xs" c="dimmed">{lineage.length} inherited proposition{lineage.length === 1 ? '' : 's'}</Text>
            </Box>
          </Group>
          <Group gap="xs" wrap="nowrap">
            <Text size="xs" c="dimmed">{opened ? 'Hide' : 'Explore'}</Text>
            <IconChevronDown className="lineage-panel__chevron" size={17} />
          </Group>
        </Group>
      </UnstyledButton>
      <Collapse in={opened}>
        <ScrollArea className="lineage-panel__scroll" type="auto" offsetScrollbars>
          <Stack gap={0} className="lineage-timeline">
            {lineage.map((proposition) => <LineageItem key={proposition.id} proposition={proposition} onInspect={onInspect} />)}
            {!lineage.length && <Text size="xs" c="dimmed" p="sm">No lineage is exposed for the current Gate.</Text>}
          </Stack>
        </ScrollArea>
      </Collapse>
    </Box>
  );
}

function LineageItem({ proposition, onInspect }) {
  const register = metaForRegister(proposition.register);
  return (
    <Box className="lineage-item" style={{ '--register-color': `var(--mantine-color-${register.color}-6)` }}>
      <span className="lineage-item__orb" />
      <PropositionCard proposition={proposition} mini onClick={() => onInspect?.({ kind: 'lineage', proposition })} />
    </Box>
  );
}
