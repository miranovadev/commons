import { Alert, Button, Stack, Text, Textarea } from '@mantine/core';
import { useState } from 'react';
import { messageFrom } from '../errors';
import { attestationDirections } from '../participationOptions';
import type { AttestationDirection, ContributionInput, ParticipantPlan, Proposition } from '../types';
import { AttestationDirectionControl } from './AttestationDirectionControl';

export function AttestationPanel({ plan, proposition, loading = false, error, onSubmit }: {
  plan: ParticipantPlan;
  proposition: Proposition;
  loading?: boolean;
  error?: unknown;
  onSubmit: (input: ContributionInput) => Promise<boolean>;
}) {
  const [selected, setSelected] = useState<AttestationDirection>('support');
  const [rationale, setRationale] = useState('');
  const directions = attestationDirections(plan, proposition.id);
  const direction = directions.includes(selected) ? selected : directions[0];
  if (!direction) return null;

  return <form onSubmit={(event) => {
    event.preventDefault();
    if (loading) return;
    void onSubmit({ action: 'attestation.create', proposition_id: proposition.id, direction,
      ...(rationale.trim() ? { rationale: rationale.trim() } : {}) });
  }}>
    <Stack gap="sm">
      <Text fw={750} size="sm">Attest this Proposition</Text>
      {Boolean(error) && <Alert color="red" role="alert">{messageFrom(error, 'The Attestation could not be applied.')}</Alert>}
      <AttestationDirectionControl value={direction} directions={directions} onChange={setSelected} />
      <Textarea label="Rationale" description="Optional context for your Attestation." minRows={4} maxLength={20000}
        value={rationale} onChange={(event) => setRationale(event.currentTarget.value)} disabled={loading} />
      <Button type="submit" color={direction === 'support' ? 'violet' : 'teal'} loading={loading}>
        {direction === 'support' ? 'Support' : 'Refute'} with Attestation
      </Button>
    </Stack>
  </form>;
}
