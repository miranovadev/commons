import { useContext } from 'react';
import { GraphTensionPalette } from '../graphPresentation';
import { Badge, Box, Group, SimpleGrid, Stack, Text, ThemeIcon, Tooltip } from '@mantine/core';
import { IconArrowRight, IconBolt, IconHexagon, IconHexagonalPrism, IconLayersDifference } from '@tabler/icons-react';
import { ParticipantAvatar } from './ParticipantIdentity';
import { OperationIcon } from './OperationIcon';
import { operationHistoryLabel } from '../operationHistory';
import { DirectionalWhirl } from './DirectionalWhirl';
import { attestationPresentation } from '../directionalPresentation';
import { metaForRegister } from '../registers';
import type { GraphField, Observables, Proposition } from '../types';

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
        <PropositionMetadata proposition={proposition} />
        <Group gap="xs" justify="space-between"><FieldValues field={proposition.field} observables={proposition.observables} committalObservables={committalObservables} tier={proposition.tier} compact /><AttestationCounts proposition={proposition} /></Group>
      </Stack>
    );
  }

  return (
    <Stack gap={detailed ? 'md' : 'xs'}>
      <Group justify="space-between" align="flex-start" wrap="nowrap">
        <Stack gap={5} miw={0}>
          <PropositionMetadata proposition={proposition} wireframe={wireframe} />
          <Text fw={detailed ? 750 : 650} size={detailed ? 'lg' : undefined} lineClamp={detailed ? undefined : 2}>{title}</Text>
        </Stack>
        {proposition.participant && (
          <Stack gap={3} align="center" className="proposition-card__participant">
            <ParticipantAvatar size={detailed ? 36 : 30} callsign={callsign} />
            <Text size="10px" c="dimmed" maw={80} truncate>{callsign}</Text>
          </Stack>
        )}
      </Group>

      {detailed && <TransitionReadout transition={proposition.transition} />}

      {wireframe && ActionIcon && <ThemeIcon color={register.color} variant="light" size="sm"><ActionIcon size={13} /></ThemeIcon>}
      {!wireframe && proposition.tier === 'committal' && <Tooltip label="Committed structure"><ThemeIcon color="indigo" variant="light" size="sm"><IconBolt size={13} /></ThemeIcon></Tooltip>}
      {detailed && (description || proposition.statement) && (
        <Text size={detailed ? 'sm' : 'xs'} c="dimmed" lineClamp={detailed ? undefined : 3} style={{ whiteSpace: 'pre-line' }}>
          {detailed ? proposition.statement : description}
        </Text>
      )}

      {!wireframe && (
        <Box className={detailed ? 'proposition-content__measurements' : undefined}>
          {detailed && <Text fw={700} size="sm" mb="sm">Field state</Text>}
          <Group gap="xs" justify="space-between" align="center">
          <FieldValues
            field={projectedObservables ? undefined : proposition.field}
            observables={projectedObservables || proposition.observables}
            committalObservables={committalObservables}
            tier={proposition.tier}
            compact={!detailed}
          />
          <AttestationCounts proposition={proposition} />
          </Group>
        </Box>
      )}
    </Stack>
  );
}

export function PropositionMetadata({ proposition, wireframe = false }: { proposition: Partial<Proposition>; wireframe?: boolean }) {
  const register = metaForRegister(proposition.register);
  return <Group gap={5} wrap="wrap" className="proposition-metadata">
    {(proposition.tier || wireframe) && <TierBadge tier={wireframe ? 'possible' : proposition.tier} />}
    {proposition.register && <Badge size="xs" color={register.color} variant="light">{register.label}</Badge>}
    {proposition.exhaustion?.exhausted && <Badge size="xs" color="gray" variant="outline">Exhausted</Badge>}
    {proposition.exhaustion?.basin_exhausted && <Badge size="xs" color="gray" variant="outline">Basin exhausted</Badge>}
    {proposition.active === false && !proposition.exhaustion?.exhausted && !proposition.exhaustion?.basin_exhausted
      && <Badge size="xs" color="gray" variant="outline">Legacy hold</Badge>}
    {proposition.transition?.type && <Badge size="xs" color="gray" variant="outline" className="operation-badge"
      leftSection={<OperationIcon operation={proposition.transition.type} size={11} />}>{operationHistoryLabel(proposition.transition.type)}</Badge>}
  </Group>;
}

export function AttestationCounts({ proposition }: { proposition: Partial<Proposition> }) {
  if (!proposition.attestation_counts && !proposition.attestation_summary) return null;
  const { slots, dominant, direction, label, animate } = attestationPresentation(proposition);
  const mapped = Boolean(proposition.attestation_summary?.support_direction);
  const [negative, positive] = slots;
  const description = dominant ? `${dominant === 'positive' ? 'Positive' : 'Negative'} count majority (unweighted)`
    : direction ? `No count majority · ${direction} direction persists${label ? ' (' + label + ')' : ''}` : 'No count majority';
  function count(slot: typeof negative) {
    return <Tooltip label={`${slot.count} effective ${mapped ? slot.direction + '-direction' : slot.text} Attestations${mapped ? ' · ' + slot.text + ' in this region' : ''}`} withArrow>
      <Text component="span" className="attestation-semantic" data-label={slot.label} size="xs" fw={700}
        aria-label={`${slot.label}: ${slot.count}`}>
        {mapped ? slot.side === 'left' ? '−' : '+' : slot.text + ' '}{slot.count}
      </Text>
    </Tooltip>;
  }
  return <Group gap={5} wrap="nowrap" className="proposition-attestation-counts" ml="auto" aria-label="Effective Attestations">
    {count(negative)}
    <Tooltip label={description} withArrow>
      <span className="attestation-semantic" data-label={label || 'neutral'} role="img" aria-label={description}><DirectionalWhirl direction={direction} animate={animate} size={16} /></span>
    </Tooltip>
    {count(positive)}
  </Group>;
}

