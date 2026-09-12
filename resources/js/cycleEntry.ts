import type { Participation } from './types';

/** Entry availability and recovery choice are entirely server-owned. */
export function cycleEntryPresentation(participation?: Participation) {
  const entry = participation?.entry;
  const mode = participation?.status === 'waiting' && entry?.available ? entry.mode : undefined;
  return {
    mode,
    available: mode === 'origin' || mode === 'potential',
    label: mode === 'origin' ? 'Establish the Origin' : entry?.selection === 'recovery' ? 'Begin recovery Cycle' : 'Enter Cycle',
    message: entry?.message || participation?.reason?.message || (participation?.status === 'ready' ? 'This Participant already has an active Cycle.' : 'Cycle entry is not currently available.'),
  };
}
