import { Anchor, Badge, Box, Group, Stack, Text } from '@mantine/core';
import { IconAnchor } from '@tabler/icons-react';
import { propositionReceiptReferences } from '../participationOptions';
import type { ParticipantPlan } from '../types';

/** Metadata and active Read evidence; memory changes accompany the forward contribution. */
export function ReceiptContextPanel({ plan }: { plan: ParticipantPlan }) {
  const receipts = (plan.receipts ?? []);
  const references = propositionReceiptReferences(plan).filter(ref => !receipts.some(receipt => receipt.id === ref.id));
  if (!receipts.length && !references.length) return null;
  return <Stack gap="xs" p="sm" aria-label="Receipt context">
    <Group gap="xs"><IconAnchor size={16} /><Text size="sm" fw={600}>Receipt context</Text></Group>
    <Text size="xs" c="dimmed">Commit Region Receipts show metadata. Loading persists; full evidence appears only while its Proposition occupies Read. Gate loading changes can accompany your normal contribution.</Text>
    {receipts.map(receipt => <Box key={receipt.id} p="xs" style={{ border: '1px solid var(--mantine-color-default-border)', borderRadius: 6 }}>
      <Group justify="space-between" gap="xs">
        <Text size="xs" style={{ overflowWrap: 'anywhere' }}>{receipt.type === 'tragedy' ? 'Tragedy Receipt' : receipt.type} · {receipt.reference}</Text>
        <Group gap="xs"><Badge size="xs" variant="light" color={receipt.availability === 'consumed' ? 'gray' : 'teal'}>{receipt.availability === 'consumed' ? 'Consumed' : receipt.availability === 'historical' ? 'Context only' : 'Available'}</Badge>{receipt.status === 'loaded' ? <Badge size="xs" variant="light" leftSection={<IconAnchor size={12} />}>Loaded</Badge>
          : null}
        </Group>
      </Group>
      {receipt.type === 'tragedy' && <Text size="xs" c="dimmed">Load to remember the failed committed lineage, never to reinforce it. Separate one-shot boundary-exit warrant for eligible homologous Bias in its owning Commit Region. Not evidence or replayable; memory grants no action authority.</Text>}
      {receipt.memory_active && receipt.tragedy_memory && <Box mt={5}>
        <Text size="xs" fw={600}>Tragedy memory · no Reinforcement</Text>
        {receipt.tragedy_memory.chain_direction === 'backward' && <Text size="xs">Looking backward from the current Committal to the catastrophe’s Region.</Text>}
        {receipt.tragedy_memory.earlier_history_omitted && <Text size="xs">Earlier committed history omitted.</Text>}
        {receipt.tragedy_memory.committed_chain.map(node => <Box key={node.proposition_id} mt="xs">
          <Text size="xs" c="dimmed">{node.register} · {node.domain} · Tension {node.tension}</Text>
          <Text size="sm">{node.statement}</Text>
        </Box>)}
        <Text size="xs" fw={600}>Recorded catastrophe · Phase {receipt.tragedy_memory.catastrophe.phase}</Text>
        <Text size="sm">{receipt.tragedy_memory.catastrophe.statement}</Text>
        <Text size="xs">{receipt.tragedy_memory.catastrophe.recorded_field_available
          ? `Historical Tension ${receipt.tragedy_memory.catastrophe.tension} · Charge ${receipt.tragedy_memory.catastrophe.charge}`
          : 'Historical boundary field unavailable'}</Text>
      </Box>}
      {receipt.preparation_history && <Stack gap={3} mt="xs">
        <Text size="xs" fw={600}>Preparation purpose: {receipt.preparation_history.purpose}</Text>
        <Text size="xs">Participant {receipt.preparation_history.participant_id} · Gate {receipt.preparation_history.originating_gate_id}</Text>
        {receipt.preparation_history.selections.map((item, index) => <Text size="xs" key={index}>Source Receipt {item.receipt_id} · Artifact {item.artifact_id} · {item.read_type}</Text>)}
        {receipt.preparation_history.deliveries.map((item, index) => <Text size="xs" key={index}>Presented using {item.method} v{item.version} · {item.read_type} · characters {item.boundaries.offset}–{item.boundaries.end} of {item.boundaries.total}</Text>)}
        <Text size="xs">Outcome: {receipt.preparation_history.outcome.type}{receipt.preparation_history.outcome.proposition_id ? ` · Proposition ${receipt.preparation_history.outcome.proposition_id}` : ''}</Text>
        {receipt.preparation_history.outcome.reason && <Text size="xs">Participant’s deferment reason: {receipt.preparation_history.outcome.reason}</Text>}
        <Text size="xs" c="dimmed">Historical investigation metadata. Source content is not replayed by loading this Receipt.</Text>
      </Stack>}
      {receipt.attached_proposition_id && <Text size="xs" c="dimmed">Attached to {receipt.attached_proposition_id}</Text>}
      {receipt.status === 'already-in-context' && <Text size="xs" c="dimmed">Referenced Proposition is already in this context.</Text>}
      {receipt.observation_error && <Text size="xs" c="dimmed">{receipt.observation_error}</Text>}
      {receipt.type !== 'tragedy' && receipt.active && receipt.evidence && <Box mt={5}>
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
    {references.length > 0 && <Text size="xs" fw={600}>Proposition Receipt references</Text>}
    {references.map(receipt => <Box key={receipt.id} p="xs">
      <Text size="xs">{receipt.type} · {receipt.reference} · {receipt.loaded ? 'Loaded' : 'Unloaded'}</Text>
      <Text size="xs" c="dimmed">Referenced by {receipt.proposition_id}</Text>

    </Box>)}
  </Stack>;
}
