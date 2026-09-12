import { Button, Stack, Text } from '@mantine/core';
import { cycleEntryPresentation } from '../cycleEntry';
import type { Participation } from '../types';

export function CycleEntryControl({ participation, loading, onEnter, onOrigin }: {
  participation: Pick<Participation, 'status' | 'entry' | 'reason'>; loading?: boolean; onEnter: () => void; onOrigin: () => void;
}) {
  const entry = cycleEntryPresentation(participation);
  return <Stack gap="xs" align="flex-start">
    <Text size="sm" c="dimmed">{entry.message}</Text>
    {entry.available && <Button color="indigo" loading={loading} onClick={entry.mode === 'origin' ? onOrigin : onEnter}>{entry.label}</Button>}
  </Stack>;
}
