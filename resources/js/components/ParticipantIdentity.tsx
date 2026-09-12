import { Avatar, Box, Group, Paper, Text, ThemeIcon } from '@mantine/core';
import { IconUserHexagon } from '@tabler/icons-react';
import { participantColor } from '../participantColor';
import type { Participant } from '../types';

export function ParticipantAvatar({ callsign, size = 30 }: { callsign: string; size?: number }) {
  return <Avatar name={callsign} color={participantColor(callsign)} size={size} radius="xl" variant="light" />;
}
export function ParticipantIdentity({ participant }: { participant: Participant }) {
  return <Paper withBorder className="participant-identity" radius="lg" p="xs">
    <Group gap="xs" wrap="nowrap">
      <ThemeIcon color="indigo" variant="light" size={36} radius="xl"><IconUserHexagon size={25} /></ThemeIcon>
      <Box miw={0}><Text fw={750} size="sm" truncate>{participant.callsign}</Text>
        <Text size="xs" c="dimmed" lineClamp={1} title={participant.designation || 'Participant'}>{participant.designation || 'Participant'}</Text></Box>
    </Group>
  </Paper>;
}
