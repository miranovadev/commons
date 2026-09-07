import { Group, SegmentedControl, Text, Tooltip } from '@mantine/core';
import { IconWhirl } from '@tabler/icons-react';

export function AttestationDirectionControl({ value, onChange, showLabels = true, fullWidth = true }) {
  return (
    <SegmentedControl
      fullWidth={fullWidth}
      color={value === 'support' ? 'violet' : 'teal'}
      data={['support', 'refute'].map((direction) => ({
        value: direction,
        label: <DirectionLabel direction={direction} showLabel={showLabels} />,
      }))}
      value={value || 'support'}
      onChange={onChange}
      aria-label="Attestation direction"
    />
  );
}

function DirectionLabel({ direction, showLabel }) {
  const label = direction === 'support' ? 'Support' : 'Refute';

  return (
    <Tooltip label={label} disabled={showLabel} withArrow>
      <Group gap={6} justify="center" wrap="nowrap" aria-label={label}>
        <IconWhirl
          size={15}
          className={`attestation-direction-icon attestation-direction-icon--${direction}`}
        />
        {showLabel && <Text component="span" size="sm">{label}</Text>}
      </Group>
    </Tooltip>
  );
}
