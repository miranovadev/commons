import { Box, Button, Center, Group, Paper, SimpleGrid, Stack, Text, ThemeIcon, Title } from '@mantine/core';
import { IconArrowUp, IconGitBranch, IconSparkles } from '@tabler/icons-react';
import { useEffect, useMemo, useState } from 'react';
import { PropositionCard } from './PropositionCard';

export function PropositionGraph({ propositions, initialFocusId, perspectiveLimit, participantMode = false }) {
  const origin = useMemo(() => propositions.find((p) => p.is_origin) || propositions[0], [propositions]);
  const [focusId, setFocusId] = useState(initialFocusId || origin?.id);
  useEffect(() => {
    setFocusId(initialFocusId || origin?.id);
  }, [initialFocusId, origin?.id]);
  const focus = propositions.find((p) => p.id === focusId) || origin;
  const byId = useMemo(() => new Map(propositions.map((p) => [p.id, p])), [propositions]);
  const parents = (focus?.parent_ids || []).map((id) => byId.get(id)).filter(Boolean);
  const children = (focus?.children || []).map(({ id }) => byId.get(id)).filter(Boolean);

  if (!focus) {
    return (
      <Paper withBorder p={50} ta="center">
        <ThemeIcon color="indigo" variant="light" size={50} radius="xl" mx="auto"><IconSparkles size={24} /></ThemeIcon>
        <Title order={3} mt="md">The Origin has not been written</Title>
        <Text c="dimmed" mt="xs">This substrate is balanced, empty, and carrying its full available Charge.</Text>
      </Paper>
    );
  }

  return (
    <Box className="graph-viewport" style={perspectiveLimit ? { '--perspective-limit': perspectiveLimit } : undefined}>
      {participantMode && <Group justify="space-between" mb="md"><Text size="sm" c="dimmed">Your readable lineage is bounded by Perspective.</Text><Text size="xs" fw={650} c="indigo">{propositions.length} / {perspectiveLimit || propositions.length} visible</Text></Group>}
      <Stack gap={0} align="stretch">
        {parents.length > 0 && (
          <Box className="graph-level graph-level--parents">
            <Text className="graph-label" size="xs" c="dimmed">ASCEND</Text>
            <SimpleGrid cols={{ base: 1, sm: Math.min(2, parents.length) }} maw={760} mx="auto">
              {parents.map((parent) => <PropositionCard key={parent.id} proposition={parent} compact onClick={() => setFocusId(parent.id)} />)}
            </SimpleGrid>
          </Box>
        )}

        <Center className={`graph-level graph-level--focus ${parents.length ? 'graph-connected-above' : ''}`}>
          <Box w="100%" maw={560}>
            <PropositionCard proposition={focus} selected />
            {focus.is_origin && <Text ta="center" size="xs" c="indigo" fw={650} mt="xs">ORIGIN</Text>}
          </Box>
        </Center>

        {children.length > 0 ? (
          <Box className="graph-level graph-level--children graph-connected-above">
            <Group gap={6} justify="center" mb="md"><IconGitBranch size={14} /><Text className="graph-label" size="xs" c="dimmed">DESCEND</Text></Group>
            <SimpleGrid cols={{ base: 1, sm: Math.min(2, children.length), md: Math.min(3, children.length) }}>
              {children.map((child) => <PropositionCard key={child.id} proposition={child} compact onClick={() => setFocusId(child.id)} />)}
            </SimpleGrid>
          </Box>
        ) : (
          <Center mt="xl"><Button variant="subtle" color="gray" size="xs" leftSection={<IconArrowUp size={14} />} onClick={() => parents[0] && setFocusId(parents[0].id)} disabled={!parents.length}>Active head · ascend</Button></Center>
        )}
      </Stack>
    </Box>
  );
}
