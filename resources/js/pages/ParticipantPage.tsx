import { useParticipantRealtime } from '../realtime/useRealtime';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Alert, Box, Breadcrumbs, Button, Container, Group, Loader, Stack, Text, ThemeIcon, Title } from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { IconAlertCircle, IconSparkles } from '@tabler/icons-react';
import { Link, useParams } from 'react-router-dom';
import { messageFrom } from '../errors';
import { quickContribution } from '../participationOptions';
import { PreparationPanel } from '../components/PreparationPanel';
import { ContributionForm } from '../components/ContributionForm';
import { OriginCycleForm } from '../components/OriginCycleForm';
import { ParticipantWorkspace } from '../components/ParticipantWorkspace';
import { useContributeMutation, useEnterCycleMutation, useGetParticipationQuery } from '../services/commonsApi';
import type { ActionName, ContributionInitialValues, ContributionInput, OriginInput, ReadyParticipation } from '../types';

import { CycleCompletion } from '../components/CycleCompletion';
import { PhaseIdentity } from '../components/PhaseIdentity';
import { ParticipantSwitcher } from '../components/ParticipantSwitcher';
import { CycleEntryControl } from '../components/CycleEntryControl';
import { ParticipantIdentity } from '../components/ParticipantIdentity';
import { WorkspaceStatus } from '../components/WorkspaceStatus';
import { participationNotices } from '../components/ParticipationNotices';
import { useBackgroundParticipation } from '../useBackgroundParticipation';

export function ParticipantPage() {
  const { slug: routeSlug, participantId: routeParticipantId } = useParams();
  const slug = routeSlug ?? '';
  const participantId = routeParticipantId ?? '';
  return <ParticipantPositionPage key={`${slug}/${participantId}`} slug={slug} participantId={participantId} />;
}

