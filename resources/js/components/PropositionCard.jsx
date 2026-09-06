import { Avatar, Badge, Card, Group, Stack, Text, ThemeIcon, Tooltip } from '@mantine/core';
import { IconBolt, IconWhirl } from '@tabler/icons-react';
import { metaForRegister } from '../registers';
import { MeasurementRails } from './MeasurementRails';

export function PropositionCard({
  proposition,
  selected = false,
  compact = false,
  mini = false,
  wireframe = false,
  muted = false,
  indigoHighlight = false,
  actionLabel,
  actionIcon: ActionIcon,
  projectedObservables,
  onClick,
}) {
  const register = metaForRegister(proposition.register);
  const title = actionLabel || proposition.title || proposition.statement?.split('\n')[0];
  const statementLines = proposition.statement?.split('\n').map((line) => line.trim()).filter(Boolean) || [];
  const description = statementLines.length > 1 ? statementLines.slice(1).join('\n') : null;
  const callsign = proposition.participant?.callsign || proposition.participant_callsign || proposition.callsign || 'Participant';
  const structurallyHighlighted = indigoHighlight || proposition.tier === 'committal';

  if (mini) {
    return (
      <Card
        component={onClick ? 'button' : 'div'}
        type={onClick ? 'button' : undefined}
        withBorder
        padding="xs"
        className={`proposition-card proposition-card--mini ${selected ? 'proposition-card--selected' : ''} ${structurallyHighlighted ? 'proposition-card--indigo' : ''}`}
        style={{ '--register-color': `var(--mantine-color-${register.color}-6)` }}
        onClick={onClick}
      >
        <Text size="xs" fw={650} lineClamp={2}>{title}</Text>
      </Card>
    );
  }

  return (
    <Card
      component={onClick ? 'button' : 'div'}
      type={onClick ? 'button' : undefined}
      withBorder
      padding={compact ? 'sm' : 'lg'}
      className={`proposition-card ${selected ? 'proposition-card--selected' : ''} ${wireframe ? 'proposition-card--wireframe' : ''} ${muted ? 'proposition-card--muted' : ''} ${indigoHighlight ? 'proposition-card--indigo' : ''}`}
      style={{ '--register-color': `var(--mantine-color-${register.color}-6)` }}
      onClick={onClick}
    >
      <Group justify="space-between" align="flex-start" wrap="nowrap">
        <Stack gap={5} miw={0}>
          <Group gap={7} wrap="nowrap">
            {(proposition.tier || wireframe) && <TierBadge tier={wireframe ? 'possible' : proposition.tier} />}
            <Badge size="xs" color={register.color} variant="light">{register.label}</Badge>
            {!wireframe && (
              <Tooltip label="Attestation activity">
                <IconWhirl size={15} className="attestation-spinner" />
              </Tooltip>
            )}
          </Group>
          <Text fw={650} lineClamp={2}>{title}</Text>
        </Stack>
        <Stack gap={3} align="center" className="proposition-card__participant">
          <Avatar size={compact ? 24 : 30} radius="xl" color="gray" name={callsign} />
          <Text size="10px" c="dimmed" maw={70} truncate>{callsign}</Text>
        </Stack>
      </Group>
      {wireframe && ActionIcon && <ThemeIcon color={register.color} variant="light" size="sm" mt="xs"><ActionIcon size={13} /></ThemeIcon>}
      {!wireframe && proposition.tier === 'committal' && <Tooltip label="Committed structure"><ThemeIcon color="indigo" variant="light" size="sm" mt="xs"><IconBolt size={13} /></ThemeIcon></Tooltip>}
      {description && <Text size="xs" c="dimmed" lineClamp={compact ? 2 : 3} mt="xs" style={{ whiteSpace: 'pre-line' }}>{description}</Text>}
      {!wireframe && <Stack gap={5} mt="sm" onClick={(event) => event.stopPropagation()}><MeasurementRails observables={proposition.observables} projectedObservables={projectedObservables} compact /></Stack>}
    </Card>
  );
}

function TierBadge({ tier }) {
  if (tier === 'committal') return <Badge size="xs" color="indigo" variant="filled">Committal</Badge>;
  return <Badge size="xs" variant="light">{tier}</Badge>;
}
