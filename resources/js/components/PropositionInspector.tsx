import { IconChevronLeft, IconChevronRight, IconX } from '@tabler/icons-react';
import { useState, type ReactNode } from 'react';
import { ClosureHistoryChart, FieldStateChart } from './InspectorCharts';
import { ActionIcon, Badge, Box, Drawer, Group, Paper, Stack, Tabs, Text, Tooltip } from '@mantine/core';
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

export function InspectorContent({ selection, committalObservables }: Omit<PropositionInspectorProps, 'onClose'>) {
  const [tab, setTab] = useState<string | null>('proposition');
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
    <Tabs value={tab === 'closure' && proposition.tier !== 'committal' ? 'proposition' : tab} onChange={setTab} keepMounted={false} className="inspector-tabs">
      <Tabs.List grow><Tabs.Tab value="proposition">Proposition</Tabs.Tab><Tabs.Tab value="field">Field State</Tabs.Tab>{proposition.tier === 'committal' && <Tabs.Tab value="closure">Closure</Tabs.Tab>}</Tabs.List>
      <Tabs.Panel value="field" p="md"><FieldStateChart proposition={proposition} /></Tabs.Panel>
      {proposition.tier === 'committal' && <Tabs.Panel value="closure" p="md"><ClosureHistoryChart proposition={proposition} /></Tabs.Panel>}
      <Tabs.Panel value="proposition"><Stack gap="md" p="md">
      {selection.kind && <Badge size="xs" color="gray" variant="outline" style={{ alignSelf: 'flex-start' }}>{selection.kind}</Badge>}

      <Box className="inspector-section">
        <PropositionContent
          proposition={{ ...proposition, title: selection.title || proposition.title }}
          mode="detailed"
          hideField
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
    </Stack></Tabs.Panel></Tabs>
  );
}


/** Non-modal viewport overlay. CSS turns this same shell into a bottom card on mobile. */
export function GraphInspectorShell({ onClose, onPrevious, onNext, children }: {
  onClose: () => void; onPrevious?: () => void; onNext?: () => void; children: ReactNode;
}) {
  return <Paper component="section" withBorder shadow="md" radius="md" className="graph-inspector"
    aria-label="Selected Proposition inspector" onKeyDown={event => {
      if (event.key === 'Escape') { event.stopPropagation(); onClose(); }
    }}>
    <Group className="graph-inspector__header" justify="space-between" wrap="nowrap" gap={4}>
      <Text fw={700} size="sm">Proposition inspector</Text>
      <Group gap={2} wrap="nowrap">
        <Tooltip label="Previous proposition"><ActionIcon variant="subtle" aria-label="Previous proposition" disabled={!onPrevious} onClick={onPrevious}><IconChevronLeft size={16} /></ActionIcon></Tooltip>
        <Tooltip label="Next proposition"><ActionIcon variant="subtle" aria-label="Next proposition" disabled={!onNext} onClick={onNext}><IconChevronRight size={16} /></ActionIcon></Tooltip>
        <Tooltip label="Deselect proposition"><ActionIcon variant="subtle" aria-label="Deselect proposition" onClick={onClose}><IconX size={17} /></ActionIcon></Tooltip>
      </Group>
    </Group>
    <Box className="graph-inspector__body">{children}</Box>
  </Paper>;
}
