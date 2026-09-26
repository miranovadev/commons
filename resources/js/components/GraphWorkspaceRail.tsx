import { ActionIcon, Box, Button, Checkbox, Group, NativeSelect, SegmentedControl, Stack, Text, Tooltip } from '@mantine/core';
import { IconFocus2, IconLayoutSidebarRightCollapse, IconLayoutSidebarRightExpand } from '@tabler/icons-react';
import { graphOverlayDefinitions, graphOverlayOptions } from '../graphOverlays';
import type { GraphOverlayMode } from '../graphOverlays';
import type { GraphDensity } from '../graphLayout';
import type { GraphRailAction, GraphRailState } from '../graphWorkspace';

interface Props {
  state: GraphRailState;
  onState: (action: GraphRailAction) => void;
  overlayMode: GraphOverlayMode;
  onOverlay: (mode: string) => void;
  density: GraphDensity;
  onDensity: (density: string) => void;
  hasSelection: boolean;
  isolate: boolean;
  onIsolate: (isolate: boolean) => void;
  onShowOrigin: () => void;
}

export function GraphWorkspaceRail({ state, onState, overlayMode, onOverlay, hasSelection, isolate,
  onIsolate, onShowOrigin, density, onDensity }: Props) {
  const opened = state === 'open';
  const toggleLabel = opened ? 'Collapse graph workspace' : 'Open graph workspace';
  return <Box component="aside" className="graph-workspace" data-state={state} aria-label="Graph workspace">
    <Group className="graph-workspace__header" gap={4} wrap="nowrap">
      <Tooltip label={toggleLabel} withArrow><ActionIcon variant="subtle" color="gray" size="lg"
        aria-label={toggleLabel} aria-expanded={opened} aria-controls="graph-workspace-content"
        onClick={() => onState(opened ? 'collapse' : 'open')}>
        {opened ? <IconLayoutSidebarRightCollapse size={18} /> : <IconLayoutSidebarRightExpand size={18} />}
      </ActionIcon></Tooltip>
      {opened && <Text size="sm" fw={700} style={{ flex: 1 }}>Workspace</Text>}

    </Group>
    {opened && <Box id="graph-workspace-content" className="graph-workspace__content">
      <Stack component="section" className="graph-workspace__section substrate-graph-controls" aria-label="Visualisation" gap="xs">
        <Box>
          <Text size="xs" fw={600} mb={4} id="graph-density-label">View</Text>
          <SegmentedControl fullWidth size="xs" aria-labelledby="graph-density-label" value={density} onChange={onDensity}
            data={[{ value: 'detailed', label: 'Detailed' }, { value: 'topology', label: 'Topology' }]} />
        </Box>
        <NativeSelect label="Visualisation" size="xs" value={overlayMode} data={graphOverlayOptions}
          onChange={event => onOverlay(event.currentTarget.value)} />
        <Text size="xs" c="dimmed">{graphOverlayDefinitions[overlayMode].legend}</Text>
        <Button size="compact-xs" variant="light" leftSection={<IconFocus2 size={15} />} onClick={onShowOrigin}>Show Origin</Button>
      </Stack>
      <Stack component="section" className="graph-workspace__section" aria-label="Selected Lineage" gap="xs">
        <Group justify="space-between" wrap="nowrap"><Text fw={700} size="sm">Selected Lineage</Text>
        </Group>
        {!hasSelection && <Text size="xs" c="dimmed">Select a proposition to focus its lineage.</Text>}
        <Checkbox size="xs" label="Isolate Selected Lineage" checked={isolate} disabled={!hasSelection}
          onChange={event => onIsolate(event.currentTarget.checked)} />
      </Stack>

    </Box>}
  </Box>;
}

