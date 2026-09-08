import { Avatar, Badge, Box, Group, SimpleGrid, Stack, Text, ThemeIcon, Tooltip } from '@mantine/core';
import { IconArrowRight, IconBolt, IconHexagon, IconHexagonalPrism, IconLayersDifference } from '@tabler/icons-react';
import { metaForRegister } from '../registers';
import type { Observables, Proposition } from '../types';

interface PropositionContentProps {
  proposition?: Partial<Proposition>;
  mode?: 'minimal' | 'card' | 'detailed';
  actionLabel?: string;
  actionIcon?: React.ElementType;
  wireframe?: boolean;
  projectedObservables?: Observables;
  committalObservables?: Observables;
}

export function PropositionContent({
  proposition = {},
  mode = 'card',
  actionLabel,
  actionIcon: ActionIcon,
  wireframe = false,
  projectedObservables,
  committalObservables,
}: PropositionContentProps) {
  const register = metaForRegister(proposition.register);
  const title = actionLabel || proposition.title || proposition.statement?.split('\n')[0] || 'Available move';
  const statementLines = proposition.statement?.split('\n').map((line) => line.trim()).filter(Boolean) || [];
  const description = statementLines.length > 1 ? statementLines.slice(1).join('\n') : null;
  const callsign = proposition.participant?.callsign || proposition.participant_callsign || proposition.callsign || 'Participant';
  const minimal = mode === 'minimal';
  const detailed = mode === 'detailed';

  if (minimal) {
    return (
      <Stack gap={6}>
        <Text size="xs" fw={650} lineClamp={2}>{title}</Text>
        <TransitionReadout transition={proposition.transition} compact />
        <FieldValues observables={proposition.observables} committalObservables={committalObservables} tier={proposition.tier} compact />
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
          {detailed && <Text fw={700} size="sm" mb="sm">Field state</Text>}
          <FieldValues
            observables={projectedObservables || proposition.observables}
            committalObservables={committalObservables}
            tier={proposition.tier}
            compact={!detailed}
          />
        </Box>
      )}
    </Stack>
  );
}

function FieldValues({ observables, committalObservables, tier, compact = false }: {
  observables?: Observables;
  committalObservables?: Observables;
  tier?: string;
  compact?: boolean;
}) {
  const expressive = tier === 'expressive';
  const state = fieldState(observables);
  const committed = fieldState(committalObservables);

  if (!state) return <Text size="xs" c="dimmed">Field awaiting complete measurement</Text>;

  const values = [
    {
      key: 'tension',
      label: 'Tension',
      icon: IconLayersDifference,
      value: state.tension,
      delta: expressive && committed ? state.tension - committed.tension : null,
      signed: true,
    },
    {
      key: 'charge',
      label: expressive ? 'Expressive Charge' : 'Charge',
      icon: IconHexagon,
      value: state.charge,
      delta: expressive && committed ? state.charge - committed.charge : null,
    },
    ...(expressive ? [{
      key: 'potential-charge',
      label: 'Potential Charge',
      icon: IconHexagonalPrism,
      value: committed ? Math.max(0, state.charge - committed.charge) : null,
      delta: null,
    }] : []),
  ];
  const items = values.map(({ key, ...field }) => (
    <FieldValue key={key} {...field} tier={tier} compact={compact} />
  ));

  return compact
    ? <Group className="proposition-field" data-tier={tier} gap="sm" wrap="wrap">{items}</Group>
    : <SimpleGrid className="proposition-field" data-tier={tier} cols={{ base: 1, xs: values.length }} spacing="xs">{items}</SimpleGrid>;
}

function FieldValue({ label, icon: Icon, value, delta, signed: signedValue = false, tier, compact }: {
  label: string;
  icon: React.ElementType;
  value: number | null;
  delta: number | null;
  signed?: boolean;
  tier?: string;
  compact?: boolean;
}) {
  const color = tier === 'committal' ? 'indigo' : 'gray';
  const readout = value === null ? 'Awaiting measurement' : number(value, signedValue);
  const deltaReadout = delta === null ? null : `Δ ${number(delta, true)} from Committal`;
  const content = (
    <Box className="field-value" data-compact={compact || undefined}>
      <Group gap={5} wrap="nowrap">
        <ThemeIcon color={color} variant={compact ? 'transparent' : 'light'} size={compact ? 22 : 28}>
          <Icon size={compact ? 15 : 17} />
        </ThemeIcon>
        {!compact && <Text size="xs" c="dimmed" truncate>{label}</Text>}
        <Text size="xs" fw={750} c={color} style={{ whiteSpace: 'nowrap' }}>{readout}</Text>
      </Group>
      {!compact && deltaReadout && <Text size="10px" c="dimmed" mt={3}>{deltaReadout}</Text>}
    </Box>
  );

  return compact
    ? <Tooltip label={`${label}: ${readout}${deltaReadout ? ` · ${deltaReadout}` : ''}`} withArrow>{content}</Tooltip>
    : content;
}

function fieldState(observables?: Observables) {
  const keys: Array<keyof Observables> = ['correspondence', 'chirality', 'closure'];
  const values = keys.map((key) => finite(observables?.[key]));
  if (values.some((value) => value === null)) return null;

  const measurements = values as number[];
  const tension = measurements.reduce((sum, value) => sum + value, 0) / measurements.length;
  return { tension, charge: 1 - Math.abs(tension) };
}

function finite(value: unknown): number | null {
  if (value === null || value === undefined || value === '') return null;
  const numeric = typeof value === 'number' ? value : Number(value);
  return Number.isFinite(numeric) ? numeric : null;
}

function number(value: number | null, signedValue = false) {
  if (value === null) return '—';
  return `${signedValue && value >= 0 ? '+' : ''}${value.toFixed(2)}`;
}

export function TransitionReadout({ transition, compact = false }: {
  transition?: Proposition['transition'];
  compact?: boolean;
}) {
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

function TierBadge({ tier }: { tier?: string }) {
  if (tier === 'committal') return <Badge size="xs" color="indigo" variant="filled">Committal</Badge>;
  return <Badge size="xs" variant="light">{tier}</Badge>;
}

function label(value?: string) {
  const text = String(value || '');
  return text ? text.charAt(0).toUpperCase() + text.slice(1) : 'Unknown';
}
