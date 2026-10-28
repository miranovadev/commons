import { Badge, Box, Group, Stack, Text } from '@mantine/core';
import type { ReceiptCognition, ReceiptObservation } from '../types';

/** Render the same observation packet used by autonomous Participants. */
export function CognitiveReceiptPanel({ context }: { context: ReceiptCognition }) {
  if (!context.receipts.length) return null;
  return <Stack gap="xs" p="sm" aria-label="Receipt context">
    <Text size="sm" fw={600}>Receipt context</Text>
    <Text size="xs" c="dimmed">Summaries describe causally visible observations. Read context includes observations consumed by the selected Read; it does not change your saved loading choices.</Text>
    {context.receipts.map(receipt => <Box key={receipt.id} p="xs" style={{ border: '1px solid var(--mantine-color-default-border)', borderRadius: 6 }}>
      <Group justify="space-between" gap="xs">
        <Text size="sm" fw={600}>{receipt.type.replaceAll('_', ' ')}</Text>
        <Group gap="xs">
          {receipt.consumed_by_proposition_id && <Badge size="xs" color="gray">Consumed</Badge>}
          {receipt.loaded && <Badge size="xs">Loaded</Badge>}
          {receipt.read_context && <Badge size="xs" color="teal">Read context</Badge>}
        </Group>
      </Group>
      <Observation value={receipt.summary} />
      {receipt.type === 'tragedy' && <Text size="xs" c="dimmed">Memory of a recorded catastrophe. Memory grants no evidence or action authority.</Text>}
      {receipt.originating_gate_id && <Text size="xs" c="dimmed">Originating Gate: {receipt.originating_gate_id}</Text>}
      {receipt.consumed_by_proposition_id && <Text size="xs" c="dimmed">Consumed by: {receipt.consumed_by_proposition_id}</Text>}
      {(['read', 'gate', 'write'] as const).map(role => context[role].propositions
        .filter(node => node.receipts.includes(receipt.id))
        .map(node => <Text key={`${role}:${node.id}`} size="xs" c="dimmed">Referenced by {role}: {node.id}</Text>))}
      {receipt.loaded_inline_content !== undefined && <Box mt="xs">
        <Text size="xs" fw={600}>{receipt.type === 'tragedy' ? 'Scoped memory' : 'Recorded observation'}</Text>
        {receipt.type === 'preparation' && <Text size="xs" c="dimmed">Reading history; preserved source material is examined through Preparation.</Text>}
        <Observation value={receipt.loaded_inline_content} />
      </Box>}
    </Box>)}
  </Stack>;
}

function Observation({ value }: { value: ReceiptObservation }) {
  if (value === null) return null;
  if (typeof value !== 'object') return <Text size="sm" style={{ overflowWrap: 'anywhere', whiteSpace: 'pre-wrap' }}>{String(value)}</Text>;
  if (Array.isArray(value)) return <Stack gap={4}>{value.map((item, index) => <Box key={index}><Observation value={item} /></Box>)}</Stack>;
  return <Stack gap={3}>{Object.entries(value).map(([key, item]) => item === null ? null : <Box key={key}>
    <Text size="xs" c="dimmed">{key.replaceAll('_', ' ')}</Text><Observation value={item} />
  </Box>)}</Stack>;
}
