import { Badge, Card, Group, HoverCard, Stack, Text, ThemeIcon, Tooltip } from '@mantine/core';
import { IconBolt, IconCirclesRelation, IconLock, IconWhirl } from '@tabler/icons-react';
import { metaForRegister } from '../registers';

const observableMeta = {
  correspondence: { label: 'Correspondence', description: 'Structural similarity between complementary Domains.', icon: IconCirclesRelation, color: 'blue' },
  chirality: { label: 'Chirality', description: 'Directional alignment of active Attestations between Domains.', icon: IconWhirl, color: 'violet' },
  closure: { label: 'Closure', description: 'Persistence across participatory depth and cadence.', icon: IconLock, color: 'teal' },
};

export function PropositionCard({
  proposition,
  selected = false,
  compact = false,
  wireframe = false,
  actionLabel,
  actionIcon: ActionIcon,
  onClick,
}) {
  const register = metaForRegister(proposition.register);

  return (
    <Card
      component={onClick ? 'button' : 'div'}
      type={onClick ? 'button' : undefined}
      withBorder
      padding={compact ? 'md' : 'lg'}
      className={`proposition-card ${selected ? 'proposition-card--selected' : ''} ${wireframe ? 'proposition-card--wireframe' : ''}`}
      onClick={onClick}
    >
      <Group justify="space-between" align="flex-start" wrap="nowrap">
        <Stack gap={5} miw={0}>
          <Group gap={7}>
            <Badge size="xs" color={proposition.tier === 'committal' ? 'indigo' : 'gray'} variant={proposition.tier === 'committal' ? 'filled' : 'light'}>{wireframe ? 'possible' : proposition.tier}</Badge>
            <Badge size="xs" color={register.color} variant="light">{register.label}</Badge>
          </Group>
          <Text fw={650} lineClamp={1}>{actionLabel || proposition.title || proposition.statement?.split('\n')[0]}</Text>
        </Stack>
        {wireframe && ActionIcon && <ThemeIcon color={register.color} variant="light" size="sm"><ActionIcon size={13} /></ThemeIcon>}
        {!wireframe && proposition.tier === 'committal' && <Tooltip label="Committed structure"><ThemeIcon color="indigo" variant="light" size="sm"><IconBolt size={13} /></ThemeIcon></Tooltip>}
      </Group>
      <Text size="sm" c="dimmed" lineClamp={compact ? 2 : 3} mt="sm">{proposition.statement}</Text>
      {!wireframe && <Group gap={5} mt="md" onClick={(event) => event.stopPropagation()}>
        {Object.entries(observableMeta).map(([key, meta]) => <Observable key={key} name={key} value={proposition.observables?.[key]} {...meta} />)}
      </Group>}
    </Card>
  );
}

function Observable({ name, value, label, description, icon: Icon, color }) {
  const display = typeof value === 'number' ? `${value >= 0 ? '+' : ''}${value.toFixed(2)}` : '—';
  const speed = name === 'chirality' && typeof value === 'number' ? Math.max(2, 8 - Math.abs(value) * 6) : null;
  return (
    <HoverCard width={245} shadow="md" openDelay={250} position="top">
      <HoverCard.Target>
        <Group gap={4} className="observable-pill" data-empty={value == null || undefined}>
          <Icon size={14} className={name === 'chirality' && value != null ? 'chirality-icon' : ''} style={speed ? { '--spin-speed': `${speed}s` } : undefined} />
          <Text size="xs" fw={650}>{display}</Text>
        </Group>
      </HoverCard.Target>
      <HoverCard.Dropdown>
        <Group gap="xs" mb={6}><ThemeIcon color={color} variant="light" size="sm"><Icon size={14} /></ThemeIcon><Text fw={650} size="sm">{label}</Text></Group>
        <Text size="xs" c="dimmed">{description}</Text>
        {value == null && <Text size="xs" mt="xs" c="indigo">Awaiting the observable measurement layer.</Text>}
      </HoverCard.Dropdown>
    </HoverCard>
  );
}
