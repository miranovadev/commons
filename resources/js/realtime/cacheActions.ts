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
  const unique = [...new Set(ids)];
  if (unique.length > 1) {
    // One burst is a bounded batch; never one HTTP request per graph card.
    for (let offset = 0; offset < unique.length; offset += 100) {
      const batch = unique.slice(offset, offset + 100);
      const propositions = await dispatch(commonsApi.endpoints.getPropositionBatch.initiate(
        { slug, ids: batch }, { subscribe: false, forceRefetch: true },
      )).unwrap();
      if (active()) {
        dispatch(commonsApi.util.updateQueryData('getPropositions', slug, draft => {
          for (const proposition of propositions) mergeProposition(draft, proposition);
        }));
        dispatch(commonsApi.util.invalidateTags(batch.map(id => ({ type: 'Proposition' as const, id }))));
      }
    }
    return;
  }
  await Promise.allSettled(unique.map(async propositionId => {
    const proposition = await dispatch(commonsApi.endpoints.getProposition.initiate(
      { slug, propositionId }, { subscribe: false, forceRefetch: true },
    )).unwrap();
    if (active()) dispatch(commonsApi.util.updateQueryData('getPropositions', slug, draft => mergeProposition(draft, proposition)));
  }));
}

export async function refreshGraphMetrics(dispatch: AppDispatch, slug: string, active: () => boolean) {
  const metrics = await dispatch(commonsApi.endpoints.getGraphMetrics.initiate(slug, { subscribe: false, forceRefetch: true })).unwrap();
  await dispatch(commonsApi.util.getRunningQueryThunk('getPropositions', slug));
  if (active()) dispatch(commonsApi.util.updateQueryData('getPropositions', slug, draft => {
    const byId = new Map(metrics.map(item => [item.id, item]));
    for (const proposition of draft) {
      const metric = byId.get(proposition.id);
      if (metric) {
        if (JSON.stringify(proposition.field) !== JSON.stringify(metric.field)) proposition.field = metric.field;
        proposition.participation_count = metric.participation_count;
      }
    }
  }));
}
