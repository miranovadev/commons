import { Box, Group, Stack, Text, Tooltip } from '@mantine/core';

export const measurementMeta = {
  correspondence: { label: 'Correspondence', negative: 'Dissonance', positive: 'Consonance' },
  chirality: { label: 'Chirality', negative: 'Divergence', positive: 'Alignment' },
  closure: { label: 'Closure', negative: 'Variability', positive: 'Persistence' },
};

export function MeasurementRails({ observables, projectedObservables, compact = false, bare = false, impact = false }) {
  return (
    <Stack gap={bare ? 5 : compact ? 7 : 'sm'}>
      {Object.entries(measurementMeta).map(([key, meta]) => (
        <MeasurementRail
          key={key}
          name={key}
          value={observables?.[key]}
          projectedValue={projectedObservables?.[key]}
          compact={compact}
          bare={bare}
          impact={impact}
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
  impact = false,
}) {
  const currentValue = measurement(value);
  const projectedMeasurement = measurement(projectedValue);
  const hasValue = currentValue !== null;
  const hasProjected = projectedMeasurement !== null;
  const current = hasValue ? position(currentValue) : 50;
  const projected = hasProjected ? position(projectedMeasurement) : null;
  const delta = hasValue && hasProjected ? projectedMeasurement - currentValue : null;
  const direction = delta === null || Math.abs(delta) < 0.005 ? 'steady' : delta > 0 ? 'prominence' : 'turbulence';
  const readout = impact && hasProjected
    ? `From ${hasValue ? signed(currentValue) : 'unmeasured'} to ${signed(projectedMeasurement)}`
    : `${hasValue ? signed(currentValue) : 'awaiting measurement'}${hasProjected ? ` to ${signed(projectedMeasurement)}` : ''}`;

  const track = (
    <Box
      className="measurement-rail__track"
      data-empty={!hasValue || undefined}
      aria-label={`${label}: ${readout}`}
      data-direction={direction}
      style={bare ? { height: 2 } : undefined}
    >
      {hasValue && !impact && (
        <span
          className="measurement-rail__value"
          style={{
            left: `${Math.min(50, current)}%`,
            width: `${Math.abs(current - 50)}%`,
            position: 'absolute',
            top: 0,
            height: '100%',
            borderRadius: 99,
            background: 'var(--register-color, var(--mantine-color-indigo-5))',
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
          style={{ left: `${Math.min(current, projected)}%`, width: `${Math.abs(projected - current)}%` }}
        />
      )}
      {hasProjected && (
        <span
          className="measurement-rail__marker measurement-rail__marker--projected"
          data-direction={direction}
          style={{ left: `${projected}%`, ...(bare && direction === 'steady' ? { width: 7, height: 7 } : {}) }}
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
            {impact && hasProjected ? `From ${hasValue ? signed(currentValue) : '—'} → To ${signed(projectedMeasurement)}` : hasValue ? signed(currentValue) : 'Awaiting measurement'}
            {!impact && hasProjected ? ` → ${signed(projectedMeasurement)}` : ''}
            {delta !== null ? `  Δ ${signed(delta)}` : ''}
          </Text>
        </Group>
      )}
      <Group gap={bare ? 0 : 8} wrap="nowrap">
        {!bare && <Text className="measurement-rail__pole" size="10px" c="dimmed">{negative}</Text>}
        {bare ? (
          <Tooltip
            label={`${label}: ${readout}${impact && direction !== 'steady' ? ` · toward ${direction === 'prominence' ? 'Prominence' : 'Turbulence'}` : ''}`}
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

export function structuralDeltaSummary(observables, projectedObservables) {
  if (!observables || !projectedObservables) return [];

  return Object.entries(measurementMeta).flatMap(([key, meta]) => {
    const current = measurement(observables[key]);
    const projected = measurement(projectedObservables[key]);
    if (current === null || projected === null) return [];

    const delta = projected - current;
    if (Math.abs(delta) < 0.005) return [`Hold ${meta.label}`];
    const qualifier = Math.abs(delta) < 0.15 ? 'Slightly ' : '';
    return [`${qualifier}${delta > 0 ? 'increase' : 'decrease'} ${meta.label}`];
  });
}

function measurement(value) {
  if (value === null || value === undefined || value === '') return null;

  const numeric = typeof value === 'number' ? value : Number(value);
  if (!Number.isFinite(numeric)) return null;

  return Math.max(-1, Math.min(1, numeric));
}

function position(value) {
  return ((value + 1) / 2) * 100;
}

function signed(value) {
  return `${value >= 0 ? '+' : ''}${value.toFixed(2)}`;
}
