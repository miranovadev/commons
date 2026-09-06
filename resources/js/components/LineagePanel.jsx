import { Badge, Box, Group, ScrollArea, Stack, Text, UnstyledButton } from '@mantine/core';
import { metaForRegister } from '../registers';

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
      <UnstyledButton className="lineage-item__card" onClick={() => onInspect?.({ kind: 'lineage', proposition })}>
        <Group gap={6} mb={4} wrap="nowrap">
          <Text size="10px" c="dimmed">#{shortId(proposition.id)}</Text>
          <Badge size="xs" color={register.color} variant="light">{register.label}</Badge>
          {proposition.tier && <Text size="10px" c="dimmed">{proposition.tier}</Text>}
        </Group>
        <Text size="xs" fw={650} lineClamp={2}>{proposition.title || proposition.statement}</Text>
      </UnstyledButton>
    </Box>
  );
}

function shortId(id) {
  if (id == null) return '—';
  return String(id).slice(-6);
}
