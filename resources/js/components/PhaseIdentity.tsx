import { ActionIcon, Box, Group, Popover, Text } from '@mantine/core';
import { IconInfoCircle } from '@tabler/icons-react';
import { phaseMeta, phaseOrder, phaseTitle } from '../phases';
import type { PhaseKind } from '../types';

export function PhaseIdentity({ kind }: { kind: PhaseKind }) {
  const meta = phaseMeta[kind];
  const Icon = meta.icon;
  return <Box className="cycle-context-header__phase">
    <Group gap={7} wrap="nowrap"><Icon size={17} /><Text size="sm" fw={650}>Phase {meta.number} · {phaseTitle(kind)}</Text>
      <Popover width={260} position="bottom-start" withArrow>
        <Popover.Target><ActionIcon className="phase-caption-info" size={28} variant="subtle" color="gray" aria-label={`About ${phaseTitle(kind)}`}><IconInfoCircle size={17} /></ActionIcon></Popover.Target>
        <Popover.Dropdown><Text size="sm">{meta.description}</Text></Popover.Dropdown>
      </Popover>
    </Group>
    <Text className="phase-caption" size="xs" c="dimmed">{meta.description}</Text>
    <PhaseProgress kind={kind} />
  </Box>;
}
export function PhaseProgress({ kind }: { kind: PhaseKind }) {
  return <Box component="ol" className="phase-progress" aria-label="Cycle Phase progress">
    {phaseOrder.map(phase => { const meta = phaseMeta[phase]; const Icon = meta.icon;
      const state = phase === kind ? 'current' : meta.number < phaseMeta[kind].number ? 'prior' : 'future';
      return <Box component="li" key={phase} data-state={state} aria-current={state === 'current' ? 'step' : undefined}>
        <Icon size={14} aria-hidden="true" /><span>{phaseTitle(phase)}</span>
      </Box>;
    })}
  </Box>;
}