function ParticipantPositionPage({ slug, participantId }: { slug: string; participantId: string }) {
  const parameters = { slug, participantId };
  const [switcherOpened, setSwitcherOpened] = useState(false);
  const { data: currentParticipation, isLoading: participationLoading, isFetching: participationFetching, error: participationError, refetch: refetchParticipation } = useGetParticipationQuery(parameters);
  const { participation, synchronising } = useBackgroundParticipation(currentParticipation);
  useParticipantRealtime(slug, participantId, Boolean(participation), participation?.context_horizon_id);
  useEffect(() => {
    if (participationFetching || !synchronising) return;
    const timer = setTimeout(() => { void refetchParticipation(); }, 2000);
    return () => clearTimeout(timer);
  }, [synchronising, participationFetching, refetchParticipation]);
  const [contribute, contribution] = useContributeMutation();
  const submitting = useRef(false);
  const submittedPhase = useRef<string | null>(null);
  const [enterCycle, cycleEntry] = useEnterCycleMutation();
  const [selectedAction, setSelectedAction] = useState<{ name: ActionName; initialValues: ContributionInitialValues; plan: ReadyParticipation } | null>(null);
  const [originOpened, { open: openOrigin, close: closeOrigin }] = useDisclosure(false);
  const plan = participation?.status === 'ready' ? participation : undefined;
  const participant = plan?.context.participant ?? participation?.participant;
  const awaitingPresentation = Boolean(plan?.preparation && plan.preparation.status !== 'presented');
  const [completedWorkspace, setCompletedWorkspace] = useState<{ plan: ReadyParticipation; phaseId: string } | null>(null);
  const completed = participation?.status === 'waiting' && participation.completion?.phase_id === completedWorkspace?.phaseId ? completedWorkspace : null;
  const displayPlan = completed?.plan || plan;
  const error = participationError;
  const availableActions = useMemo(
    () => plan?.schema?.oneOf
      ?.map((entry) => entry.$ref?.split('/').pop())
      .filter((name): name is ActionName => isActionName(name)) || [],
    [plan],
  );

  function openAction(name: ActionName, initialValues: ContributionInitialValues = {}) {
    if (!plan || awaitingPresentation || !availableActions.includes(name)) return;
    contribution.reset(); setSelectedAction({ name, initialValues, plan });
  }
  function workspaceAction(name: ActionName, initialValues: ContributionInitialValues = {}) {
    if (awaitingPresentation || !plan || submitting.current || submittedPhase.current === plan.phase.id) return;
    const input = quickContribution(plan, name, initialValues);
    if (input) { contribution.reset(); void submit(input); }
    else openAction(name, initialValues);
  }
  async function submit(contributionInput: ContributionInput) {
    // Preserve the draft's Phase/Closure identity; the server decides if it is still lawful.
    const submissionPlan = selectedAction?.plan ?? plan;
    if ((!selectedAction && awaitingPresentation) || !submissionPlan || submitting.current || submittedPhase.current === submissionPlan.phase.id) return false;
    submitting.current = true;
    try {
      const result = await contribute({ slug, participantId, phaseId: submissionPlan.phase.id, contribution: contributionInput, expectedClosure: submissionPlan.schema?.$comment }).unwrap();
      if (result.status === 'waiting' && result.completion?.phase_id === submissionPlan.phase.id) {
        setCompletedWorkspace({ plan: submissionPlan, phaseId: submissionPlan.phase.id });
      }
      submittedPhase.current = result.phase?.id === submissionPlan.phase.id || result.reason?.code === 'potential_projection_pending' ? null : submissionPlan.phase.id;
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

  const notices = participation ? participationNotices({ participation, plan,
    canRest: availableActions.includes('participant.rest'), error: !selectedAction ? contribution.error : undefined,
    onRest: () => openAction('participant.rest') }) : [];

  return (
    <Container fluid className={displayPlan ? 'participant-page participant-page--active' : 'participant-page'} p={displayPlan ? 0 : undefined}>
      {!displayPlan && <Breadcrumbs mb="lg"><Text component={Link} to="/" size="sm" c="dimmed">Substrates</Text><Text component={Link} to={`/substrates/${slug}`} size="sm" c="dimmed">{slug}</Text><Text size="sm">Participant</Text></Breadcrumbs>}
      {participationLoading && !error && <Stack align="center" py={100}><Loader color="indigo" /><Text c="dimmed">Finding your current position…</Text></Stack>}
      {error && <Alert color="red" variant="light" icon={<IconAlertCircle size={18} />} title="The Commons could not load this position">{messageFrom(error, 'This participation state could not be loaded.')}</Alert>}
      {(synchronising || participationFetching) && displayPlan && <Text size="xs" c="dimmed" px="md" role="status">Refreshing available options in the background. You can keep working.</Text>}
      {participant && !displayPlan && <ParticipantIdentity participant={participant} onSelect={() => setSwitcherOpened(true)} opened={switcherOpened} />}
      {participant && participation?.status === 'waiting' && !completed && (
        <Box p="xl"><Stack align="flex-start" maw={680} mx="auto"><ThemeIcon color="indigo" variant="light" size="xl" radius="xl"><IconSparkles size={22} /></ThemeIcon><Box><Title order={2}>{participation.reason?.code === 'potential_projection_pending' ? 'Updating your position' : participation.entry?.mode === 'origin' ? 'Every substrate begins somewhere' : 'Ready to enter the cycle'}</Title></Box>{cycleEntry.error && <Alert color="red" variant="light">{messageFrom(cycleEntry.error, 'The cycle could not be entered.')}</Alert>}<CycleEntryControl participation={participation} loading={cycleEntry.isLoading} onEnter={enterFromPotential} onOrigin={() => { cycleEntry.reset(); openOrigin(); }} /></Stack></Box>
      )}
      {!displayPlan && notices.map(notice => <Alert key={notice.id} color={notice.color} title={notice.title} m="md">{notice.content}</Alert>)}
      {participant && displayPlan && (
        <Box className="participant-cycle-shell">
          <Box className="cycle-context-header">
            <PhaseIdentity kind={displayPlan.phase.kind} />
            <ParticipantIdentity participant={participant} onSelect={() => setSwitcherOpened(true)} opened={switcherOpened} />
          </Box>
          <Box className="participant-cycle-content">
            <ParticipantWorkspace plan={displayPlan} availableActions={completed ? [] : availableActions} onAction={workspaceAction} onForm={openAction}
              busy={awaitingPresentation || contribution.isLoading || (!completed && submittedPhase.current === displayPlan.phase.id)}
              preparation={plan?.preparation && <PreparationPanel key={plan.preparation.execution_id} slug={slug} participantId={participantId} preparation={plan.preparation} onDefer={availableActions.includes('preparation.defer') ? () => workspaceAction('preparation.defer') : undefined} />}
              completion={completed && participation ? <CycleCompletion slug={slug} participation={participation} loading={cycleEntry.isLoading} error={cycleEntry.error} onEnter={enterFromPotential} onOrigin={openOrigin} /> : undefined}
              status={completed ? undefined : <WorkspaceStatus notices={notices} closeDetails={selectedAction !== null || switcherOpened} />} />
          </Box>
        </Box>
      )}
      {selectedAction && <ContributionForm action={selectedAction.name} plan={selectedAction.plan}
        opened loading={contribution.isLoading} error={contribution.error} initialValues={selectedAction.initialValues} onClose={() => setSelectedAction(null)} onSubmit={submit} />}
      {participant && <ParticipantSwitcher slug={slug} participant={participant} opened={switcherOpened} onClose={() => setSwitcherOpened(false)} />}
      <OriginCycleForm opened={originOpened} loading={cycleEntry.isLoading} error={cycleEntry.error} onClose={closeOrigin} onSubmit={establishOrigin} />
    </Container>
  );
}

function isActionName(value?: string): value is ActionName {
  return [
    'attestation.create',
    'instrument.execute',
    'preparation.start',
    'preparation.defer',
    'acquisition.defer',
    'proposition.commit',
    'proposition.reclassify',
    'proposition.reconcile',
    'proposition.revise',
    'proposition.traverse',
    'participant.rest',
    'proposition.variant',
  ].includes(value as ActionName);
}
