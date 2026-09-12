import { useMemo, useRef, useState } from 'react';
import { Alert, Box, Breadcrumbs, Button, Container, Group, Loader, Stack, Text, ThemeIcon, Title } from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { IconAlertCircle, IconSparkles } from '@tabler/icons-react';
import { Link, useParams } from 'react-router-dom';
import { messageFrom } from '../errors';
import { quickContribution } from '../participationOptions';
import { ContributionForm } from '../components/ContributionForm';
import { OriginCycleForm } from '../components/OriginCycleForm';
import { ParticipantWorkspace } from '../components/ParticipantWorkspace';
import { useContributeMutation, useEnterCycleMutation, useGetParticipantQuery, useGetParticipationQuery } from '../services/commonsApi';
import type { ActionName, ContributionInitialValues, ContributionInput, OriginInput, ParticipantPlan, PhaseKind } from '../types';

import { CycleCompletion } from '../components/CycleCompletion';
import { canPreviewPhase } from '../phasePreview';
import { PhaseIdentity } from '../components/PhaseIdentity';
import { ParticipantSwitcher } from '../components/ParticipantSwitcher';
import { CycleEntryControl } from '../components/CycleEntryControl';
import { ParticipantIdentity } from '../components/ParticipantIdentity';
import { WorkspaceStatus } from '../components/WorkspaceStatus';
import { participationNotices } from '../components/ParticipationNotices';

export function ParticipantPage() {
  const { slug: routeSlug, participantId: routeParticipantId } = useParams();
  const slug = routeSlug ?? '';
  const participantId = routeParticipantId ?? '';
  return <ParticipantPositionPage key={`${slug}/${participantId}`} slug={slug} participantId={participantId} />;
}

