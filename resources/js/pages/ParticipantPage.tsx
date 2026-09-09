import { useMemo, useState } from 'react';
import { Alert, Badge, Box, Breadcrumbs, Button, Container, Group, Loader, Stack, Text, ThemeIcon, Title } from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { IconAdjustmentsHorizontal, IconAlertCircle, IconArrowsJoin, IconArrowsSplit, IconFilter, IconFocus2, IconLock, IconRepeat, IconSparkles, IconUserHexagon } from '@tabler/icons-react';
import { Link, useParams } from 'react-router-dom';
import { messageFrom } from '../errors';
import { ContributionForm } from '../components/ContributionForm';
import { OriginCycleForm } from '../components/OriginCycleForm';
import { ParticipantWorkspace } from '../components/ParticipantWorkspace';
import { useContributeMutation, useEnterCycleMutation, useGetParticipantQuery, useGetParticipationQuery } from '../services/commonsApi';
import type { ActionName, ContributionInitialValues, ContributionInput, OriginInput, PhaseKind } from '../types';

const phaseMeta = {
  distinguish: { number: 1, icon: IconFocus2 },
  persist: { number: 2, icon: IconRepeat },
  constrain: { number: 3, icon: IconFilter },
  disperse: { number: 4, icon: IconArrowsSplit },
  mediate: { number: 5, icon: IconArrowsJoin },
  bias: { number: 6, icon: IconAdjustmentsHorizontal },
  order: { number: 7, icon: IconLock },
};

