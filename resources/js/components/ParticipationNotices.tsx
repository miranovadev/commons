import { Button, Stack, Text } from '@mantine/core';
import { IconAlertCircle, IconMoon, IconRoute } from '@tabler/icons-react';
import { messageFrom } from '../errors';
import type { Participation, ReadyParticipation } from '../types';
import type { WorkspaceNotice } from './WorkspaceStatus';

export function participationNotices({ participation, plan, canRest, error, onRest }: {
  participation: Participation; plan?: ReadyParticipation; canRest: boolean; error?: unknown; onRest: () => void;
}): WorkspaceNotice[] {
  const notices: WorkspaceNotice[] = [];
  const acquisition = plan?.context.gate.acquisition;
  if (acquisition?.role && acquisition.status !== 'not_required') notices.push({ id: 'personal-acquisition', title: acquisition.status === 'resolved' ? 'Acquisition attempt completed' : acquisition.status === 'pending' ? 'Acquisition in progress' : acquisition.status === 'awaiting_resolution' ? 'Choose Variant or Defer' : 'Acquire before creating a Variant',
    color: acquisition.status === 'resolved' ? 'teal' : 'indigo', satisfied: acquisition.status === 'resolved', icon: IconRoute,
    content: <Stack gap="xs">
      <Text size="sm">{acquisition.status === 'resolved' ? 'This Phase’s attempt is complete. Later Phases evaluate fresh opportunities independently.'
        : acquisition.status === 'awaiting_resolution' ? 'Choose Variant or Defer. Defer preserves your observation and completes this Cycle.'
        : acquisition.status === 'pending' ? 'Resolve the current execution before starting another acquisition.'
          : 'Explore existing structure first. Acquire for this fresh Variant opportunity, then choose Variant or Defer.'}</Text>
      {acquisition.status === 'required' && <Text size="xs">{plan?.phase.kind === 'persist' && plan.schema?.$defs?.actions?.['preparation.start']
        ? 'Prepare an accessible Artifact, then resolve the reading before continuing.'
        : plan?.phase.kind === 'disperse' ? 'Choose Instrument or an available Preparation; either admits this Cycle’s generative attempt.' : 'Choose an available Instrument.'}</Text>}
      {acquisition.outcome && <Text size="xs">Recorded outcome: {acquisition.outcome.action}</Text>}
    </Stack> });
  const onlyRest = Object.keys(plan?.schema?.$defs?.actions ?? {}).length === 1;
  if (canRest) notices.push({ id: 'rest', title: onlyRest ? 'No available move · Rest' : 'Encounter complete · Rest', color: 'gray', icon: IconMoon, content: <Stack gap="xs">
    <Text size="sm">{onlyRest ? 'No lawful non-Rest action remains in this Phase.' : 'You can Rest after this encounter or choose another available action.'} Rest ends this Cycle and begins recovery.</Text>
    <Button variant="light" color="gray" onClick={onRest}>Rest</Button>
  </Stack> });
  if (error) notices.push({ id: 'contribution-error', title: 'Contribution could not be applied', color: 'red', icon: IconAlertCircle, urgent: true,
    content: <Text size="sm" role="alert">{messageFrom(error, 'Please try again.')}</Text> });
  return notices;
}
