import { IconAdjustmentsHorizontal, IconArrowsJoin, IconArrowsSplit, IconFilter, IconFocus2, IconLock, IconRepeat } from '@tabler/icons-react';

/** Participant-facing captions from config/phases.php and docs/participation.md. */
export const phaseMeta = {
  distinguish: { number: 1, icon: IconFocus2, description: 'Open a new distinction or follow an existing path from your commitment.' },
  persist: { number: 2, icon: IconRepeat, description: 'Develop the distinction through variation, revision, reclassification or attestation.' },
  constrain: { number: 3, icon: IconFilter, description: 'Refine, reclassify or attest the available Propositions.' },
  disperse: { number: 4, icon: IconArrowsSplit, description: 'Open a new branch, add evidence or attest a continuation.' },
  mediate: { number: 5, icon: IconArrowsJoin, description: 'Reconcile the children of corresponding Reads, or attest a continuation.' },
  bias: { number: 6, icon: IconAdjustmentsHorizontal, description: 'Carry forward a Proposition aligned with the population, add evidence or attest a continuation.' },
  order: { number: 7, icon: IconLock, description: 'Commit a grounded candidate with aligned Depth and Cadence, or attest an eligible continuation.' },
};
