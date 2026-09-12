import { Box, Group, Modal, Stack, Text, UnstyledButton } from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { IconArrowDown, IconChevronDown, IconGitBranch } from '@tabler/icons-react';
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
      <Modal opened={opened} onClose={close} title="Inherited lineage" size="lg" centered>
          <Stack gap={0} className="lineage-timeline">
            {lineage.map((proposition) => <LineageItem key={proposition.id} proposition={proposition} committalObservables={committalObservables} onInspect={(selection) => { close(); onInspect?.(selection); }} />)}
            {!lineage.length && <Text size="xs" c="dimmed" p="sm">No lineage is exposed for the current Gate.</Text>}
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
