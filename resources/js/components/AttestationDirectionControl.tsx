import { useSubstratePresentation } from '../SubstrateTheme';
import { Group, SegmentedControl, Text } from '@mantine/core';
import { directionSlots, type RotationDirection } from '../directionalPresentation';
import type { AttestationDirection } from '../types';
import { DirectionalWhirl } from './DirectionalWhirl';

interface AttestationDirectionControlProps {
  value: AttestationDirection;
  onChange: (direction: AttestationDirection) => void;
  showLabels?: boolean;
  fullWidth?: boolean;
  directions?: AttestationDirection[];
  positiveDirection?: RotationDirection | null;
  initialLabelChoice?: boolean;
}

export function AttestationDirectionControl({ value, onChange, showLabels = true, fullWidth = true,
  directions = ['positive', 'negative'], positiveDirection, initialLabelChoice = false }: AttestationDirectionControlProps) {
  const presentation = useSubstratePresentation();
  const slots = directionSlots(positiveDirection, presentation).filter(slot => directions.includes(slot.label));
  return <SegmentedControl fullWidth={fullWidth} color={presentation[value].color}
    data={slots.map(slot => ({ value: slot.label, label: <Group gap={6} justify="center" wrap="nowrap" aria-label={slot.text}>
      <DirectionalWhirl direction={initialLabelChoice ? 'positive' : slot.direction} size={15} />
      {showLabels && <Text component="span" size="sm">{slot.text}</Text>}
    </Group> }))} value={value} onChange={direction => onChange(direction as AttestationDirection)} aria-label="Attestation direction" />;
}
