import { useState } from 'react';
import { Alert, Badge, Button, Drawer, Group, Loader, Stack, Text } from '@mantine/core';
import { IconBooks } from '@tabler/icons-react';
import { useGetPreparationHistoryQuery, useGetPreparationQuery } from '../services/commonsApi';
import { messageFrom } from '../errors';
import { PreparationDetails } from './PreparationDetails';

type HistoryProps = { slug: string; participantId?: string; propositionId?: string };

export function PreparationHistory(props: HistoryProps) {
  const [opened, setOpened] = useState(false);
  return <>
    <Button variant="subtle" size="compact-sm" leftSection={<IconBooks size={16} />} onClick={() => setOpened(true)}>Preparation history</Button>
    <Drawer opened={opened} onClose={() => setOpened(false)} position="right" size="min(42rem, 100%)" title="Preparation history">
      {opened && <HistoryBrowser key={`${props.slug}/${props.participantId ?? props.propositionId}`} {...props} />}
    </Drawer>
  </>;
}

function HistoryBrowser(props: HistoryProps) {
  const [cursors, setCursors] = useState<string[]>([]);
  const [executionId, setExecutionId] = useState<string | null>(null);
  const { currentData: history, error, isFetching, refetch } = useGetPreparationHistoryQuery(
    { ...props, before: cursors.at(-1) }, { refetchOnMountOrArgChange: true, pollingInterval: 10000 });
  if (executionId) return <Stack><Button variant="subtle" size="compact-sm" onClick={() => setExecutionId(null)}>Back to Preparations</Button>
    <PreparationRecord slug={props.slug} executionId={executionId} /></Stack>;
  return <Stack>
    {error && <Alert color="red">{messageFrom(error, 'Preparation history could not be loaded.')} <Button variant="subtle" size="compact-xs" onClick={() => refetch()}>Retry</Button></Alert>}
    {isFetching && !history && <Loader size="sm" aria-label="Loading Preparation history" />}
    {history?.items.length === 0 && <Text size="sm" c="dimmed">No Preparations recorded here.</Text>}
    {history?.items.map(item => <Button key={item.id} variant="default" h="auto" py="sm" styles={{ label: { display: 'block', whiteSpace: 'normal', textAlign: 'left', width: '100%' } }} onClick={() => setExecutionId(item.id)}>
      <Group justify="space-between" mb={4}><Text size="sm" fw={600}>{item.participant.callsign} · Cycle {item.cycle.sequence}</Text><Badge size="sm">{item.outcome ?? item.status}</Badge></Group>
      <Text size="sm" lineClamp={2}>{item.purpose ?? 'Purpose not recorded'}</Text>
      <Text size="xs" c="dimmed" mt={4}>{item.created_at ?? 'Date not recorded'}</Text>
    </Button>)}
    <Group justify="space-between">
      <Button variant="subtle" size="compact-sm" disabled={cursors.length === 0} onClick={() => setCursors(current => current.slice(0, -1))}>Newer</Button>
      <Button variant="subtle" size="compact-sm" disabled={!history?.next_cursor} onClick={() => { if (history?.next_cursor) setCursors(current => [...current, history.next_cursor!]); }}>Older</Button>
    </Group>
  </Stack>;
}

function PreparationRecord({ slug, executionId }: { slug: string; executionId: string }) {
  const { currentData: preparation, error, refetch } = useGetPreparationQuery({ slug, executionId }, { refetchOnMountOrArgChange: true, pollingInterval: 10000 });
  return <>
    {error && <Alert color="red">{messageFrom(error, 'Preparation details could not be loaded.')} <Button variant="subtle" size="compact-xs" onClick={() => refetch()}>Retry</Button></Alert>}
    {!preparation && !error && <Loader size="sm" aria-label="Loading Preparation details" />}
    {preparation && <PreparationDetails preparation={preparation} slug={slug} />}
  </>;
}
