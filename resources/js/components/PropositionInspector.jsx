import { Badge, Box, Drawer, Group, ScrollArea, Stack, Tabs, Text, Title } from '@mantine/core';
import { useMediaQuery } from '@mantine/hooks';
import { metaForRegister } from '../registers';
import { MeasurementRails, structuralDeltaSummary } from './MeasurementRails';

export function PropositionInspector({ selection, onClose }) {
  const mobile = useMediaQuery('(max-width: 74em)');
  const content = <InspectorContent selection={selection} />;

  if (mobile) {
    return (
      <Drawer opened={Boolean(selection)} onClose={onClose} position="right" size="min(34rem, 100%)" title="Proposition inspector">
        {content}
      </Drawer>
    );
  }

  return (
    <Box className="proposition-inspector">
      <ScrollArea h="100%" type="auto" offsetScrollbars>
        {content}
      </ScrollArea>
    </Box>
  );
}

function InspectorContent({ selection }) {
  if (!selection) {
    return (
      <Stack className="proposition-inspector__empty" align="center" justify="center" gap="xs">
        <Text fw={700}>Inspect structural state</Text>
        <Text size="sm" c="dimmed" ta="center">Select a proposition, lineage node, candidate, or available move to examine it without changing the cycle.</Text>
      </Stack>
    );
  }

  const proposition = selection.proposition;
  const register = metaForRegister(proposition?.register);
  const projected = selection.projectedObservables;
  const summary = structuralDeltaSummary(proposition?.observables, projected);

  return (
    <Stack gap="md" p="md">
      <Box>
        <Group gap={6} mb={6}>
          {proposition?.tier && <Badge size="xs" color={proposition.tier === 'committal' ? 'indigo' : 'gray'}>{proposition.tier}</Badge>}
          {proposition?.register && <Badge size="xs" color={register.color} variant="light">{register.label}</Badge>}
          {selection.kind && <Badge size="xs" color="gray" variant="outline">{selection.kind}</Badge>}
        </Group>
        <Title order={4}>{selection.title || proposition?.title || proposition?.statement?.split('\n')[0] || 'Available move'}</Title>
        {proposition?.statement && <Text size="sm" c="dimmed" mt={5}>{proposition.statement}</Text>}
      </Box>

      <Tabs defaultValue="structural">
        <Tabs.List>
          <Tabs.Tab value="structural">Structural State</Tabs.Tab>
          <Tabs.Tab value="overview">Overview</Tabs.Tab>
        </Tabs.List>
        <Tabs.Panel value="structural" pt="md">
          <Stack gap="lg">
            <Box className="inspector-section">
              <Text fw={750} size="sm" mb="md">Measurement position</Text>
              <MeasurementRails observables={proposition?.observables} projectedObservables={projected} />
            </Box>
            {summary.length > 0 && (
              <Box className="inspector-section">
                <Text fw={750} size="sm" mb="sm">Structural delta</Text>
                <Stack gap={6}>
                  {summary.map((item) => <Text key={item} size="sm">{item}</Text>)}
                </Stack>
              </Box>
            )}
            {!projected && (
              <Text size="xs" c="dimmed">Projected structural measurements are not currently supplied for this selection, so no resulting position is fabricated here.</Text>
            )}
          </Stack>
        </Tabs.Panel>
        <Tabs.Panel value="overview" pt="md">
          <Text size="sm" c="dimmed">This view intentionally stays close to the state already exposed by the participation plan.</Text>
        </Tabs.Panel>
      </Tabs>
    </Stack>
  );
}
