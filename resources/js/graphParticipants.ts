import type { GraphParticipant, GraphParticipantsSnapshot, Identifier } from './types';

/** Server-relative clock avoids permanently active badges from a skewed browser clock. */
export function activityAt(snapshot: GraphParticipantsSnapshot, now: number): GraphParticipant[] {
  const serverNow = Date.parse(snapshot.server_time) + Math.max(0, now - snapshot.received_at);
  return snapshot.participants.map(participant => ({ ...participant,
    is_active: participant.is_active && participant.activity_expires_at !== null && participant.activity_expires_at > serverNow,
  }));
}

export function participantsByPosition(participants: GraphParticipant[]) {
  const positions = new Map<Identifier, GraphParticipant[]>();
  for (const participant of participants) {
    if (!participant.current_proposition_id) continue;
    const occupants = positions.get(participant.current_proposition_id) || [];
    occupants.push(participant);
    positions.set(participant.current_proposition_id, occupants);
  }
  return positions;
}