function FieldValues({ field, observables, committalObservables, tier, compact = false }: {
  field?: GraphField;
  observables?: Observables;
  committalObservables?: Observables;
  tier?: string;
  compact?: boolean;
}) {
  const expressive = tier === 'expressive';
  const state = field ? (field.tension === null || field.charge === null ? null : { tension: field.tension, charge: field.charge }) : fieldState(observables);
  const committed = fieldState(committalObservables);

  if (!state && !field) return <Text size="xs" c="dimmed">Field awaiting complete measurement</Text>;

  const values = [
    {
      key: 'tension',
      label: 'Tension',
      icon: IconLayersDifference,
      value: state?.tension ?? null,
      delta: expressive && committed && state ? state.tension - committed.tension : null,
      signed: true,
    },
    {
      key: 'charge',
      label: expressive ? 'Expressive Charge' : 'Charge',
      icon: IconHexagon,
      value: state?.charge ?? null,
      delta: expressive && committed && state ? state.charge - committed.charge : null,
    },
    ...(expressive || field ? [{
      key: 'potential-charge',
      label: field?.potential_basis === 'region' ? 'Region Potential Charge' : 'Potential Charge',
      icon: IconHexagonalPrism,
      value: field ? field.potential_charge : committed && state ? Math.max(0, state.charge - committed.charge) : null,
      delta: null,
    }] : []),
  ];
  const items = values.map(({ key, ...item }) => (
    <FieldValue key={key} {...item} tier={tier} compact={compact}
      stale={(key === 'potential-charge' ? field?.potential_status : field?.status) === 'stale'} />
  ));

  return compact
    ? <Group className="proposition-field" data-tier={tier} gap="sm" wrap="wrap">{items}</Group>
    : <SimpleGrid className="proposition-field" data-tier={tier} cols={{ base: 1, xs: values.length }} spacing="xs">{items}</SimpleGrid>;
}

function FieldValue({ label, icon: Icon, value, delta, signed: signedValue = false, tier, compact, stale = false }: {
  label: string;
  icon: React.ElementType;
  value: number | null;
  delta: number | null;
  signed?: boolean;
  tier?: string;
  compact?: boolean;
  stale?: boolean;
}) {
  const tensionPalette = useContext(GraphTensionPalette);
  const sign = value === null || value === 0 ? 'neutral' : value < 0 ? 'negative' : 'positive';
  const color = 'var(--field-color)';
  const readout = value === null ? (compact ? '—' : 'Awaiting measurement') : number(value, signedValue);
  const deltaReadout = delta === null ? null : `Δ ${number(delta, true)} from Committal`;
  const description = `${label}: ${readout}${stale ? ' · last measured' : ''}${deltaReadout ? ` · ${deltaReadout}` : ''}`;
  const content = (
    <Box className="field-value" data-sign={sign} data-directional={signedValue || undefined} data-tension-palette={signedValue ? tensionPalette : undefined} data-compact={compact || undefined} aria-label={description}>
      <Group gap={5} wrap="nowrap">
        <ThemeIcon color={color} variant={compact ? 'transparent' : 'light'} size={compact ? 22 : 28}>
          <Icon size={compact ? 15 : 17} />
        </ThemeIcon>
        {!compact && <Text size="xs" c="dimmed" truncate>{label}</Text>}
        <Text size="xs" fw={750} c={color} style={{ whiteSpace: 'nowrap' }}>{readout}</Text>
      </Group>
      {!compact && deltaReadout && <Text size="10px" c="dimmed" mt={3}>{deltaReadout}</Text>}
      {!compact && stale && <Text size="10px" c="dimmed">Last measured</Text>}
    </Box>
  );

  return compact
    ? <Tooltip label={description} withArrow>{content}</Tooltip>
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
      <Badge size="xs" variant="outline" color="gray" className="operation-badge">{operationHistoryLabel(transition.type)}</Badge>
      <Text size={compact ? '9px' : 'xs'} c="dimmed">{label(transition.from)}</Text>
      <IconArrowRight size={compact ? 11 : 13} />
      <Text size={compact ? '9px' : 'xs'} fw={650}>{label(transition.to)}</Text>
    </Group>
  );
}

function TierBadge({ tier }: { tier?: string }) {
  if (tier === 'committal') return <Badge size="xs" color="indigo" variant="filled">Committal</Badge>;
  return <Badge size="xs" color="gray" variant="light">{tier}</Badge>;
}

function label(value?: string) {
  const text = String(value || '');
  return text ? text.charAt(0).toUpperCase() + text.slice(1) : 'Unknown';
}
