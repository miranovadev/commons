import type { AttestationDirection, Proposition } from './types';

export type RotationDirection = 'positive' | 'negative';
export const attestationLabels = { support: 'Support', refute: 'Refute' } as const;
export const attestationColors = { support: 'violet', refute: 'teal' } as const;
export function opposite(direction: RotationDirection): RotationDirection {
  return direction === 'positive' ? 'negative' : 'positive';
}

/** Only the server's immutable Commit Region frame assigns labels to signs. */
export function directionSlots(support?: RotationDirection | null) {
  return (['negative', 'positive'] as const).map((sign, index) => {
    const label: AttestationDirection = support ? (support === sign ? 'support' : 'refute') : (index === 0 ? 'refute' : 'support');
    return { side: index === 0 ? 'left' as const : 'right' as const, direction: support ? sign : null,
      label, text: attestationLabels[label], color: attestationColors[label] };
  });
}

export function attestationPresentation(proposition: Partial<Proposition>) {
  const summary = proposition.attestation_summary;
  const slots = directionSlots(summary?.support_direction).map(slot => ({ ...slot,
    count: summary && slot.direction ? summary[slot.direction] : proposition.attestation_counts?.[slot.label] ?? 0,
  }));
  const dominant = !summary || summary.positive === summary.negative ? null
    : summary.positive > summary.negative ? 'positive' as const : 'negative' as const;
  return { slots, dominant, dominantLabel: slots.find(slot => dominant && slot.direction === dominant)?.label };
}
