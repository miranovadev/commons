import { Badge, Box, Button, Group, Stack, Text } from '@mantine/core';
import { IconAnchor, IconBook } from '@tabler/icons-react';
import { actionTargetIds } from '../participationOptions';
import type { ParticipantPlan } from '../types';

/** Receipt uptake is a context action; it does not navigate Read/Gate/Write. */
export function ReceiptContextPanel({ plan, onRead, canRead = true }: { plan: ParticipantPlan; onRead: (id: string) => void; canRead?: boolean }) {
  const receipts = (plan.receipts ?? []).filter(receipt => receipt.status !== 'unqualified');
  if (!receipts.length) return null;
  const allowed = canRead ? actionTargetIds(plan, 'receipt.read') : new Set<string>();
  return <Stack gap="xs" p="sm" aria-label="Receipt context">
    <Group gap="xs"><IconAnchor size={16} /><Text size="sm" fw={600}>Receipt context</Text></Group>
    <Text size="xs" c="dimmed">Read evidence to keep it loaded across Cycles. Each Participant contributes at most one Reinforcement per Proposition.</Text>
    {receipts.map(receipt => <Box key={receipt.id} p="xs" style={{ border: '1px solid var(--mantine-color-default-border)', borderRadius: 6 }}>
      <Group justify="space-between" gap="xs">
        <Text size="xs" style={{ overflowWrap: 'anywhere' }}>{receipt.type} · {receipt.reference}</Text>
        {receipt.status === 'loaded' ? <Badge size="xs" variant="light" leftSection={<IconAnchor size={12} />}>Loaded</Badge>
          : allowed.has(receipt.id) && <Button size="compact-xs" variant="light" leftSection={<IconBook size={13} />} onClick={() => onRead(receipt.id)}>Read Receipt</Button>}
      </Group>
      {receipt.status === 'already-in-context' && <Text size="xs" c="dimmed">Referenced Proposition is already in this context.</Text>}
      {receipt.evidence && <Box mt={5}>
        {receipt.evidence.statement && <Text size="sm">{receipt.evidence.statement}</Text>}
        {receipt.evidence.direction && <Text size="xs">{receipt.evidence.direction}: {receipt.evidence.rationale}</Text>}
        {receipt.evidence.content_excerpt && <Text size="sm">{receipt.evidence.content_excerpt}{receipt.evidence.excerpt_truncated ? '…' : ''}</Text>}
        {receipt.evidence.resource && <Text size="xs" c="dimmed">Verified resource: {receipt.evidence.resource}</Text>}
      </Box>}
    </Box>)}
  </Stack>;
}
