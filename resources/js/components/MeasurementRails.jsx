import { Box, Group, Stack, Text } from '@mantine/core';

export const measurementMeta = {
  correspondence: { label: 'Correspondence', negative: 'Dissonance', positive: 'Consonance' },
  chirality: { label: 'Chirality', negative: 'Divergence', positive: 'Alignment' },
  closure: { label: 'Closure', negative: 'Variability', positive: 'Persistence' },
};

export function MeasurementRails({ observables, projectedObservables, compact = false }) {
  return (
    <Stack gap={compact ? 7 : 'sm'}>
      {Object.entries(measurementMeta).map(([key, meta]) => (
        <MeasurementRail
          key={key}
          name={key}
          value={observables?.[key]}
          projectedValue={projectedObservables?.[key]}
          compact={compact}
          {...meta}
        />
      ))}
    </Stack>
  );
}

export function MeasurementRail({ name, label, negative, positive, value, projectedValue, compact = false }) {
  const hasValue = typeof value === 'number';
  const hasProjected = typeof projectedValue === 'number';
  const current = hasValue ? position(value) : 50;
  const projected = hasProjected ? position(projectedValue) : null;
  const delta = hasValue && hasProjected ? projectedValue - value : null;

  return (
    <Box className="measurement-rail" data-measurement={name} data-compact={compact || undefined}>
      {!compact && (
        <Group justify="space-between" gap="xs" mb={5} wrap="nowrap">
          <Text size="xs" fw={700}>{label}</Text>
          <Text size="xs" c="dimmed">
            {hasValue ? signed(value) : 'Awaiting measurement'}
            {hasProjected ? ` → ${signed(projectedValue)}` : ''}
            {delta !== null ? `  Δ ${signed(delta)}` : ''}
          </Text>
        </Group>
      )}
      <Group gap={8} wrap="nowrap">
        <Text className="measurement-rail__pole" size="10px" c="dimmed">{negative}</Text>
        <Box className="measurement-rail__track" data-empty={!hasValue || undefined}>
          {hasValue && <span className="measurement-rail__marker measurement-rail__marker--current" style={{ left: `${current}%` }} />}
          {hasValue && hasProjected && (
            <span
              className="measurement-rail__delta"
              style={{ left: `${Math.min(current, projected)}%`, width: `${Math.abs(projected - current)}%` }}
            />
          )}
          {hasProjected && <span className="measurement-rail__marker measurement-rail__marker--projected" style={{ left: `${projected}%` }} />}
        </Box>
        <Text className="measurement-rail__pole measurement-rail__pole--right" size="10px" c="dimmed">{positive}</Text>
      </Group>
    </Box>
  );
}

export function structuralDeltaSummary(observables, projectedObservables) {
  if (!observables || !projectedObservables) return [];

  return Object.entries(measurementMeta).flatMap(([key, meta]) => {
    const current = observables[key];
    const projected = projectedObservables[key];
    if (typeof current !== 'number' || typeof projected !== 'number') return [];

    const delta = projected - current;
    if (Math.abs(delta) < 0.005) return [`Hold ${meta.label}`];
    const qualifier = Math.abs(delta) < 0.15 ? 'Slightly ' : '';
    return [`${qualifier}${delta > 0 ? 'increase' : 'decrease'} ${meta.label}`];
  });
}

function position(value) {
  return Math.max(0, Math.min(100, ((value + 1) / 2) * 100));
}

function signed(value) {
  return `${value >= 0 ? '+' : ''}${value.toFixed(2)}`;
}
