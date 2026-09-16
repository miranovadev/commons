import { Button, Stack, Text } from '@mantine/core';
import { IconAlertCircle, IconGitCommit, IconLock, IconLockOpen, IconMoon, IconRoute } from '@tabler/icons-react';
import { messageFrom } from '../errors';
import type { Participation, ParticipantPlan } from '../types';
import type { WorkspaceNotice } from './WorkspaceStatus';

export function participationNotices({ participation, plan, canCommit, canRest, error, onRest }: {
  participation: Participation; plan?: ParticipantPlan; canCommit: boolean; canRest: boolean; error?: unknown; onRest: () => void;
}): WorkspaceNotice[] {
  const notices: WorkspaceNotice[] = [];
  const last = participation.last_commitment;
  if (last) notices.push({ id: 'last-commitment', title: 'Latest Commitment', color: 'indigo', icon: IconGitCommit, content: <Stack gap="xs">
    <Text size="sm">{last.domain_before} → {last.domain_after} · {last.closure_boundary ? 'Closure Boundary created' : 'No Closure Boundary'}</Text>
    <Text size="xs">Tension: {last.tension_before ?? 'unrecorded'} → {last.tension_after}</Text>
    <Text size="xs">Depth: {last.depth ?? 'unrecorded'} ({last.depth_direction ?? 'unknown'}) · Cadence: {last.cadence ?? 'unrecorded'} ({last.cadence_direction ?? 'unknown'})</Text>
    <Text size="xs" c="dimmed">Current Domain: {participation.domain} · Horizon {participation.horizon_sequence}</Text>
  </Stack> });
  if (plan?.commitment) notices.push({ id: 'commitment', title: plan.commitment.requirements.phase_position === false ? 'No phase-6 candidate at this Gate' : plan.commitment.requirements.write_opportunity === false ? 'Source already committed' : canCommit ? 'Committable candidate' : 'No committable candidate',
    color: canCommit ? 'indigo' : 'gray', icon: canCommit ? IconLock : IconLockOpen, satisfied: canCommit, content: <Stack gap="xs">
    {canCommit && <Text size="sm">An eligible source is available to Commit in Gate.</Text>}
    {!canCommit && <Text size="sm">{participation.reason?.message}</Text>}
    {plan.commitment.requirements.phase_position === false && <Text size="xs">Commit requires a Bias-produced candidate in the current Commit Region. A matching register label does not change the producing phase.</Text>}
    <Text size="xs">Current Gate Attestation: {plan.commitment.requirements.attestation ? 'available' : 'missing'}.</Text>
    <Text size="xs">Current Gate Receipt: {plan.commitment.requirements.receipt ? 'available' : 'missing'}.</Text>
    {!canCommit && plan.commitment.requirements.write_opportunity !== false && <Text size="xs" c="dimmed">Grounding can be added in Bias and retained for a later Cycle. Use an available Phase action; the Closure conditions still apply.</Text>}
  </Stack> });
  if (plan?.commitment?.closure) { const closure = plan.commitment.closure;
    notices.push({ id: 'closure-preview', title: 'Gate Closure preview', color: plan.commitment.requirements.closure === true ? 'indigo' : 'gray', satisfied: plan.commitment.requirements.closure === true, icon: IconRoute, content: <Stack gap="xs">
      <Text size="sm">Depth: {closure.depth} ({closure.depth_direction})</Text>
      <Text size="sm">Cadence: {closure.cadence} ({closure.cadence_direction})</Text>
      <Text size="xs">{plan.commitment.requirements.closure ? 'Directions align.' : 'Directions diverge for this source. Other eligible sources appear in Gate.'}</Text>
      <Text size="xs" c="dimmed">Zero retains the previous committed direction. History: {closure.history_source}.</Text>
    </Stack> });
  }
  if (canRest) notices.push({ id: 'rest', title: 'Tragic Region · Rest', color: 'gray', icon: IconMoon, content: <Stack gap="xs">
    <Text size="sm">No admissible non-Rest action remains in this Phase. Rest ends this Cycle; recovery begins at the nearest viable Committal region.</Text>
    <Button variant="light" color="gray" onClick={onRest}>Rest</Button>
  </Stack> });
  if (error) notices.push({ id: 'contribution-error', title: 'Contribution could not be applied', color: 'red', icon: IconAlertCircle, urgent: true,
    content: <Text size="sm" role="alert">{messageFrom(error, 'Please try again.')}</Text> });
  return notices;
}
