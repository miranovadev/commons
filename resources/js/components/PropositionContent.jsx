import { Avatar, Badge, Box, Group, Stack, Text, ThemeIcon, Tooltip } from '@mantine/core';
import { IconArrowRight, IconBolt } from '@tabler/icons-react';
import { metaForRegister } from '../registers';
import { MeasurementRails } from './MeasurementRails';

export function PropositionContent({
  proposition = {},
  mode = 'card',
  actionLabel,
  actionIcon: ActionIcon,
  wireframe = false,
  projectedObservables,
}) {
  const register = metaForRegister(proposition.register);
  const title = actionLabel || proposition.title || proposition.statement?.split('\n')[0] || 'Available move';
  const statementLines = proposition.statement?.split('\n').map((line) => line.trim()).filter(Boolean) || [];
  const description = statementLines.length > 1 ? statementLines.slice(1).join('\n') : null;
  const callsign = proposition.participant?.callsign || proposition.participant_callsign || proposition.callsign || 'Participant';
  const minimal = mode === 'minimal';
  const detailed = mode === 'detailed';
  const impact = proposition.tier === 'expressive' ? proposition.impact : null;

  if (minimal) {
    return (
      <Stack gap={6}>
        <Text size="xs" fw={650} lineClamp={2}>{title}</Text>
        <TransitionReadout transition={proposition.transition} compact />
        <MeasurementRails observables={impact?.from || proposition.observables} projectedObservables={impact?.to} compact bare impact={Boolean(impact)} />
      </Stack>
    );
  }

  return (
    <Stack gap={detailed ? 'md' : 'xs'}>
      <Group justify="space-between" align="flex-start" wrap="nowrap">
        <Stack gap={5} miw={0}>
          <Group gap={7} wrap="wrap">
            {(proposition.tier || wireframe) && <TierBadge tier={wireframe ? 'possible' : proposition.tier} />}
            {proposition.register && <Badge size="xs" color={register.color} variant="light">{register.label}</Badge>}
          </Group>
          <Text fw={detailed ? 750 : 650} size={detailed ? 'lg' : undefined} lineClamp={detailed ? undefined : 2}>{title}</Text>
        </Stack>
        {proposition.participant && (
          <Stack gap={3} align="center" className="proposition-card__participant">
            <Avatar size={detailed ? 36 : 30} radius="xl" color="gray" name={callsign} />
            <Text size="10px" c="dimmed" maw={80} truncate>{callsign}</Text>
          </Stack>
        )}
      </Group>

      <TransitionReadout transition={proposition.transition} />

      {wireframe && ActionIcon && <ThemeIcon color={register.color} variant="light" size="sm"><ActionIcon size={13} /></ThemeIcon>}
      {!wireframe && proposition.tier === 'committal' && <Tooltip label="Committed structure"><ThemeIcon color="indigo" variant="light" size="sm"><IconBolt size={13} /></ThemeIcon></Tooltip>}
      {(description || (detailed && proposition.statement)) && (
        <Text size={detailed ? 'sm' : 'xs'} c="dimmed" lineClamp={detailed ? undefined : 3} style={{ whiteSpace: 'pre-line' }}>
          {detailed ? proposition.statement : description}
        </Text>
      )}

      {!wireframe && (
        <Box className={detailed ? 'proposition-content__measurements' : undefined}>
          {detailed && <Text fw={700} size="sm" mb="sm">{impact ? 'Structural impact' : 'Structural state'}</Text>}
          <MeasurementRails
            observables={impact?.from || proposition.observables}
            projectedObservables={impact?.to || projectedObservables}
            compact={!detailed}
            impact={Boolean(impact)}
          />
        </Box>
      )}
    </Stack>
  );
}

export function TransitionReadout({ transition, compact = false }) {
  if (!transition) return null;

  return (
    <Group gap={5} wrap="nowrap" className="proposition-transition">
      <Badge size="xs" variant="outline" color="gray">{transition.type}</Badge>
      <Text size={compact ? '9px' : 'xs'} c="dimmed">{label(transition.from)}</Text>
      <IconArrowRight size={compact ? 11 : 13} />
      <Text size={compact ? '9px' : 'xs'} fw={650}>{label(transition.to)}</Text>
    </Group>
  );
}

function TierBadge({ tier }) {
  if (tier === 'committal') return <Badge size="xs" color="indigo" variant="filled">Committal</Badge>;
  return <Badge size="xs" variant="light">{tier}</Badge>;
}

function label(value) {
  const text = String(value || '');
  return text ? text.charAt(0).toUpperCase() + text.slice(1) : 'Unknown';
}