export function ParticipantPage() {
  const { slug: routeSlug, participantId: routeParticipantId } = useParams();
  const slug = routeSlug ?? '';
  const participantId = routeParticipantId ?? '';
  const parameters = { slug, participantId };
  const { data: participant, isLoading: participantLoading, error: participantError } = useGetParticipantQuery(parameters);
  const { data: participation, isLoading: participationLoading, error: participationError } = useGetParticipationQuery(parameters);
  const [contribute, contribution] = useContributeMutation();
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
  async function submit(contributionInput: ContributionInput) {
    if (!plan) return;
    try { await contribute({ slug, participantId, phaseId: plan.phase.id, contribution: contributionInput }).unwrap(); setSelectedAction(null); } catch { /* mutation error is rendered by the form */ }
  }
  async function establishOrigin(origin: OriginInput) {
    try { await enterCycle({ slug, participantId, origin }).unwrap(); closeOrigin(); } catch { /* mutation error is rendered by the form */ }
  }
  async function enterFromPotential() {
    try { await enterCycle({ slug, participantId }).unwrap(); } catch { /* mutation error is rendered below */ }
  }

  return (
    <Container fluid className={plan ? 'participant-page participant-page--active' : 'participant-page'} p={plan ? 0 : undefined}>
      {!plan && <Breadcrumbs mb="lg"><Text component={Link} to="/" size="sm" c="dimmed">Substrates</Text><Text component={Link} to={`/substrates/${slug}`} size="sm" c="dimmed">{slug}</Text><Text size="sm">Participant</Text></Breadcrumbs>}
      {(participantLoading || participationLoading) && !error && <Stack align="center" py={100}><Loader color="indigo" /><Text c="dimmed">Finding your current position…</Text></Stack>}
      {error && <Alert color="red" variant="light" icon={<IconAlertCircle size={18} />} title="The Commons could not load this position">{messageFrom(error, 'This participation state could not be loaded.')}</Alert>}
      {participant && participation?.status === 'waiting' && (
        <Box p="xl"><Stack align="flex-start" maw={680} mx="auto"><ThemeIcon color="indigo" variant="light" size="xl" radius="xl"><IconSparkles size={22} /></ThemeIcon><Box><Title order={2}>{participation.entry?.mode === 'origin' ? 'Every substrate begins somewhere' : 'Ready to enter the cycle'}</Title><Text c="dimmed" mt="xs">{participation.entry?.message || participation.reason?.message}</Text></Box>{cycleEntry.error && <Alert color="red" variant="light">{messageFrom(cycleEntry.error, 'The cycle could not be entered.')}</Alert>}<Group>{participation.entry?.mode === 'origin' && participation.entry?.available && <Button color="indigo" onClick={() => { cycleEntry.reset(); openOrigin(); }}>Establish the Origin</Button>}{participation.entry?.mode === 'potential' && participation.entry?.available && <Button color="indigo" loading={cycleEntry.isLoading} onClick={enterFromPotential}>{participation.entry?.selection === 'recovery' ? 'Begin recovery Cycle' : 'Enter Cycle'}</Button>}</Group></Stack></Box>
      )}
      {participation?.last_commitment && (
        <Alert color="indigo" title="Your latest Commitment" m="md">
          <Text size="sm">{participation.last_commitment.domain_before} → {participation.last_commitment.domain_after}
            {' · '}{participation.last_commitment.closure_boundary ? 'Closure Boundary created' : 'No Closure Boundary'}</Text>
          <Text size="sm">Tension: {participation.last_commitment.tension_before ?? 'unrecorded'} → {participation.last_commitment.tension_after}</Text>
          <Text size="xs">Depth: {participation.last_commitment.depth ?? 'unrecorded'} ({participation.last_commitment.depth_direction ?? 'unknown'})
            {' · '}Cadence: {participation.last_commitment.cadence ?? 'unrecorded'} ({participation.last_commitment.cadence_direction ?? 'unknown'})</Text>
          <Text size="xs">Current Domain: {participation.domain} · Horizon {participation.horizon_sequence}</Text>
        </Alert>
      )}
      {participant && plan && (
        <Box className="participant-cycle-shell">
          <Box className="cycle-context-header">
            <Stack className="cycle-context-header__participant" gap={2} miw={0}>
              <Badge color="indigo" variant="light" size="xs" leftSection={<IconUserHexagon size={11} />}>Participant</Badge>
              <Text fw={750} truncate>{participant.callsign}</Text>
              <Text size="xs" c="dimmed" truncate>{slug}</Text>
            </Stack>
            <PhaseLabel kind={plan.phase.kind} />
            <Box />
          </Box>
          {plan.commitment && !plan.commitment.admissible && (
            <Alert color="yellow" title="Commitment is blocked" icon={<IconLock size={18} />} m="md">
              <Stack gap="xs">
                <Text size="sm">{participation.reason?.message}</Text>
                <Text size="sm">Effective Attestation: {plan.commitment.requirements.attestation ? 'available' : 'missing'}.</Text>
                <Text size="sm">Completed Receipt: {plan.commitment.requirements.receipt ? 'available' : 'missing'}.</Text>
                <Text size="xs">Grounding can be added in Bias and retained for a later Cycle. Use the available Phase actions; the Closure gate still applies.</Text>
              </Stack>
            </Alert>
          )}
          {plan.commitment?.closure && (
            <Alert color={plan.commitment.requirements.closure ? 'indigo' : 'yellow'} title="Prospective Closure gate" m="md">
              <Text size="sm">Depth: {plan.commitment.closure.depth} ({plan.commitment.closure.depth_direction})
                {' · '}Cadence: {plan.commitment.closure.cadence} ({plan.commitment.closure.cadence_direction})</Text>
              <Text size="sm">{plan.commitment.requirements.closure ? 'Directions align.' : 'Directions diverge; Commitment is blocked.'}</Text>
              <Text size="xs">Zero retains the previous committed direction. History: {plan.commitment.closure.history_source}.</Text>
            </Alert>
          )}
          {availableActions.includes('participant.rest') && (
            <Alert color="gray" title="Tragic Region" m="md">
              <Text size="sm">No admissible non-Rest action remains in this Phase. Rest ends this Cycle; recovery begins at the nearest viable Committal region.</Text>
              <Button mt="sm" variant="light" color="gray" onClick={() => openAction('participant.rest')}>Rest</Button>
            </Alert>
          )}
          <ParticipantWorkspace plan={{ ...plan, perspectiveLimit: participant.max_read_count }} availableActions={availableActions} onAction={openAction} />
          <ContributionForm action={selectedAction?.name} plan={plan} opened={selectedAction !== null} loading={contribution.isLoading} error={contribution.error} initialValues={selectedAction?.initialValues} onClose={() => setSelectedAction(null)} onSubmit={submit} />
        </Box>
      )}
      <OriginCycleForm opened={originOpened} loading={cycleEntry.isLoading} error={cycleEntry.error} onClose={closeOrigin} onSubmit={establishOrigin} />
    </Container>
  );
}

function PhaseLabel({ kind }: { kind: PhaseKind }) {
  const meta = phaseMeta[kind];
  const Icon = meta?.icon || IconFocus2;
  const name = kind ? kind.charAt(0).toUpperCase() + kind.slice(1) : 'Unknown';
  return (
    <Group className="cycle-context-header__phase" gap={7} justify="center" wrap="nowrap">
      <Icon size={17} />
      <Text size="sm" fw={650}>{meta?.number ? `Phase ${meta.number} · ${name}` : name}</Text>
    </Group>
  );
}

function isActionName(value?: string): value is ActionName {
  return [
    'attestation.create',
    'instrument.execute',
    'proposition.commit',
    'proposition.reclassify',
    'proposition.reconcile',
    'proposition.revise',
    'proposition.traverse',
    'participant.rest',
    'proposition.variant',
  ].includes(value as ActionName);
}
