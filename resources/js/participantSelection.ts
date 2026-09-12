import type { Participant } from './types';

export function switchableParticipants(participants: Participant[], systemAccess = false) {
  return participants.filter(participant => participant.owned_by_user || systemAccess);
}
export function participantPath(slug: string, id: string) {
  return `/substrates/${encodeURIComponent(slug)}/participants/${encodeURIComponent(id)}`;
}
export function participantIsWaiting(participant: Participant) {
  return !participant.position || participant.position.cycle_completed === true;
}
