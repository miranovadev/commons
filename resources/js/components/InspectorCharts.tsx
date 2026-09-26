import { Box, Group, Stack, Text } from '@mantine/core';
import { IconArrowDown, IconArrowUp, IconLayersIntersect, IconWaveSine } from '@tabler/icons-react';
import { useId } from 'react';
import type { ClosureHistoryPoint, Proposition } from '../types';

const finite = (value: unknown): number | null => value === null || value === undefined || value === '' || !Number.isFinite(Number(value)) ? null : Number(value);
const display = (value: number | null) => value === null ? 'Unavailable' : `${value > 0 ? '+' : ''}${value.toFixed(2)}`;
const position = (value: number) => 10 + (Math.max(-1, Math.min(1, value)) + 1) * 140;

export function FieldStateChart({ proposition }: { proposition: Proposition }) {
  const gradient = useId();
  const tension = finite(proposition.field?.tension);
  return <Stack gap="md">
    <Text size="xs" c="dimmed">Observed Measurements · −1 to +1</Text>
    {(['correspondence', 'chirality', 'closure'] as const).map(key => {
      const value = finite(proposition.observables?.[key]);
      const label = key[0].toUpperCase() + key.slice(1);
      return <Box key={key}>
        <Group justify="space-between"><Text size="sm">{label}</Text><Text size="sm" fw={650}>{display(value)}</Text></Group>
        <svg viewBox="0 0 300 22" className="inspector-chart" role="img" aria-label={`${label}: ${display(value)}`}>
          <line x1="10" x2="290" y1="11" y2="11" stroke="var(--mantine-color-default-border)" strokeWidth="5" strokeLinecap="round" />
          <line x1="150" x2="150" y1="3" y2="19" stroke="var(--mantine-color-dimmed)" />
          {value !== null && <><line x1="150" x2={position(value)} y1="11" y2="11" stroke="var(--mantine-color-indigo-6)" strokeWidth="5" />
            <circle cx={position(value)} cy="11" r="5" fill="var(--mantine-color-indigo-6)" /></>}
        </svg>
      </Box>;
    })}
    <Box>
      <Group justify="space-between"><Text fw={700} size="sm">Tension</Text><Text fw={700} size="sm">{display(tension)}</Text></Group>
      <svg viewBox="0 0 300 42" className="inspector-chart" role="img" aria-label={`Tension: ${display(tension)}; Settlement −1, Balanced 0, Emergence +1`}>
        <defs><linearGradient id={gradient}><stop offset="0" stopColor="var(--mantine-color-indigo-9)" /><stop offset=".5" stopColor="var(--mantine-color-indigo-6)" /><stop offset="1" stopColor="var(--mantine-color-indigo-3)" /></linearGradient></defs>
        <rect x="10" y="14" width="280" height="12" rx="6" fill={`url(#${gradient})`} />
        <line x1="150" x2="150" y1="12" y2="28" stroke="var(--mantine-color-body)" strokeWidth="2" />
        {tension !== null && <path d={`M ${position(tension) - 5} 4 L ${position(tension) + 5} 4 L ${position(tension)} 12 Z`} fill="var(--mantine-color-text)" />}
      </svg>
      <Group justify="space-between" gap={3}><Text size="10px">Settlement</Text><Text size="10px">Balanced</Text><Text size="10px">Emergence</Text></Group>
    </Box>
    <Group justify="space-between">
      <Text size="xs" aria-label={`Charge: ${display(finite(proposition.field?.charge))}`}>Charge {display(finite(proposition.field?.charge))}</Text>
      <Text size="xs" aria-label={`Potential Charge: ${display(finite(proposition.field?.potential_charge))}`}>Potential Charge {display(finite(proposition.field?.potential_charge))}</Text>
    </Group>
    {(proposition.observables?.status === 'stale' || proposition.field?.status === 'stale') && <Text size="xs" c="dimmed">Last measured values · awaiting refresh</Text>}
  </Stack>;
}

export function closureDirectionColor(point?: ClosureHistoryPoint) {
  if (!point?.depth_direction || !point.cadence_direction) return 'gray.6';
  if (point.depth_direction !== point.cadence_direction) return 'gray.6';
  return point.depth_direction === 'positive' ? 'indigo.4' : 'indigo.9';
}

export function ClosureHistoryChart({ proposition }: { proposition: Proposition }) {
  const history = proposition.closure_history;
  const points = history?.points || [];
  const current = history?.current ?? undefined;
  const color = closureDirectionColor(current);
  return <Stack gap="md">
    <Text size="xs" c="dimmed">Committed lineage, oldest to newest. Spacing shows structural order.</Text>
    {!points.length && <Text size="sm" c="dimmed">Committed Closure history is unavailable for this view.</Text>}
    {points.length > 0 && (['depth', 'cadence'] as const).map(key => <Box key={key}>
      <Text size="sm" fw={650}>{key === 'depth' ? 'Depth' : 'Cadence'}</Text>
      <svg viewBox="0 0 300 76" className="inspector-chart" role="img" aria-label={`${key === 'depth' ? 'Depth' : 'Cadence'} history: ${points.map(p => display(finite(p[key]))).join(', ')}`}>
        <line x1="10" x2="290" y1="38" y2="38" stroke="var(--mantine-color-default-border)" strokeDasharray="3 3" />
        {points.map((point, index) => {
          const value = finite(point[key]);
          if (value === null) return null;
          const x = points.length === 1 ? 150 : 10 + index * 280 / (points.length - 1);
          const y = 38 - value * 28;
          const previous = index > 0 ? finite(points[index - 1][key]) : null;
          return <g key={point.proposition_id}>
            {previous !== null && <line x1={10 + (index - 1) * 280 / (points.length - 1)} y1={38 - previous * 28} x2={x} y2={y} stroke="var(--mantine-color-indigo-5)" strokeWidth="2" />}
            <circle cx={x} cy={y} r="3" fill="var(--mantine-color-indigo-5)"><title>{`Committal ${index + 1}: ${display(value)}`}</title></circle>
          </g>;
        })}
        <text x="10" y="73" fill="var(--mantine-color-dimmed)" fontSize="9">Earlier</text><text x="290" y="73" textAnchor="end" fill="var(--mantine-color-dimmed)" fontSize="9">Latest</text>
      </svg>
    </Box>)}
    {current && <>
      <Group grow>{(['depth', 'cadence'] as const).map(key => {
        const direction = current[`${key}_direction`];
        const Icon = key === 'depth' ? IconLayersIntersect : IconWaveSine;
        const Arrow = direction === 'positive' ? IconArrowUp : IconArrowDown;
        const label = key === 'depth' ? 'Depth' : 'Cadence';
        return <Box key={key} className="closure-direction" data-color={color} data-direction={direction ?? undefined}>
          <Text size="xs">{label}</Text><Group gap={6} aria-label={`${label}: ${direction || 'unavailable'} direction`}>
            <span className="closure-direction__icons" style={{ color: `var(--mantine-color-${color.replace('.', '-')})` }}><Icon size={22} />{direction && <Arrow size={20} />}</span>
            <Text size="sm" fw={650}>{display(finite(current[key]))}</Text>
          </Group>
        </Box>;
      })}</Group>
      <Text size="xs" c="dimmed">{!current.depth_direction || !current.cadence_direction ? 'Historical direction unavailable.' : current.depth_direction === current.cadence_direction
        ? 'Directions aligned. This alone does not establish Commit eligibility.' : 'Opposite directions · inadmissible Closure orientation for Commit.'}</Text>
    </>}
  </Stack>;
}
