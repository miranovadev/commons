import { IconWhirl } from '@tabler/icons-react';
import type { RotationDirection } from '../directionalPresentation';

/** Mirror geometry inside a separate rotating wrapper so transforms never cancel. */
export function DirectionalWhirl({ direction, size = 16, animate = true }: {
  direction?: RotationDirection | null; size?: number; animate?: boolean;
}) {
  return <span className="directional-whirl" data-direction={direction || 'neutral'}
    data-animated={Boolean(direction && animate)} aria-hidden="true" style={{ width: size, height: size }}>
    <IconWhirl size={size} className="directional-whirl__geometry" />
  </span>;
}
