import { Badge, Box, Drawer, Stack, Text } from '@mantine/core';
import { structuralDeltaSummary } from './MeasurementRails';
import { PropositionContent } from './PropositionContent';
import { AttestationPanel } from './AttestationPanel';
import { attestationDirections, writePropositions } from '../participationOptions';
import type { ContributionInput, Observables, ParticipantPlan, PropositionSelection } from '../types';

interface PropositionInspectorProps {
  selection: PropositionSelection | null;
  committalObservables?: Observables;
  onClose: () => void;
  plan?: ParticipantPlan;
  onSubmit?: (input: ContributionInput) => Promise<boolean>;
  loading?: boolean;
  error?: unknown;
}

export function PropositionInspector({ selection, committalObservables, onClose, plan, onSubmit, loading, error }: PropositionInspectorProps) {
  return (
    <Drawer opened={Boolean(selection)} onClose={onClose} position="right" size="min(34rem, 100%)" title="Proposition inspector">
      <InspectorContent selection={selection} committalObservables={committalObservables} />
      {selection?.kind === 'write option' && plan && onSubmit && attestationDirections(plan, selection.proposition.id).length > 0 && writePropositions(plan).some((item) => item.id === selection.proposition.id) && (
        <Box p="md"><AttestationPanel key={`${plan.phase.id}-${selection.proposition.id}`} plan={plan} proposition={selection.proposition}
          loading={loading} error={error} onSubmit={async (input) => {
            const applied = await onSubmit(input);
            if (applied) onClose();
            return applied;
          }} /></Box>
      )}
    </Drawer>
  );
}

function InspectorContent({ selection, committalObservables }: Omit<PropositionInspectorProps, 'onClose'>) {
  if (!selection) {
    return (
      <Stack className="proposition-inspector__empty" align="center" justify="center" gap="xs">
        <Text fw={700}>Inspect structural state</Text>
        <Text size="sm" c="dimmed" ta="center">Select a proposition, lineage node, candidate, or available move to examine it without changing the cycle.</Text>
      </Stack>
    );
  }

  const proposition = selection.proposition;
  const projected = selection.projectedObservables;
  const summary = structuralDeltaSummary(proposition.observables, projected);

  return (
    <Stack gap="md" p="md">
      {selection.kind && <Badge size="xs" color="gray" variant="outline" style={{ alignSelf: 'flex-start' }}>{selection.kind}</Badge>}

      <Box className="inspector-section">
        <PropositionContent
          proposition={{ ...proposition, title: selection.title || proposition.title }}
          mode="detailed"
          projectedObservables={projected}
          committalObservables={committalObservables}
        />
      </Box>

      {summary.length > 0 && (
        <Box className="inspector-section">
          <Text fw={750} size="sm" mb="sm">Structural delta</Text>
          <Stack gap={6}>
            {summary.map((item) => <Text key={item} size="sm">{item}</Text>)}
          </Stack>
        </Box>
      )}

      {!projected && (
        <Box className="inspector-section">
          <Text fw={750} size="sm" mb="sm">Instruments & receipts</Text>
          <Stack gap={6}>
            {proposition.instruments === undefined
              ? <Text size="sm" c="dimmed">Instrument details are not included in this view.</Text>
              : proposition.instruments.length === 0 && <Text size="sm" c="dimmed">No instrument execution recorded on this proposition.</Text>}
            {proposition.instruments?.map((instrument) => (
              <Box key={instrument.id}>
                <Text size="sm">{instrument.kind} · {instrument.status}{instrument.receipt_id ? ' · Receipt available' : ' · No Receipt'}</Text>
                <Text size="xs" style={{ overflowWrap: 'anywhere' }}>{instrument.reference}</Text>
                {instrument.failure_reason && <Text size="xs" c="red">{instrument.failure_reason}</Text>}
              </Box>
            ))}
          </Stack>
        </Box>
      )}

      {!projected && (
        <Text size="xs" c="dimmed">This is the observed structural state. A projected position appears here only when an admissible move supplies one.</Text>
      )}
    </Stack>
  );
}
