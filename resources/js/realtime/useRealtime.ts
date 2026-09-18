import { useEffect, useRef } from 'react';
import { useDispatch } from 'react-redux';
import { commonsApi, useGetGraphSubscriptionsQuery } from '../services/commonsApi';
import type { AppDispatch } from '../store';
import { refreshGraphMetrics, refreshGraphPropositions, refreshParticipant } from './cacheActions';
import { notificationQueue } from './notifications';
import type { Invalidation } from './notifications';
import { subscribeInvalidations } from './subscriptions';

export function useParticipantRealtime(slug: string, participantId: string, enabled: boolean, horizonId?: string | null) {
  const dispatch = useDispatch<AppDispatch>();
  useEffect(() => {
    if (!enabled || !participantId) return;
    const queue = notificationQueue(async () => {
      refreshParticipant(dispatch, participantId);
    });
    const release = subscribeInvalidations(`participant.${participantId}`, false, {
      invalidated: event => {
        if (event.type === 'participant.state_changed' && event.participant_id === participantId) queue.add(participantId);
      },
      subscribed: () => queue.add(participantId),
    });
    return () => { queue.dispose(); release(); };
  }, [dispatch, slug, participantId, enabled]);

  // Another Participant may exhaust a local alternative while this viewer stays
  // still. Reuse channel ownership/transport; refresh the workspace by ID only.
  useEffect(() => {
    if (!enabled || !participantId || !horizonId) return;
    const queue = notificationQueue(async () => { refreshParticipant(dispatch, participantId); });
    const release = subscribeInvalidations(`context-horizon.${horizonId}`, false, {
      invalidated: event => {
        if ('context_horizon_id' in event && event.context_horizon_id === horizonId) queue.add(participantId);
      },
      subscribed: () => queue.add(participantId),
    });
    return () => { queue.dispose(); release(); };
  }, [dispatch, slug, participantId, enabled, horizonId]);
}

export function useContextHorizonRealtime(slug: string, enabled: boolean) {
  const dispatch = useDispatch<AppDispatch>();
  const { currentData: data } = useGetGraphSubscriptionsQuery(slug, { skip: !enabled });
  const substrateId = data?.substrate_id;
  const publicChannel = data?.public ?? false;
  const horizonKey = JSON.stringify([...new Set(data?.context_horizon_ids ?? [])].sort());
  const observation = useRef<{ reconcile: (ids: string[]) => void } | null>(null);

  useEffect(() => {
    if (!enabled || !substrateId) return;
    let active = true;
    const horizons = new Map<string, () => void>();
    const prefix = publicChannel ? 'public.' : '';
    const queue = notificationQueue(async keys => {
      if (keys.includes('participants') || keys.includes('catchup')) {
        dispatch(commonsApi.util.invalidateTags([{ type: 'GraphParticipants', id: slug }]));
      }
      if (keys.includes('subscriptions')) {
        dispatch(commonsApi.util.invalidateTags([{ type: 'GraphSubscriptions', id: slug }]));
      }
      if (keys.includes('catchup')) {
        // Subscription ACKs cover the HTTP-to-WebSocket gap, including reconnects.
        // forceRefetch alone joins an in-flight request whose snapshot can predate
        // the ACK. Wait for it, then start the catch-up request while still owned.
        await dispatch(commonsApi.util.getRunningQueryThunk('getPropositions', slug));
        if (!active) return;
        await dispatch(commonsApi.endpoints.getPropositions.initiate(slug, { subscribe: false, forceRefetch: true }));
      }
      if (!active) return;
      dispatch(commonsApi.util.invalidateTags([{ type: 'PropositionHistory', id: slug }]));
      await refreshGraphPropositions(dispatch, slug, keys.filter(key => !['catchup', 'subscriptions', 'participants', 'metrics'].includes(key)), () => active);
      if (keys.includes('metrics') || keys.includes('catchup')) await refreshGraphMetrics(dispatch, slug, () => active);
    });
    const catchup = () => {
      queue.add('subscriptions');
      queue.add('catchup');
    };
    const invalidated = (event: Invalidation) => {
      if (event.type === 'context-horizons.changed' && event.substrate_id === substrateId) catchup();
      if (event.type === 'graph.participant_changed' && event.substrate_id === substrateId) queue.add('participants');
      if (event.type === 'graph.metrics_changed' && event.substrate_id === substrateId) queue.add('metrics');
      if ('context_horizon_id' in event && horizons.has(event.context_horizon_id)
        && (event.type === 'horizon.tragedy' || event.type === 'horizon.exhausted')) catchup();
      if ('proposition_id' in event && horizons.has(event.context_horizon_id)) {
        queue.add(event.proposition_id);
        queue.add('metrics');
      }
    };
    const observer = { invalidated, subscribed: catchup };
    const releaseSubstrate = subscribeInvalidations(`${prefix}substrate.${substrateId}`, publicChannel, observer);
    observation.current = {
      reconcile(ids) {
        const next = new Set(ids);
        for (const [id, release] of horizons) {
          if (next.has(id)) continue;
          release();
          horizons.delete(id);
          // Retirement can remove nodes without acknowledging any new channel.
          queue.add('catchup');
        }
        for (const id of next) {
          if (!horizons.has(id)) horizons.set(id, subscribeInvalidations(`${prefix}context-horizon.${id}`, publicChannel, observer));
        }
      },
    };
    return () => {
      active = false;
      observation.current = null;
      queue.dispose();
      releaseSubstrate();
      horizons.forEach(release => release());
    };
  }, [dispatch, slug, enabled, substrateId, publicChannel]);

  // Changes to the descriptor reconcile channel ownership without replacing the
  // queue, existing listeners, or transport. Also rerun when the observer changes.
  useEffect(() => {
    observation.current?.reconcile(JSON.parse(horizonKey));
  }, [dispatch, slug, enabled, substrateId, publicChannel, horizonKey]);
}
