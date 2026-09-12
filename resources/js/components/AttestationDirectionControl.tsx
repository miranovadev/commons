import { Group, SegmentedControl, Text } from '@mantine/core';
import { attestationColors, directionSlots, type RotationDirection } from '../directionalPresentation';
import type { AttestationDirection } from '../types';
import { DirectionalWhirl } from './DirectionalWhirl';

interface AttestationDirectionControlProps {
  value: AttestationDirection;
  onChange: (direction: AttestationDirection) => void;
  showLabels?: boolean;
  fullWidth?: boolean;
  directions?: AttestationDirection[];
  supportDirection?: RotationDirection | null;
  initialLabelChoice?: boolean;
}

export function AttestationDirectionControl({ value, onChange, showLabels = true, fullWidth = true,
  directions = ['support', 'refute'], supportDirection, initialLabelChoice = false }: AttestationDirectionControlProps) {
  const slots = directionSlots(supportDirection).filter(slot => directions.includes(slot.label));
  return <SegmentedControl fullWidth={fullWidth} color={attestationColors[value]}
    data={slots.map(slot => ({ value: slot.label, label: <Group gap={6} justify="center" wrap="nowrap" aria-label={slot.text}>
      <DirectionalWhirl direction={initialLabelChoice ? 'positive' : slot.direction} size={15} />
      {showLabels && <Text component="span" size="sm">{slot.text}</Text>}
    </Group> }))} value={value} onChange={direction => onChange(direction as AttestationDirection)} aria-label="Attestation direction" />;
}