function ParticipantPositionPage({ slug, participantId }: { slug: string; participantId: string }) {
  const parameters = { slug, participantId };
  const [switcherOpened, setSwitcherOpened] = useState(false);
  const { data: participant, isLoading: participantLoading, error: participantError } = useGetParticipantQuery(parameters);
  const { data: participation, isLoading: participationLoading, error: participationError } = useGetParticipationQuery(parameters);
  const [contribute, contribution] = useContributeMutation();
  const submitting = useRef(false);
  const submittedPhase = useRef<string | null>(null);
  const [enterCycle, cycleEntry] = useEnterCycleMutation();
  const [selectedAction, setSelectedAction] = useState<{ name: ActionName; initialValues: ContributionInitialValues } | null>(null);
  const [originOpened, { open: openOrigin, close: closeOrigin }] = useDisclosure(false);
  const plan = participation?.plan;
  const [completedWorkspace, setCompletedWorkspace] = useState<{ plan: ParticipantPlan; phaseId: string } | null>(null);
  const completed = participation?.status === 'waiting' && participation.completion?.phase_id === completedWorkspace?.phaseId ? completedWorkspace : null;
  const displayPlan = completed?.plan || plan;
  const [previewSelection, setPreviewSelection] = useState<{ phaseId: string; kind: PhaseKind } | null>(null);
  const previewKind = plan && previewSelection?.phaseId === plan.phase.id && canPreviewPhase(plan.phase.kind, previewSelection.kind) ? previewSelection.kind : undefined;
  function selectPhase(kind: PhaseKind) {
    if (!plan || submitting.current || submittedPhase.current === plan.phase.id) return;
    setSelectedAction(null);
    setPreviewSelection(canPreviewPhase(plan.phase.kind, kind) ? { phaseId: plan.phase.id, kind } : null);
  }
  const error = participantError || participationError;
  const availableActions = useMemo(
    () => plan?.schema?.oneOf
      ?.map((entry) => entry.$ref?.split('/').pop())
      .filter((name): name is ActionName => isActionName(name)) || [],
    [plan],
  );

  function openAction(name: ActionName, initialValues: ContributionInitialValues = {}) { contribution.reset(); setSelectedAction({ name, initialValues }); }
  function workspaceAction(name: ActionName, initialValues: ContributionInitialValues = {}) {
    if (previewKind || !plan || submitting.current || submittedPhase.current === plan.phase.id) return;
    const input = quickContribution(plan, name, initialValues);
    if (input) { contribution.reset(); void submit(input); }
    else openAction(name, initialValues);
  }
  async function submit(contributionInput: ContributionInput) {
    if (previewKind || !plan || submitting.current || submittedPhase.current === plan.phase.id) return false;
    submitting.current = true;
    try {
      const result = await contribute({ slug, participantId, phaseId: plan.phase.id, contribution: contributionInput }).unwrap();
      if (result.status === 'waiting' && !result.plan && result.completion?.phase_id === plan.phase.id) {
        setCompletedWorkspace({ plan, phaseId: plan.phase.id });
      }
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
    <Container fluid className={displayPlan ? 'participant-page participant-page--active' : 'participant-page'} p={displayPlan ? 0 : undefined}>
      {!displayPlan && <Breadcrumbs mb="lg"><Text component={Link} to="/" size="sm" c="dimmed">Substrates</Text><Text component={Link} to={`/substrates/${slug}`} size="sm" c="dimmed">{slug}</Text><Text size="sm">Participant</Text></Breadcrumbs>}
      {(participantLoading || participationLoading) && !error && <Stack align="center" py={100}><Loader color="indigo" /><Text c="dimmed">Finding your current position…</Text></Stack>}
      {error && <Alert color="red" variant="light" icon={<IconAlertCircle size={18} />} title="The Commons could not load this position">{messageFrom(error, 'This participation state could not be loaded.')}</Alert>}
      {participant && !displayPlan && <ParticipantIdentity participant={participant} onSelect={() => setSwitcherOpened(true)} opened={switcherOpened} />}
      {participant && participation?.status === 'waiting' && !completed && (
        <Box p="xl"><Stack align="flex-start" maw={680} mx="auto"><ThemeIcon color="indigo" variant="light" size="xl" radius="xl"><IconSparkles size={22} /></ThemeIcon><Box><Title order={2}>{participation.entry?.mode === 'origin' ? 'Every substrate begins somewhere' : 'Ready to enter the cycle'}</Title></Box>{cycleEntry.error && <Alert color="red" variant="light">{messageFrom(cycleEntry.error, 'The cycle could not be entered.')}</Alert>}<CycleEntryControl participation={participation} loading={cycleEntry.isLoading} onEnter={enterFromPotential} onOrigin={() => { cycleEntry.reset(); openOrigin(); }} /></Stack></Box>
      )}
      {!displayPlan && notices.map(notice => <Alert key={notice.id} color={notice.color} title={notice.title} m="md">{notice.content}</Alert>)}
      {participant && displayPlan && (
        <Box className="participant-cycle-shell">
          <Box className="cycle-context-header">
            <PhaseIdentity kind={displayPlan.phase.kind} selectedKind={previewKind || displayPlan.phase.kind} onSelect={completed ? undefined : selectPhase} busy={contribution.isLoading || (!completed && submittedPhase.current === displayPlan.phase.id)} />
            <ParticipantIdentity participant={participant} onSelect={() => setSwitcherOpened(true)} opened={switcherOpened} />
          </Box>
          {previewKind && <Group className="phase-preview-notice" justify="space-between" gap="xs">
            <Text size="xs" c="dimmed" role="status">Read-only register preview · using loaded cards and lineage, not a historical snapshot.</Text>
            <Button size="compact-xs" variant="light" color="indigo" onClick={() => setPreviewSelection(null)}>Return to current Phase</Button>
          </Group>}
          <Box className="participant-cycle-content">
            <ParticipantWorkspace key={previewKind || 'live'} previewKind={previewKind} plan={{ ...displayPlan, perspectiveLimit: participant.max_read_count }} availableActions={previewKind || completed ? [] : availableActions} onAction={workspaceAction}
              busy={contribution.isLoading || (!completed && submittedPhase.current === displayPlan.phase.id)} onSubmit={previewKind || completed ? undefined : submit} error={contribution.error}
              completion={completed && participation ? <CycleCompletion slug={slug} participation={participation} loading={cycleEntry.isLoading} error={cycleEntry.error} onEnter={enterFromPotential} onOrigin={openOrigin} /> : undefined}
              status={previewKind || completed ? undefined : <WorkspaceStatus notices={notices} closeDetails={selectedAction !== null || switcherOpened} />} />
          </Box>
          {plan && <ContributionForm action={selectedAction?.name} plan={plan} opened={selectedAction !== null} loading={contribution.isLoading} error={contribution.error} initialValues={selectedAction?.initialValues} onClose={() => setSelectedAction(null)} onSubmit={submit} />}
        </Box>
      )}
      {participant && <ParticipantSwitcher slug={slug} participant={participant} opened={switcherOpened} onClose={() => setSwitcherOpened(false)} />}
      <OriginCycleForm opened={originOpened} loading={cycleEntry.isLoading} error={cycleEntry.error} onClose={closeOrigin} onSubmit={establishOrigin} />
    </Container>
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
