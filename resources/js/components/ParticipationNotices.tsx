import { Button, Stack, Text } from '@mantine/core';
import { IconAlertCircle, IconGitCommit, IconLock, IconLockOpen, IconMoon, IconRoute } from '@tabler/icons-react';
import { messageFrom } from '../errors';
import type { Participation, ParticipantPlan } from '../types';
import type { WorkspaceNotice } from './WorkspaceStatus';

export function participationNotices({ participation, plan, canCommit, canRest, error, onRest }: {
  participation: Participation; plan?: ParticipantPlan; canCommit: boolean; canRest: boolean; error?: unknown; onRest: () => void;
}): WorkspaceNotice[] {
  const notices: WorkspaceNotice[] = [];
  const acquisition = plan?.acquisition;
  if (acquisition?.role && acquisition.status !== 'not_required') notices.push({ id: 'personal-acquisition', title: acquisition.status === 'resolved' ? 'Acquisition attempt completed' : acquisition.status === 'pending' ? 'Acquisition in progress' : acquisition.status === 'awaiting_resolution' ? 'Choose Variant or Defer' : 'Acquire before creating a Variant',
    color: acquisition.status === 'resolved' ? 'teal' : 'indigo', satisfied: acquisition.status === 'resolved', icon: IconRoute,
    content: <Stack gap="xs">
      <Text size="sm">{acquisition.status === 'resolved' ? 'This Cycle’s attempt is complete. Later Cycles evaluate fresh opportunities independently.'
        : acquisition.status === 'awaiting_resolution' ? 'Choose Variant or Defer. Defer preserves your observation and completes this Cycle.'
        : acquisition.status === 'pending' ? 'Resolve the current execution before starting another acquisition.'
          : 'Explore existing structure first. Acquire for this fresh Variant opportunity, then choose Variant or Defer.'}</Text>
      {acquisition.status === 'required' && <Text size="xs">{plan?.phase.kind === 'persist' && plan.schema?.$defs?.actions?.['preparation.start']
        ? 'Prepare an accessible Artifact, then resolve the reading before continuing.'
        : plan?.phase.kind === 'disperse' ? 'Choose Instrument or an available Preparation; either admits this Cycle’s generative attempt.' : 'Choose an available Instrument.'}</Text>}
      {acquisition.outcome && <Text size="xs">Recorded outcome: {acquisition.outcome.action}</Text>}
    </Stack> });
  const correspondence = plan?.correspondence_admissibility;
  const chirality = plan?.chirality_admissibility;
  const coherence = [
    { id: 'correspondence-admissibility', name: 'Correspondence', action: 'Reconcile', state: correspondence,
      first: 'Shape Topology', firstDirection: correspondence?.shape_topology_direction,
      second: 'Phase Profile', secondDirection: correspondence?.phase_profile_direction },
    { id: 'chirality-admissibility', name: 'Chirality', action: 'Reclassify', state: chirality,
      first: 'Orientation', firstDirection: chirality?.orientation_direction,
      second: 'Attestation Cadence', secondDirection: chirality?.attestation_cadence_direction },
  ];
  for (const item of coherence) {
    if (!item.state) continue;
    notices.push({ id: item.id, title: `Gate ${item.name}`, color: item.state.admissible ? 'indigo' : 'gray',
      satisfied: item.state.admissible, icon: IconRoute, content: <Stack gap="xs">
        <Text size="sm">{item.first}: {item.firstDirection ?? 'unavailable'}</Text>
        <Text size="sm">{item.second}: {item.secondDirection ?? 'unavailable'}</Text>
        <Text size="xs">{item.state.admissible
          ? 'Component directions align. Other Write requirements still apply.'
          : `${item.action} unavailable from this Gate. ${item.state.reason === 'component_directions_unavailable' ? 'Component directions are unavailable.' : 'Component directions disagree.'}`}</Text>
        <Text size="xs" c="dimmed">Zero uses retained component directions. The Gate remains readable.</Text>
      </Stack> });
  }
  const last = participation.last_commitment;
  if (last) notices.push({ id: 'last-commitment', title: 'Latest Commitment', color: 'indigo', icon: IconGitCommit, content: <Stack gap="xs">
    <Text size="sm">{last.domain_before} → {last.domain_after} · {last.closure_boundary ? 'Closure Boundary created' : 'No Closure Boundary'}</Text>
    <Text size="xs">Tension: {last.tension_before ?? 'unrecorded'} → {last.tension_after}</Text>
    <Text size="xs">Historical Extent: {last.historical_extent ?? 'unrecorded'} ({last.historical_extent_direction ?? 'unknown'}) · Historical Rhythm: {last.historical_rhythm ?? 'unrecorded'} ({last.historical_rhythm_direction ?? 'unknown'})</Text>
    <Text size="xs" c="dimmed">Current Domain: {participation.domain} · Horizon {participation.horizon_sequence}</Text>
  </Stack> });
  if (plan?.commitment) notices.push({ id: 'commitment', title: plan.commitment.requirements.phase_position === false ? 'No phase-6 candidate at this Gate' : plan.commitment.requirements.write_opportunity === false ? 'Source already committed' : canCommit ? 'Committable candidate' : 'No committable candidate',
    color: canCommit ? 'indigo' : 'gray', icon: canCommit ? IconLock : IconLockOpen, satisfied: canCommit, content: <Stack gap="xs">
    {canCommit && <Text size="sm">An eligible source is available to Commit in Write.</Text>}
    {!canCommit && <Text size="sm">{participation.reason?.message}</Text>}
    {plan.commitment.requirements.phase_position === false && <Text size="xs">Commit requires a Bias-produced candidate in the current Commit Region. A matching register label does not change the producing phase.</Text>}
    <Text size="xs">Current Gate Attestation: {plan.commitment.requirements.attestation ? 'available' : 'missing'}.</Text>
    <Text size="xs">Current Gate Receipt: {plan.commitment.requirements.receipt ? 'available' : 'missing'}.</Text>
    {!canCommit && plan.commitment.requirements.write_opportunity !== false && <Text size="xs" c="dimmed">Grounding can be added in Bias and retained for a later Cycle. Use an available Phase action; the Closure conditions still apply.</Text>}
  </Stack> });
  if (plan?.commitment?.closure) { const closure = plan.commitment.closure;
    notices.push({ id: 'closure-preview', title: 'Write Closure preview', color: plan.commitment.requirements.closure === true ? 'indigo' : 'gray', satisfied: plan.commitment.requirements.closure === true, icon: IconRoute, content: <Stack gap="xs">
      <Text size="sm">Historical Extent: {closure.historical_extent ?? 'unavailable'} ({closure.historical_extent_direction ?? 'unavailable'})</Text>
      <Text size="sm">Historical Rhythm: {closure.historical_rhythm ?? 'unavailable'} ({closure.historical_rhythm_direction ?? 'unavailable'})</Text>
      <Text size="xs">{plan.commitment.requirements.closure ? 'Directions align.' : 'Commit unavailable from this Write. See its retained directions and Commitment requirements.'}</Text>
      <Text size="xs" c="dimmed">Zero retains each component’s established direction. Commit preserves this authorised state.</Text>
    </Stack> });
  }
  if (canRest) notices.push({ id: 'rest', title: plan?.rest_reason === 'no_remaining_potential' ? 'No remaining Potential · Rest' : 'No available move · Rest', color: 'gray', icon: IconMoon, content: <Stack gap="xs">
    <Text size="sm">No admissible non-Rest action remains in this Phase. Rest ends this Cycle; recovery begins at the nearest viable Committal region.</Text>
    <Button variant="light" color="gray" onClick={onRest}>Rest</Button>
  </Stack> });
  if (error) notices.push({ id: 'contribution-error', title: 'Contribution could not be applied', color: 'red', icon: IconAlertCircle, urgent: true,
    content: <Text size="sm" role="alert">{messageFrom(error, 'Please try again.')}</Text> });
  return notices;
}
