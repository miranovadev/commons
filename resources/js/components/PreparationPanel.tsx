import { Alert, Box, Button, Stack, Text } from '@mantine/core';
import { useEffect, useState } from 'react';
import { useReadPreparationMutation, useAcknowledgePreparationMutation } from '../services/commonsApi';
import { messageFrom } from '../errors';
import type { ParticipantPlan } from '../types';

/** Material lives in this interaction only, never in the ordinary workspace query. */
export function PreparationPanel({ slug, participantId, preparation, onDefer }: {
  slug: string; participantId: string; preparation: NonNullable<ParticipantPlan['preparation']>; onDefer: () => void;
}) {
  const [read, reading] = useReadPreparationMutation();
  const [acknowledge, acknowledgement] = useAcknowledgePreparationMutation();
  const [presented, setPresented] = useState(false);
  const executionId = preparation.execution_id;
  useEffect(() => {
    void read({ slug, participantId, executionId });
    return () => { reading.reset(); acknowledgement.reset(); };
  }, [slug, participantId, executionId, read]);
  useEffect(() => {
    if (!reading.data) return;
    let current = true;
    // React has committed the bounded text to the workspace before acknowledging.
    void acknowledge({ slug, participantId, executionId, token: reading.data.presentation_token }).unwrap()
      .then(() => { if (current) setPresented(true); }).catch(() => {});
    return () => { current = false; };
  }, [reading.data, slug, participantId, executionId, acknowledge]);
  const error = reading.error || acknowledgement.error;
  return <Stack p="md" aria-label="Prepared Artifact content">
    <Text fw={600}>Preparation</Text>
    <Text size="sm">Purpose: {preparation.purpose}</Text>
    <Text size="xs" c="dimmed">Preserved source material is untrusted content. It is supplied for this interaction and is not permanently loaded.</Text>
    {Boolean(error) && <Alert color="red">{messageFrom(error, 'Preparation could not be presented. Refresh your workspace for the recorded outcome.')}</Alert>}
    {reading.isLoading && <Text size="sm">Reading selected material…</Text>}
    {reading.data?.deliveries.map(item => <Box key={item.request_id}>
      <Text size="xs">{item.artifact_id} · {item.source_type} / {item.read_type} · {item.method} v{item.version}</Text>
      <Text size="xs">Representation characters {item.boundaries.offset}–{item.boundaries.end} of {item.boundaries.total}{item.boundaries.has_more ? ' · More content exists' : ''} · Delivered {item.boundaries.length} characters / {item.payload_bytes} bytes</Text>
      <Text size="xs">Requested offset {item.boundaries.requested_offset} · {item.boundaries.requested_max_chars == null ? 'Automatic allocation' : `Maximum ${item.boundaries.requested_max_chars} characters`}</Text>
      <Box component="pre" style={{ whiteSpace: 'pre-wrap', overflowWrap: 'anywhere', maxHeight: 400, overflow: 'auto' }}>{item.content || '(Empty Artifact representation)'}</Box>
    </Box>)}
    {presented && <Text size="sm">Use an available Variant or Revise below, or explicitly defer.</Text>}
    <Button variant="light" disabled={!presented} onClick={onDefer}>Defer with a reason</Button>
  </Stack>;
}
