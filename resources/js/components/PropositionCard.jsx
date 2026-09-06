import { Badge, Card, Group, Stack, Text, ThemeIcon, Tooltip } from '@mantine/core';
import { IconBolt } from '@tabler/icons-react';
import { metaForRegister } from '../registers';
import { MeasurementRails } from './MeasurementRails';

export function PropositionCard({
  proposition,
  selected = false,
  compact = false,
  wireframe = false,
  muted = false,
  actionLabel,
  actionIcon: ActionIcon,
  projectedObservables,
  onClick,
}) {
  const register = metaForRegister(proposition.register);

  return (
    <Card
      component={onClick ? 'button' : 'div'}
      type={onClick ? 'button' : undefined}
      withBorder
      padding={compact ? 'sm' : 'lg'}
      className={`proposition-card ${selected ? 'proposition-card--selected' : ''} ${wireframe ? 'proposition-card--wireframe' : ''} ${muted ? 'proposition-card--muted' : ''}`}
      style={{ '--register-color': `var(--mantine-color-${register.color}-6)` }}
      onClick={onClick}
    >
      <Group justify="space-between" align="flex-start" wrap="nowrap">
        <Stack gap={5} miw={0}>
          <Group gap={7}>
            {(proposition.tier || wireframe) && <Badge size="xs" color={proposition.tier === 'committal' ? 'indigo' : 'gray'} variant={proposition.tier === 'committal' ? 'filled' : 'light'}>{wireframe ? 'possible' : proposition.tier}</Badge>}
            <Badge size="xs" color={register.color} variant="light">{register.label}</Badge>
          </Group>
          <Text fw={650} lineClamp={2}>{actionLabel || proposition.title || proposition.statement?.split('\n')[0]}</Text>
        </Stack>
        {wireframe && ActionIcon && <ThemeIcon color={register.color} variant="light" size="sm"><ActionIcon size={13} /></ThemeIcon>}
        {!wireframe && proposition.tier === 'committal' && <Tooltip label="Committed structure"><ThemeIcon color="indigo" variant="light" size="sm"><IconBolt size={13} /></ThemeIcon></Tooltip>}
      </Group>
      {proposition.statement && <Text size="xs" c="dimmed" lineClamp={compact ? 2 : 3} mt="xs">{proposition.statement}</Text>}
      {!wireframe && <Stack gap={5} mt="sm" onClick={(event) => event.stopPropagation()}><MeasurementRails observables={proposition.observables} projectedObservables={projectedObservables} compact /></Stack>}
    </Card>
  );
}
