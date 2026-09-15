import { useEffect, useMemo, useState } from 'react';
import { useDispatch } from 'react-redux';
import { useGetGraphParticipantsQuery } from '../services/commonsApi';
import { activityAt } from '../graphParticipants';
import { refreshGraphPropositions } from './cacheActions';
import type { GraphParticipant, Proposition } from '../types';
import type { AppDispatch } from '../store';

const EMPTY: GraphParticipant[] = [];

export function useGraphParticipants(slug: string, enabled: boolean, propositions: Proposition[]) {
  const dispatch = useDispatch<AppDispatch>();
  const { data, error } = useGetGraphParticipantsQuery(slug, {
    skip: !enabled, pollingInterval: 30_000, refetchOnMountOrArgChange: true,
  });
  const [expiryTick, setExpiryTick] = useState(0);
  const participants = useMemo(() => data ? activityAt(data, Date.now()) : EMPTY, [data, expiryTick]);
  useEffect(() => {
    if (!data) return;
    const expiries = participants.filter(p => p.is_active).map(p => p.activity_expires_at!);
    if (!expiries.length) return;
    const serverNow = Date.parse(data.server_time) + Math.max(0, Date.now() - data.received_at);
    const timer = setTimeout(() => setExpiryTick(tick => tick + 1), Math.max(1, Math.min(...expiries) - serverNow + 1));
    return () => clearTimeout(timer);
  }, [data, participants]);

  useEffect(() => {
    if (!enabled || !data) return;
    let active = true;
    const visible = new Set(propositions.map(p => p.id));
    const missing = [...new Set(data.participants.map(p => p.current_proposition_id).filter((id): id is string => Boolean(id) && !visible.has(id!)))];
    // A current position can be newer than the initial bounded graph list.
    if (missing.length) void refreshGraphPropositions(dispatch, slug, missing, () => active).catch(() => { /* The next snapshot retries missing positions. */ });
    return () => { active = false; };
  }, [data, propositions, enabled, dispatch, slug]);
  return { participants, error };
}
