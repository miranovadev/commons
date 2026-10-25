import { useEffect, useState } from 'react';
import type { Participation } from './types';

/** A projection refresh is not a lifecycle transition or a draft invalidation. */
export function useBackgroundParticipation(current?: Participation) {
  const [settled, setSettled] = useState<Participation>();
  const synchronising = current?.reason?.code === 'potential_projection_pending';
  useEffect(() => {
    if (current && !synchronising) setSettled(current);
  }, [current, synchronising]);
  return { participation: synchronising ? settled ?? current : current, synchronising };
}
