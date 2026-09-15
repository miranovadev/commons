import { Box, Text, Tooltip } from '@mantine/core';
import { ParticipantAvatar } from './ParticipantIdentity';
import type { GraphParticipant } from '../types';

export function GraphParticipants({ participants, compact = false }: { participants: GraphParticipant[]; compact?: boolean }) {
  if (!participants.length) return null;
  const limit = compact ? 2 : 4;
  const visible = participants.slice(0, limit);
  const overflow = participants.slice(limit);
  return <Box className="graph-participants" data-compact={compact || undefined} role="group" aria-label="Participants at this proposition">
    {visible.map(participant => <Tooltip key={participant.id} label={`${participant.callsign} · ${participant.is_active ? 'executing a wake' : 'inactive'}`} withArrow>
      <Box className="graph-participant" data-participant-id={participant.id} data-active={participant.is_active || undefined}
        tabIndex={0} role="img" aria-label={`${participant.callsign}: ${participant.is_active ? 'active' : 'inactive'}`}>
        <ParticipantAvatar callsign={participant.callsign} size={compact ? 20 : 27} />
        {participant.is_active && <span className="graph-participant__activity" aria-hidden="true" />}
      </Box>
    </Tooltip>)}
    {overflow.length > 0 && <Tooltip label={overflow.map(p => `${p.callsign} · ${p.is_active ? 'active' : 'inactive'}`).join(', ')} withArrow>
      <Box className="graph-participant graph-participant--overflow" tabIndex={0} role="img"
        data-active={overflow.some(p => p.is_active) || undefined}
        aria-label={overflow.map(p => `${p.callsign}: ${p.is_active ? 'active' : 'inactive'}`).join(', ')}>
        <Text size="10px" fw={700}>+{overflow.length}</Text>
        {overflow.some(p => p.is_active) && <span className="graph-participant__activity" aria-hidden="true" />}
      </Box>
    </Tooltip>}
  </Box>;
}
