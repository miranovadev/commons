import { Avatar, Box, Group, Text, UnstyledButton } from '@mantine/core';
import { IconSelector } from '@tabler/icons-react';
import { participantColor } from '../participantColor';
import type { Participant } from '../types';

export function ParticipantAvatar({ callsign, size = 30 }: { callsign: string; size?: number }) {
  return <Avatar name={callsign} color={participantColor(callsign)} size={size} radius="xl" variant="light" />;
}
export function ParticipantIdentity({ participant, onSelect, opened = false }: { participant: Participant; onSelect: () => void; opened?: boolean }) {
  return <UnstyledButton className="participant-identity" onClick={onSelect} aria-label={`Switch Participant: ${participant.callsign}`} aria-expanded={opened} aria-haspopup="dialog">
    <Group gap={7} wrap="nowrap">
      <ParticipantAvatar callsign={participant.callsign} size={32} />
      <Box miw={0}><Text fw={750} size="sm" truncate>{participant.callsign}</Text>
        <Text className="participant-identity__designation" size="xs" c="dimmed" lineClamp={1}>{participant.designation || 'Participant'}</Text></Box>
      <IconSelector size={14} aria-hidden="true" />
    </Group>
  </UnstyledButton>;
}
