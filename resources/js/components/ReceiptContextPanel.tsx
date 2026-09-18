import { Anchor, Badge, Box, Button, Group, Stack, Text } from '@mantine/core';
import { IconAnchor, IconBook, IconRefresh } from '@tabler/icons-react';
import { actionTargetIds } from '../participationOptions';
import type { ParticipantPlan } from '../types';

/** Receipt uptake is a context action; it does not navigate Read/Gate/Write. */
export function ReceiptContextPanel({ plan, onRead, canRead = true, onReplay, canReplay = true }: {
  plan: ParticipantPlan; onRead: (id: string) => void; canRead?: boolean; onReplay?: (id: string) => void; canReplay?: boolean;
}) {
  const replays = canReplay && onReplay ? actionTargetIds(plan, 'receipt.replay') : new Set<string>();
  const receipts = (plan.receipts ?? []);
  if (!receipts.length) return null;
  const allowed = canRead ? actionTargetIds(plan, 'receipt.read') : new Set<string>();
  return <Stack gap="xs" p="sm" aria-label="Receipt context">
    <Group gap="xs"><IconAnchor size={16} /><Text size="sm" fw={600}>Horizon Receipts</Text></Group>
    <Text size="xs" c="dimmed">Shared evidence is visible to everyone in this Horizon. Reclassify attaches and consumes a Receipt once; its history remains.</Text>
    {receipts.map(receipt => <Box key={receipt.id} p="xs" style={{ border: '1px solid var(--mantine-color-default-border)', borderRadius: 6 }}>
      <Group justify="space-between" gap="xs">
        <Text size="xs" style={{ overflowWrap: 'anywhere' }}>{receipt.type === 'tragedy' ? 'Tragedy Receipt' : receipt.type} · {receipt.reference}</Text>
        <Group gap="xs"><Badge size="xs" variant="light" color={receipt.availability === 'consumed' ? 'gray' : 'teal'}>{receipt.availability === 'consumed' ? 'Consumed' : receipt.availability === 'historical' ? 'Context only' : 'Available'}</Badge>{receipt.status === 'loaded' ? <Badge size="xs" variant="light" leftSection={<IconAnchor size={12} />}>Loaded</Badge>
          : allowed.has(receipt.id) && <Button size="compact-xs" variant="light" leftSection={<IconBook size={13} />} onClick={() => onRead(receipt.id)}>Read Receipt</Button>}
          {replays.has(receipt.id) && <Button size="compact-xs" variant="light" leftSection={<IconRefresh size={13} />} onClick={() => onReplay?.(receipt.id)}>Replay Receipt</Button>}
        </Group>
      </Group>
      {receipt.attached_proposition_id && <Text size="xs" c="dimmed">Attached to {receipt.attached_proposition_id}</Text>}
      {receipt.status === 'already-in-context' && <Text size="xs" c="dimmed">Referenced Proposition is already in this context.</Text>}
      {receipt.observation_error && <Text size="xs" c="dimmed">{receipt.observation_error}</Text>}
      {receipt.evidence && <Box mt={5}>
        {receipt.evidence.exhausted_committals?.map(item => <Box key={item.id} mt="xs">
          <Text size="xs" fw={600}>Exhausted Committal · Phase {item.phase_number} · {item.register}</Text>
          <Text size="sm">{item.statement}</Text>
        </Box>)}
        {receipt.evidence.statement && <Text size="sm">{receipt.evidence.statement}</Text>}
        {receipt.evidence.direction && <Text size="xs">{receipt.evidence.direction}: {receipt.evidence.rationale}</Text>}
        {receipt.evidence.content_excerpt && <Text size="sm">{receipt.evidence.content_excerpt}{receipt.evidence.excerpt_truncated ? '…' : ''}</Text>}
        {receipt.evidence.resource && <Text size="xs" c="dimmed">Verified resource: {receipt.evidence.resource}</Text>}
        {receipt.evidence.executed_at && <Text size="xs" c="dimmed">Observed {receipt.evidence.executed_at}</Text>}
        {receipt.evidence.results?.map((result) => <Box key={result.rank} mt="xs">
          <Anchor href={result.url} target="_blank" rel="noopener noreferrer" size="sm">{result.title || result.url}</Anchor>
          <Text size="xs" c="dimmed">{result.source}</Text>
          <Text size="sm">{result.excerpt}{result.excerpt_truncated ? '…' : ''}</Text>
        </Box>)}
        {receipt.evidence.results?.length === 0 && <Text size="xs">No resources returned for this query.</Text>}
      </Box>}
    </Box>)}
  </Stack>;
}
