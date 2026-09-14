import { Box, Group, Stack, Text, Tooltip } from '@mantine/core';
import type { Observables } from '../types';

type MeasurementName = keyof Observables;

export const measurementMeta = {
  correspondence: { label: 'Correspondence', negative: 'Dissonance', positive: 'Consonance' },
  chirality: { label: 'Chirality', negative: 'Divergence', positive: 'Alignment' },
  closure: { label: 'Closure', negative: 'Variability', positive: 'Persistence' },
};

interface MeasurementRailsProps {
  observables?: Observables;
  projectedObservables?: Observables;
  compact?: boolean;
  bare?: boolean;
}

export function MeasurementRails({ observables, projectedObservables, compact = false, bare = false }: MeasurementRailsProps) {
  return (
    <Stack gap={bare ? 5 : compact ? 7 : 'sm'}>
      {(Object.entries(measurementMeta) as Array<[MeasurementName, (typeof measurementMeta)[MeasurementName]]>).map(([key, meta]) => (
        <MeasurementRail
          key={key}
          name={key}
          value={observables?.[key]}
          projectedValue={projectedObservables?.[key]}
          compact={compact}
          bare={bare}
          {...meta}
        />
      ))}
    </Stack>
  );
}

export function MeasurementRail({
  name,
  label,
  negative,
  positive,
  value,
  projectedValue,
  compact = false,
  bare = false,
}: {
  name: MeasurementName;
  label: string;
  negative: string;
  positive: string;
  value?: number | string | null;
  projectedValue?: number | string | null;
  compact?: boolean;
  bare?: boolean;
}) {
  const currentValue = measurement(value);
  const projectedMeasurement = measurement(projectedValue);
  const hasValue = currentValue !== null;
  const hasProjected = projectedMeasurement !== null;
  const current = hasValue ? position(currentValue) : 50;
  const projected = hasProjected ? position(projectedMeasurement) : null;
  const delta = hasValue && hasProjected ? projectedMeasurement - currentValue : null;

  const track = (
    <Box
      className="measurement-rail__track"
      data-empty={!hasValue || undefined}
      aria-label={`${label}: ${hasValue ? signed(currentValue) : 'awaiting measurement'}`}
      style={bare ? { height: 2 } : undefined}
    >
      {hasValue && (
        <span
          className="measurement-rail__value"
          style={{
            left: `${Math.min(50, current)}%`,
            width: `${Math.abs(current - 50)}%`,
            position: 'absolute',
            top: 0,
            height: '100%',
            borderRadius: 99,
            background: 'var(--register-color, var(--mantine-color-primary-5))',
            opacity: 0.58,
          }}
        />
      )}
      {hasValue && (
        <span
          className="measurement-rail__marker measurement-rail__marker--current"
          style={{ left: `${current}%`, ...(bare ? { width: 7, height: 7 } : {}) }}
        />
      )}
      {hasValue && hasProjected && (
        <span
          className="measurement-rail__delta"
          style={{ left: `${Math.min(current, projected!)}%`, width: `${Math.abs(projected! - current)}%` }}
        />
      )}
      {hasProjected && (
        <span
          className="measurement-rail__marker measurement-rail__marker--projected"
          style={{ left: `${projected}%`, ...(bare ? { width: 7, height: 7 } : {}) }}
        />
      )}
    </Box>
  );

  return (
    <Box
      className="measurement-rail"
      data-measurement={name}
      data-compact={compact || undefined}
      data-bare={bare || undefined}
    >
      {!compact && !bare && (
        <Group justify="space-between" gap="xs" mb={5} wrap="nowrap">
          <Text size="xs" fw={700}>{label}</Text>
          <Text size="xs" c="dimmed">
            {hasValue ? signed(currentValue) : 'Awaiting measurement'}
            {hasProjected ? ` → ${signed(projectedMeasurement)}` : ''}
            {delta !== null ? `  Δ ${signed(delta)}` : ''}
          </Text>
        </Group>
      )}
      <Group gap={bare ? 0 : 8} wrap="nowrap">
        {!bare && <Text className="measurement-rail__pole" size="10px" c="dimmed">{negative}</Text>}
        {bare ? (
          <Tooltip
            label={`${label}: ${hasValue ? signed(currentValue) : 'Awaiting measurement'}`}
            position="right"
            withArrow
          >
            {track}
          </Tooltip>
        ) : track}
        {!bare && <Text className="measurement-rail__pole measurement-rail__pole--right" size="10px" c="dimmed">{positive}</Text>}
      </Group>
    </Box>
  );
}

export function structuralDeltaSummary(observables?: Observables, projectedObservables?: Observables): string[] {
  if (!observables || !projectedObservables) return [];

  return Object.entries(measurementMeta).flatMap(([key, meta]) => {
    const name = key as MeasurementName;
    const current = measurement(observables[name]);
    const projected = measurement(projectedObservables[name]);
    if (current === null || projected === null) return [];

    const delta = projected - current;
    if (Math.abs(delta) < 0.005) return [`Hold ${meta.label}`];
    const qualifier = Math.abs(delta) < 0.15 ? 'Slightly ' : '';
    return [`${qualifier}${delta > 0 ? 'increase' : 'decrease'} ${meta.label}`];
  });
}

function measurement(value: unknown): number | null {
  if (value === null || value === undefined || value === '') return null;

  const numeric = typeof value === 'number' ? value : Number(value);
  if (!Number.isFinite(numeric)) return null;

  return Math.max(-1, Math.min(1, numeric));
}

function position(value: number) {
  return ((value + 1) / 2) * 100;
}

function signed(value: number) {
  return `${value >= 0 ? '+' : ''}${value.toFixed(2)}`;
}
