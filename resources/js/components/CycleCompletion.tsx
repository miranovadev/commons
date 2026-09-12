import { Alert, Box, Button, Group, Stack, Text } from '@mantine/core';
import { Link } from 'react-router-dom';
import { cycleEntryPresentation } from '../cycleEntry';
import { messageFrom } from '../errors';
import type { Participation } from '../types';

export function CycleCompletion({ slug, participation, loading, error, onEnter, onOrigin }: {
  slug: string; participation: Participation; loading?: boolean; error?: unknown; onEnter: () => void; onOrigin: () => void;
}) {
  const entry = cycleEntryPresentation(participation);
  return <Box className="cycle-completion"><Stack gap="xs">
    <Text size="sm" fw={600} role="status">Cycle complete</Text>
    <Text size="xs" c="dimmed">{entry.message}</Text>
    {Boolean(error) && <Alert color="red">{messageFrom(error, 'The Cycle could not be entered.')}</Alert>}
    <Group gap="xs">
      <Button component={Link} to={`/substrates/${slug}/participants`} variant="subtle" color="gray" size="compact-sm">Return to Participants</Button>
      <Button color="indigo" size="compact-sm" disabled={!entry.available} loading={loading} onClick={entry.mode === 'origin' ? onOrigin : onEnter}>Enter another Cycle</Button>
    </Group>
  </Stack></Box>;
}
