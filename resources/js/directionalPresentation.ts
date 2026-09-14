import type { AttestationDirection, Proposition } from './types';

import { defaultPresentation, type SubstratePresentation } from './substratePresentation';

export type RotationDirection = 'positive' | 'negative';
export function opposite(direction: RotationDirection): RotationDirection {
  return direction === 'positive' ? 'negative' : 'positive';
}

/** Only the server's immutable Commit Region frame assigns labels to signs. */
export function directionSlots(positiveDirection?: RotationDirection | null, presentation: SubstratePresentation = defaultPresentation) {
  return (['negative', 'positive'] as const).map((sign, index) => {
    const label: AttestationDirection = positiveDirection ? (positiveDirection === sign ? 'positive' : 'negative') : (index === 0 ? 'negative' : 'positive');
    return { side: index === 0 ? 'left' as const : 'right' as const, direction: positiveDirection ? sign : null,
      label, text: presentation[label].label, color: presentation[label].color };
  });
}

export function attestationPresentation(proposition: Partial<Proposition>, presentation: SubstratePresentation = defaultPresentation) {
  const summary = proposition.attestation_summary;
  const slots = directionSlots(summary?.positive_direction, presentation).map(slot => ({ ...slot,
    count: summary && slot.direction ? summary[slot.direction] : proposition.attestation_counts?.[slot.label] ?? 0,
  }));
  const dominant = !summary || summary.positive === summary.negative ? null
    : summary.positive > summary.negative ? 'positive' as const : 'negative' as const;
  const direction = dominant ?? summary?.retained_direction ?? null;
  const label: AttestationDirection | undefined = direction && summary?.positive_direction ? (direction === summary.positive_direction ? 'positive' : 'negative') : undefined;
  return { slots, dominant, direction, label, animate: dominant !== null };
}
