import { ActionIcon, Box, Group, Popover, Stepper, Text } from '@mantine/core';
import { IconInfoCircle } from '@tabler/icons-react';
import { phaseMeta, phaseOrder, phaseTitle } from '../phases';
import type { PhaseKind } from '../types';

interface PhaseIdentityProps { kind: PhaseKind; selectedKind?: PhaseKind; onSelect?: (kind: PhaseKind) => void; busy?: boolean; }
export function PhaseIdentity({ kind, selectedKind = kind, onSelect, busy }: PhaseIdentityProps) {
  const meta = phaseMeta[selectedKind];
  const Icon = meta.icon;
  return <Box className="cycle-context-header__phase">
    <Group className="phase-title" gap={7} wrap="nowrap"><Icon size={17} /><Text size="sm" fw={650}>Phase {meta.number} · {phaseTitle(selectedKind)}</Text>
      <Popover width={260} position="bottom-start" withArrow>
        <Popover.Target><ActionIcon className="phase-caption-info" size={28} variant="subtle" color="gray" aria-label={`About ${phaseTitle(selectedKind)}`}><IconInfoCircle size={17} /></ActionIcon></Popover.Target>
        <Popover.Dropdown><Text size="sm">{meta.description}</Text></Popover.Dropdown>
      </Popover>
    </Group>
    <Text className="phase-caption" size="xs" c="dimmed">{meta.description}</Text>
    <PhaseProgress kind={kind} selectedKind={selectedKind} onSelect={onSelect} busy={busy} />
  </Box>;
}
export function PhaseProgress({ kind, selectedKind = kind, onSelect, busy = false }: PhaseIdentityProps) {
  const active = phaseMeta[kind].number - 1;
  return <Stepper className="phase-progress" active={active} color="indigo" size="xs" iconSize={28} contentPadding={0}
    wrap={false} aria-label="Cycle Phase progress" onStepClick={index => {
      if (!busy && index <= active) onSelect?.(phaseOrder[index]);
    }} allowNextStepsSelect={false}>
    {phaseOrder.map(phase => { const meta = phaseMeta[phase]; const Icon = meta.icon;
      const current = phase === kind;
      const available = meta.number <= phaseMeta[kind].number;
      return <Stepper.Step key={phase} label={phaseTitle(phase)} icon={<Icon size={15} />} completedIcon={<Icon size={15} />}
        color={current ? 'indigo' : 'gray'} data-state={current ? 'current' : available ? 'prior' : 'future'}
        data-preview={phase === selectedKind && !current || undefined}
        aria-label={current ? `Return to current Phase: ${phaseTitle(phase)}` : `Preview ${phaseTitle(phase)} registers`}
        aria-current={current ? 'step' : undefined} aria-pressed={phase === selectedKind}
        disabled={!available || busy || !onSelect} allowStepSelect={available && !busy && Boolean(onSelect)} />;
    })}
  </Stepper>;
}
