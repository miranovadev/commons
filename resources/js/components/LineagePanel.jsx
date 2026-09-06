import { Box, Group, ScrollArea, Stack, Text } from '@mantine/core';
import { metaForRegister } from '../registers';
import { PropositionCard } from './PropositionCard';

export function LineagePanel({ lineage = [], onInspect }) {
  return (
    <Box className="lineage-panel">
      <Group justify="space-between" mb="xs">
        <Text fw={750} size="sm">Lineage</Text>
        <Text size="xs" c="dimmed">{lineage.length} propositions</Text>
      </Group>
      <ScrollArea className="lineage-panel__scroll" type="auto" offsetScrollbars>
        <Stack gap={0} className="lineage-timeline">
          {lineage.map((proposition) => <LineageItem key={proposition.id} proposition={proposition} onInspect={onInspect} />)}
          {!lineage.length && <Text size="xs" c="dimmed" p="sm">No lineage is exposed for the current Gate.</Text>}
        </Stack>
      </ScrollArea>
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
