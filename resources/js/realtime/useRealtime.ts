import { useEffect } from 'react';
import { useDispatch } from 'react-redux';
import { commonsApi, useGetGraphSubscriptionsQuery } from '../services/commonsApi';
import type { AppDispatch } from '../store';
import { createEcho } from './echo';
import { refreshGraphMetrics, refreshGraphPropositions, refreshParticipant } from './cacheActions';
import { notificationQueue } from './notifications';
import type { Invalidation } from './notifications';

export function useParticipantRealtime(slug: string, participantId: string, enabled: boolean) {
  const dispatch = useDispatch<AppDispatch>();
  useEffect(() => {
    if (!enabled || !participantId) return;
    const echo = createEcho();
    if (!echo) return;
    const queue = notificationQueue(async () => {
      refreshParticipant(dispatch, participantId);
    });
    const name = `participant.${participantId}`;
    echo.private(name).listen('.invalidation', (event: Invalidation) => {
      if (event.type === 'participant.state_changed' && event.participant_id === participantId) queue.add(participantId);
    }).subscribed(() => queue.add(participantId));
    return () => { queue.dispose(); echo.leave(name); echo.disconnect(); };
  }, [dispatch, slug, participantId, enabled]);
}

export function useContextHorizonRealtime(slug: string, enabled: boolean) {
  const dispatch = useDispatch<AppDispatch>();
  const { data } = useGetGraphSubscriptionsQuery(slug, { skip: !enabled });
  const substrateId = data?.substrate_id;
  const publicChannel = data?.public ?? false;
  const horizonKey = data?.context_horizon_ids.join(',') || '';
  useEffect(() => {
    if (!enabled || !substrateId) return;
    const echo = createEcho();
    if (!echo) return;
    let active = true;
    const horizonIds = horizonKey ? horizonKey.split(',') : [];
    const prefix = publicChannel ? 'public.' : '';
    const names = [`${prefix}substrate.${substrateId}`, ...horizonIds.map(id => `${prefix}context-horizon.${id}`)];
    const queue = notificationQueue(async keys => {
      if (keys.includes('participants') || keys.includes('catchup')) {
        dispatch(commonsApi.util.invalidateTags([{ type: 'GraphParticipants', id: slug }]));
      }
      if (keys.includes('subscriptions')) {
        dispatch(commonsApi.util.invalidateTags([{ type: 'GraphSubscriptions', id: slug }]));
      }
      if (keys.includes('catchup')) {
        // Once per subscription/reconnection: cover the HTTP-to-WebSocket gap.
        await dispatch(commonsApi.endpoints.getPropositions.initiate(slug, { subscribe: false, forceRefetch: true }));
      }
      await refreshGraphPropositions(dispatch, slug, keys.filter(key => !['catchup', 'subscriptions', 'participants', 'metrics'].includes(key)), () => active);
      if (keys.includes('metrics')) await refreshGraphMetrics(dispatch, slug, () => active);
    });
    const subscribed = new Set<string>();
    names.forEach(name => {
      const channel = publicChannel ? echo.channel(name) : echo.private(name);
      channel.listen('.invalidation', (event: Invalidation) => {
        if (event.type === 'context-horizons.changed' && event.substrate_id === substrateId) {
          queue.add('subscriptions');
          queue.add('catchup');
        }
        if (event.type === 'graph.participant_changed' && event.substrate_id === substrateId) queue.add('participants');
        if (event.type === 'graph.metrics_changed' && event.substrate_id === substrateId) queue.add('metrics');
        if ('proposition_id' in event && horizonIds.includes(event.context_horizon_id)) {
          queue.add(event.proposition_id);
          queue.add('metrics');
        }
      }).subscribed(() => {
        subscribed.add(name);
        if (subscribed.size === names.length) {
          queue.add('subscriptions');
          queue.add('catchup');
        }
      });
    });
    return () => { active = false; queue.dispose(); names.forEach(name => echo.leave(name)); echo.disconnect(); };
  }, [dispatch, slug, enabled, substrateId, publicChannel, horizonKey]);
}
