import { commonsApi } from '../services/commonsApi';
import type { AppDispatch } from '../store';
import { mergeProposition } from './notifications';

export function refreshParticipant(dispatch: AppDispatch, participantId: string) {
  dispatch(commonsApi.util.invalidateTags([
    { type: 'Participation', id: participantId },
    { type: 'Participant', id: participantId },
  ]));
}

export async function refreshGraphPropositions(dispatch: AppDispatch, slug: string, ids: string[], active: () => boolean) {
  // An initial or ordinary HTTP list refresh must finish before an incremental merge.
  await dispatch(commonsApi.util.getRunningQueryThunk('getPropositions', slug));
  await Promise.allSettled([...new Set(ids)].map(async propositionId => {
    const proposition = await dispatch(commonsApi.endpoints.getProposition.initiate(
      { slug, propositionId }, { subscribe: false, forceRefetch: true },
    )).unwrap();
    if (active()) dispatch(commonsApi.util.updateQueryData('getPropositions', slug, draft => mergeProposition(draft, proposition)));
  }));
}
