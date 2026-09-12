import { useCallback, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { chronologicalLineage } from '../lineage';
import { Box, Button, Group, Modal, ScrollArea, Stack, Text, UnstyledButton } from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { IconArrowDown, IconArrowUp, IconChevronDown, IconGitBranch } from '@tabler/icons-react';
import { metaForRegister } from '../registers';
import { PropositionCard } from './PropositionCard';
import type { Observables, Proposition, PropositionSelection } from '../types';

interface LineagePanelProps {
  lineage?: Proposition[];
  committalObservables?: Observables;
  onInspect?: (selection: PropositionSelection) => void;
}

export function LineagePanel({ lineage = [], committalObservables, onInspect }: LineagePanelProps) {
  const [opened, { toggle, close }] = useDisclosure(false);

  const ordered = useMemo(() => chronologicalLineage(lineage), [lineage]);
  const viewport = useRef<HTMLDivElement | null>(null);
  const [bounds, setBounds] = useState({ overflow: false, start: false, end: true });
  const measure = useCallback(() => {
    const node = viewport.current;
    if (node) setBounds({ overflow: node.scrollHeight > node.clientHeight + 2, start: node.scrollTop <= 2, end: node.scrollTop + node.clientHeight >= node.scrollHeight - 2 });
  }, []);
  const attachViewport = useCallback((node: HTMLDivElement | null) => {
    viewport.current = node;
    if (node) { node.scrollTop = node.scrollHeight; measure(); }
  }, [measure]);
  useLayoutEffect(() => {
    const node = viewport.current;
    if (!opened || !node) return;
    // Resize and incoming lineage updates preserve the user's current scroll position.
    const observer = new ResizeObserver(measure);
    observer.observe(node);
    if (node.firstElementChild) observer.observe(node.firstElementChild);
    measure();
    return () => observer.disconnect();
  }, [opened, ordered, measure]);
  function endpoint(present: boolean) {
    const node = viewport.current;
    node?.scrollTo({ top: present ? node.scrollHeight : 0, behavior: 'smooth' });
  }

  return (
    <Box className="lineage-panel" data-opened={opened || undefined}>
      <UnstyledButton className="lineage-panel__toggle" onClick={toggle} aria-expanded={opened}>
        <Group justify="space-between" wrap="nowrap">
          <Group gap="sm" wrap="nowrap">
            <IconGitBranch size={18} />
            <Box>
              <Text size="xs" fw={600}>Lineage <Text span c="dimmed" inherit>· {lineage.length} inherited</Text></Text>
            </Box>
          </Group>
          <Group gap="xs" wrap="nowrap">
            <Text size="xs" c="dimmed">{opened ? 'Hide' : 'Explore'}</Text>
            <IconChevronDown className="lineage-panel__chevron" size={17} />
          </Group>
        </Group>
      </UnstyledButton>
      <IconArrowDown className="region-transition lineage-transition" size={24} stroke={2.2} aria-hidden="true" />
      <Modal opened={opened} onClose={close} title="Inherited lineage" size="lg" centered>
          <Stack gap={4}>
          {bounds.overflow && <Button className="lineage-endpoint" variant="subtle" color="gray" size="compact-xs" leftSection={<IconArrowUp size={13} />} disabled={bounds.start} onClick={() => endpoint(false)}>Provenance</Button>}
          <ScrollArea.Autosize mah="60dvh" viewportRef={attachViewport} onScrollPositionChange={measure} type="hover">
          <Stack gap={0} className="lineage-timeline">
            {ordered.map((proposition) => <LineageItem key={proposition.id} proposition={proposition} committalObservables={committalObservables} onInspect={(selection) => { close(); onInspect?.(selection); }} />)}
            {!lineage.length && <Text size="xs" c="dimmed" p="sm">No lineage is exposed for the current Gate.</Text>}
          </Stack>
          </ScrollArea.Autosize>
          {bounds.overflow && <Button className="lineage-endpoint lineage-endpoint--present" variant="subtle" color="gray" size="compact-xs" leftSection={<IconArrowDown size={13} />} disabled={bounds.end} onClick={() => endpoint(true)}>Present</Button>}
          </Stack>
      </Modal>
    </Box>
  );
}

function LineageItem({ proposition, committalObservables, onInspect }: {
  proposition: Proposition;
  committalObservables?: Observables;
  onInspect?: (selection: PropositionSelection) => void;
}) {
  const register = metaForRegister(proposition.register);
  return (
    <Box className="lineage-item" style={{ '--register-color': `var(--mantine-color-${register.color}-6)` }}>
      <span className="lineage-item__orb" />
      <IconArrowDown className="lineage-item__arrow" size={22} stroke={2.2} aria-hidden="true" />
      <PropositionCard proposition={proposition} committalObservables={committalObservables} mini onClick={() => onInspect?.({ kind: 'lineage', proposition })} />
    </Box>
  );
}
