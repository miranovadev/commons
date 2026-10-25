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
        {receipt.preparation_history.selections.map((item, index) => <Text size="xs" key={index}>Source Receipt {item.receipt_id} · Artifact {item.artifact_id} · {item.read_type} · Requested offset {item.parameters?.offset ?? 0} · {item.parameters?.max_chars == null ? 'Automatic allocation' : `Requested maximum ${item.parameters.max_chars}`}</Text>)}
        {receipt.preparation_history.deliveries.map((item, index) => <Text size="xs" key={index}>Presented using {item.method} v{item.version} · {item.read_type} · characters {item.boundaries.offset}–{item.boundaries.end} of {item.boundaries.total} · {item.payload_bytes} bytes{item.boundaries.has_more ? ' · More content exists' : ''}{item.boundaries.length !== undefined ? ` · ${item.boundaries.requested_max_chars == null ? 'Automatic allocation' : `Requested maximum ${item.boundaries.requested_max_chars}`}` : ''}</Text>)}
        <Text size="xs">Outcome: {receipt.preparation_history.outcome.type}{receipt.preparation_history.outcome.proposition_id ? ` · Proposition ${receipt.preparation_history.outcome.proposition_id}` : ''}</Text>
        {receipt.preparation_history.failure && <Text size="xs">Operational failure · {receipt.preparation_history.failure.stage} · {receipt.preparation_history.failure.classification} · {receipt.preparation_history.failure.method} v{receipt.preparation_history.failure.version}: {receipt.preparation_history.failure_reason}. This does not establish source support or refutation.</Text>}
        {receipt.preparation_history.outcome.reason && <Text size="xs">Participant’s deferment reason: {receipt.preparation_history.outcome.reason}</Text>}
        <Text size="xs" c="dimmed">Historical investigation metadata. Source content is not replayed by loading this Receipt.</Text>
      </Stack>}
      {receipt.acquisition && <Stack gap={3} mt="xs" aria-label="File acquisition">
        <Text size="xs" fw={600}>Load File · Download · {receipt.acquisition.content_verified ? 'Acquired' : 'Retrieval failed'}</Text>
        {receipt.acquisition.filename && <Text size="sm">{receipt.acquisition.filename}</Text>}
        {receipt.acquisition.effective_url && <Text size="xs" style={{ overflowWrap: 'anywhere' }}>Retrieved from {receipt.acquisition.effective_url}</Text>}
        {receipt.acquisition.artifacts.map(artifact => <Box key={artifact.hash}>
          <Text size="xs">{artifact.media_type} · {artifact.size} bytes</Text>
          <Text size="xs" style={{ overflowWrap: 'anywhere' }}>{artifact.hash}</Text>
        </Box>)}
        <Text size="xs" c="dimmed">Acquisition: {receipt.acquisition.processing_ms} ms. File contents are not loaded or rendered. Preparation is available when a supported reader exists.</Text>
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
        {receipt.evidence.title && <Text size="sm" fw={600}>{receipt.evidence.title}</Text>}
        {receipt.evidence.content_excerpt && <Text size="sm">{receipt.evidence.content_excerpt}{receipt.evidence.excerpt_truncated ? '…' : ''}</Text>}
        {receipt.evidence.resource && <Text size="xs" c="dimmed">Verified resource: {receipt.evidence.resource}</Text>}
        {receipt.evidence.links && <Stack gap={3} mt="xs">
          <Text size="xs" fw={600}>Page references</Text>
          {receipt.evidence.links.map(link => <Anchor key={link.url} href={link.url} target="_blank" rel="noopener noreferrer" size="sm" style={{ overflowWrap: 'anywhere' }}>{link.text || link.url}</Anchor>)}
          {receipt.evidence.links_error ? <Text size="xs">References could not be extracted. The page observation remains available.</Text>
            : receipt.evidence.links_truncated ? <Text size="xs">Showing {receipt.evidence.links.length} of {receipt.evidence.links_count} discovered destinations.</Text>
              : receipt.evidence.links.length === 0 ? <Text size="xs">No navigable references found.</Text> : null}
          <Text size="xs" c="dimmed">References only; linked resources have not been acquired.</Text>
        </Stack>}
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
