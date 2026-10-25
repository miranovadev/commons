import { Alert, Anchor, Badge, Box, Divider, Group, Stack, Table, Text, Title } from '@mantine/core';
import type { ReactNode } from 'react';
import { Link } from 'react-router-dom';
import type { PreparationHistoryRecord } from '../types';

const unknown = 'Not recorded';
export function preparationPropositionUrl(slug: string, id: string) {
  return `/substrates/${encodeURIComponent(slug)}?proposition=${encodeURIComponent(id)}&view=history`;
}

function Facts({ rows }: { rows: Array<[string, ReactNode]> }) {
  return <Table verticalSpacing="xs"><Table.Tbody>{rows.map(([label, value]) => <Table.Tr key={label}>
    <Table.Th w="38%" style={{ verticalAlign: 'top' }}>{label}</Table.Th>
    <Table.Td style={{ overflowWrap: 'anywhere' }}>{value ?? unknown}</Table.Td>
  </Table.Tr>)}</Table.Tbody></Table>;
}

/** Historical contract only. This component never requests a source reading. */
export function PreparationDetails({ preparation: p, slug }: { preparation: PreparationHistoryRecord; slug: string }) {
  const proposition = (id: string) => <Anchor component={Link} to={preparationPropositionUrl(slug, id)}>{id}</Anchor>;
  return <Stack gap="lg" aria-label="Preparation details">
    <Box>
      <Group gap="xs" mb="sm"><Title order={3}>Preparation</Title><Badge>{p.outcome ?? p.status}</Badge></Group>
      <Text style={{ whiteSpace: 'pre-wrap' }}>{p.purpose ?? 'Purpose not recorded.'}</Text>
    </Box>
    <Box><Title order={4} mb="xs">Identity and provenance</Title><Facts rows={[
      ['Execution', p.id], ['Participant', `${p.participant.callsign} · ${p.participant.id}`],
      ['Originating Gate', proposition(p.originating_gate_id)],
      ['Cycle', `${p.cycle.sequence} · ${p.cycle.id}`], ['Phase', `${p.phase.number} · ${p.phase.name} · ${p.phase.id}`],
      ['Created', p.created_at], ['Started', p.started_at], ['Presentation acknowledged', p.presented_at],
      ['Resolved', p.resolved_at], ['Receipt', p.receipt_id ?? 'No Receipt recorded'],
    ]} /></Box>
    <Box><Title order={4} mb="xs">Requested source selections</Title>
      <Text size="sm" c="dimmed" mb="sm">Selections record what was requested. They do not establish that material was delivered.</Text>
      {p.selections.length === 0 && <Text size="sm" c="dimmed">No selections recorded.</Text>}
      {p.selections.map((selection, index) => <Box key={index} mb="md">
        <Text fw={600} size="sm">Selection {index + 1}</Text>
        <Facts rows={[
          ['Source Receipt', selection.receipt_id], ['Artifact hash', selection.artifact_id],
          ['Source type', selection.source?.receipt_type], ['Source reference', selection.source?.reference],
          ['Filename', selection.source?.filename], ['Media type', selection.source?.media_type], ['Artifact bytes', selection.source?.size],
          ['Read Type', selection.read_type], ['Requested offset', selection.parameters?.offset ?? 0],
          ['Requested maximum characters', selection.parameters?.max_chars ?? 'Automatic allocation'],
        ]} />
      </Box>)}
    </Box>
    <Box><Title order={4} mb="xs">Acknowledged reading boundaries</Title>
      {p.deliveries.length === 0 && <Text size="sm" c="dimmed">No acknowledged delivery is recorded. Successful reading is not implied.</Text>}
      {p.deliveries.map((delivery, index) => <Box key={delivery.request_id ?? index} mb="md">
        <Text fw={600} size="sm">Delivery {index + 1}</Text><Facts rows={[
          ['Artifact hash', delivery.artifact_id], ['Source Receipts', delivery.receipt_ids.join(', ')],
          ['Read Type', delivery.read_type], ['Source type', delivery.source_type],
          ['Reader method', delivery.method], ['Reader version', delivery.version],
          ['Representation', delivery.representation], ['Representation hash', delivery.representation_hash],
          ['Delivery method', delivery.delivery_method], ['Effective offset', delivery.boundaries?.offset],
          ['Actual end', delivery.boundaries?.end], ['Delivered characters', delivery.boundaries?.length],
          ['Delivered bytes', delivery.payload_bytes], ['Total representation characters', delivery.boundaries?.total],
          ['More material remained', delivery.boundaries?.has_more == null ? unknown : delivery.boundaries.has_more ? 'Yes' : 'No'],
        ]} />
      </Box>)}
    </Box>
    <Box><Title order={4} mb="xs">Outcome and diagnostics</Title>
      <Text size="sm">Execution status: {p.status}</Text>
      {p.outcome === 'contributed' && <Text size="sm">Contributed · Resulting Proposition: {p.resulting_proposition_id ? proposition(p.resulting_proposition_id) : unknown}</Text>}
      {p.outcome === 'deferred' && <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>Deferred · Participant’s reason: {p.deferment_reason ?? unknown}</Text>}
      {p.status === 'pending' && <Text size="sm">Pending · No presentation or final outcome has been recorded.</Text>}
      {p.status === 'presented' && <Text size="sm">Presented · Delivery was acknowledged; no final outcome has been recorded.</Text>}
      {p.status === 'failed' && <Stack gap="xs" mt="sm">
        <Alert color="orange">Operational failure. No successful source observation is implied; this does not establish that the source supports or refutes a Proposition.</Alert>
        <Facts rows={[
          ['Failure stage', p.failure?.stage], ['Classification', p.failure?.classification],
          ['Reporting method', p.failure?.method], ['Reporting version', p.failure?.version], ['Diagnostic reason', p.failure?.reason],
        ]} />
      </Stack>}
    </Box>
    <Divider /><Text size="xs" c="dimmed">Persisted investigation history. Extracted source content is transient and is not replayed here.</Text>
  </Stack>;
}
