import { useMemo, useRef, useState } from 'react';
import { Alert, Box, Breadcrumbs, Button, Container, Group, Loader, Stack, Text, ThemeIcon, Title } from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { IconAlertCircle, IconFocus2, IconSparkles } from '@tabler/icons-react';
import { Link, useParams } from 'react-router-dom';
import { messageFrom } from '../errors';
import { quickContribution } from '../participationOptions';
import { ContributionForm } from '../components/ContributionForm';
import { OriginCycleForm } from '../components/OriginCycleForm';
import { ParticipantWorkspace } from '../components/ParticipantWorkspace';
import { useContributeMutation, useEnterCycleMutation, useGetParticipantQuery, useGetParticipationQuery } from '../services/commonsApi';
import type { ActionName, ContributionInitialValues, ContributionInput, OriginInput, PhaseKind } from '../types';

import { phaseMeta } from '../phases';
import { ParticipantIdentity } from '../components/ParticipantIdentity';
import { WorkspaceStatus } from '../components/WorkspaceStatus';
import { participationNotices } from '../components/ParticipationNotices';

export function ParticipantPage() {
  const { slug: routeSlug, participantId: routeParticipantId } = useParams();
  const slug = routeSlug ?? '';
  const participantId = routeParticipantId ?? '';
  const parameters = { slug, participantId };
  const { data: participant, isLoading: participantLoading, error: participantError } = useGetParticipantQuery(parameters);
  const { data: participation, isLoading: participationLoading, error: participationError } = useGetParticipationQuery(parameters);
  const [contribute, contribution] = useContributeMutation();
  const submitting = useRef(false);
  const submittedPhase = useRef<string | null>(null);
  const [enterCycle, cycleEntry] = useEnterCycleMutation();
  const [selectedAction, setSelectedAction] = useState<{ name: ActionName; initialValues: ContributionInitialValues } | null>(null);
  const [originOpened, { open: openOrigin, close: closeOrigin }] = useDisclosure(false);
  const plan = participation?.plan;
  const error = participantError || participationError;
  const availableActions = useMemo(
    () => plan?.schema?.oneOf
      ?.map((entry) => entry.$ref?.split('/').pop())
      .filter((name): name is ActionName => isActionName(name)) || [],
    [plan],
  );

  function openAction(name: ActionName, initialValues: ContributionInitialValues = {}) { contribution.reset(); setSelectedAction({ name, initialValues }); }
  function workspaceAction(name: ActionName, initialValues: ContributionInitialValues = {}) {
    if (!plan || submitting.current || submittedPhase.current === plan.phase.id) return;
    const input = quickContribution(plan, name, initialValues);
    if (input) { contribution.reset(); void submit(input); }
    else openAction(name, initialValues);
  }
  async function submit(contributionInput: ContributionInput) {
    if (!plan || submitting.current || submittedPhase.current === plan.phase.id) return false;
    submitting.current = true;
    try {
      await contribute({ slug, participantId, phaseId: plan.phase.id, contribution: contributionInput }).unwrap();
      submittedPhase.current = plan.phase.id;
      setSelectedAction(null);
      return true;
    } catch { return false; /* mutation errors appear in the workspace or form */ }
    finally { submitting.current = false; }
  }
  async function establishOrigin(origin: OriginInput) {
    try { await enterCycle({ slug, participantId, origin }).unwrap(); closeOrigin(); } catch { /* mutation error is rendered by the form */ }
  }
  async function enterFromPotential() {
    try { await enterCycle({ slug, participantId }).unwrap(); } catch { /* mutation error is rendered below */ }
  }

  const notices = participation ? participationNotices({ participation, plan, canCommit: availableActions.includes('proposition.commit'),
    canRest: availableActions.includes('participant.rest'), error: !selectedAction ? contribution.error : undefined,
    onRest: () => openAction('participant.rest') }) : [];

  return (
    <Container fluid className={plan ? 'participant-page participant-page--active' : 'participant-page'} p={plan ? 0 : undefined}>
      {!plan && <Breadcrumbs mb="lg"><Text component={Link} to="/" size="sm" c="dimmed">Substrates</Text><Text component={Link} to={`/substrates/${slug}`} size="sm" c="dimmed">{slug}</Text><Text size="sm">Participant</Text></Breadcrumbs>}
      {(participantLoading || participationLoading) && !error && <Stack align="center" py={100}><Loader color="indigo" /><Text c="dimmed">Finding your current position…</Text></Stack>}
      {error && <Alert color="red" variant="light" icon={<IconAlertCircle size={18} />} title="The Commons could not load this position">{messageFrom(error, 'This participation state could not be loaded.')}</Alert>}
      {participant && participation?.status === 'waiting' && (
        <Box p="xl"><Stack align="flex-start" maw={680} mx="auto"><ThemeIcon color="indigo" variant="light" size="xl" radius="xl"><IconSparkles size={22} /></ThemeIcon><Box><Title order={2}>{participation.entry?.mode === 'origin' ? 'Every substrate begins somewhere' : 'Ready to enter the cycle'}</Title><Text c="dimmed" mt="xs">{participation.entry?.message || participation.reason?.message}</Text></Box>{cycleEntry.error && <Alert color="red" variant="light">{messageFrom(cycleEntry.error, 'The cycle could not be entered.')}</Alert>}<Group>{participation.entry?.mode === 'origin' && participation.entry?.available && <Button color="indigo" onClick={() => { cycleEntry.reset(); openOrigin(); }}>Establish the Origin</Button>}{participation.entry?.mode === 'potential' && participation.entry?.available && <Button color="indigo" loading={cycleEntry.isLoading} onClick={enterFromPotential}>{participation.entry?.selection === 'recovery' ? 'Begin recovery Cycle' : 'Enter Cycle'}</Button>}</Group></Stack></Box>
      )}
      {!plan && notices.map(notice => <Alert key={notice.id} color={notice.color} title={notice.title} m="md">{notice.content}</Alert>)}
      {participant && plan && (
        <Box className="participant-cycle-shell">
          <Box className="cycle-context-header">
            <ParticipantIdentity participant={participant} />
            <PhaseLabel kind={plan.phase.kind} />
            <Box />
          </Box>
          <Box className="participant-cycle-content">
            <ParticipantWorkspace plan={{ ...plan, perspectiveLimit: participant.max_read_count }} availableActions={availableActions} onAction={workspaceAction}
              busy={contribution.isLoading || submittedPhase.current === plan.phase.id} onSubmit={submit} error={contribution.error} />
            <WorkspaceStatus notices={notices} closeDetails={selectedAction !== null} />
          </Box>
          <ContributionForm action={selectedAction?.name} plan={plan} opened={selectedAction !== null} loading={contribution.isLoading} error={contribution.error} initialValues={selectedAction?.initialValues} onClose={() => setSelectedAction(null)} onSubmit={submit} />
        </Box>
      )}
      <OriginCycleForm opened={originOpened} loading={cycleEntry.isLoading} error={cycleEntry.error} onClose={closeOrigin} onSubmit={establishOrigin} />
    </Container>
  );
}

export function PhaseLabel({ kind }: { kind: PhaseKind }) {
  const meta = phaseMeta[kind];
  const Icon = meta?.icon || IconFocus2;
  const name = kind ? kind.charAt(0).toUpperCase() + kind.slice(1) : 'Unknown';
  return (
    <Stack className="cycle-context-header__phase" gap={3}>
      <Group gap={7} justify="center" wrap="nowrap"><Icon size={17} /><Text size="sm" fw={650}>{meta?.number ? `Phase ${meta.number} · ${name}` : name}</Text></Group>
      <Text size="xs" c="dimmed" maw={540}>{meta?.description}</Text>
    </Stack>
  );
}

function isActionName(value?: string): value is ActionName {
  return [
    'attestation.create',
    'instrument.execute',
    'proposition.commit',
    'proposition.reclassify',
    'proposition.reconcile',
    'proposition.bias',
    'proposition.revise',
    'proposition.traverse',
    'participant.rest',
    'proposition.variant',
  ].includes(value as ActionName);
}
