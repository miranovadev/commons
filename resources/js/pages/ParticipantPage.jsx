import { useMemo, useState } from 'react';
import { Alert, Badge, Box, Breadcrumbs, Button, Container, Group, Loader, Stack, Text, ThemeIcon, Title } from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { IconAlertCircle, IconSparkles, IconUserHexagon } from '@tabler/icons-react';
import { Link, useParams } from 'react-router-dom';
import { messageFrom } from '../errors';
import { ContributionForm } from '../components/ContributionForm';
import { OriginCycleForm } from '../components/OriginCycleForm';
import { ParticipantWorkspace } from '../components/ParticipantWorkspace';
import { useContributeMutation, useEnterCycleMutation, useGetParticipantQuery, useGetParticipationQuery } from '../services/commonsApi';

const phaseNumbers = {
  distinguish: 1,
  persist: 2,
  constrain: 3,
  disperse: 4,
  mediate: 5,
  bias: 6,
  order: 7,
};

export function ParticipantPage() {
  const { slug, participantId } = useParams();
  const parameters = { slug, participantId };
  const { data: participant, isLoading: participantLoading, error: participantError } = useGetParticipantQuery(parameters);
  const { data: participation, isLoading: participationLoading, error: participationError } = useGetParticipationQuery(parameters);
  const [contribute, contribution] = useContributeMutation();
  const [enterCycle, cycleEntry] = useEnterCycleMutation();
  const [selectedAction, setSelectedAction] = useState(null);
  const [originOpened, { open: openOrigin, close: closeOrigin }] = useDisclosure(false);
  const plan = participation?.plan;
  const error = participantError || participationError;
  const availableActions = useMemo(() => plan?.schema?.oneOf?.map((entry) => entry.$ref?.split('/').pop()).filter(Boolean) || [], [plan]);

  function openAction(name, initialValues = {}) { contribution.reset(); setSelectedAction({ name, initialValues }); }
  async function submit(contributionInput) {
    try { await contribute({ slug, participantId, phaseId: plan.phase.id, contribution: contributionInput }).unwrap(); setSelectedAction(null); } catch { /* mutation error is rendered by the form */ }
  }
  async function establishOrigin(origin) {
    try { await enterCycle({ slug, participantId, origin }).unwrap(); closeOrigin(); } catch { /* mutation error is rendered by the form */ }
  }

  return (
    <Container fluid className={plan ? 'participant-page participant-page--active' : 'participant-page'} p={plan ? 0 : undefined}>
      {!plan && <Breadcrumbs mb="lg"><Text component={Link} to="/" size="sm" c="dimmed">Substrates</Text><Text component={Link} to={`/substrates/${slug}`} size="sm" c="dimmed">{slug}</Text><Text size="sm">Participant</Text></Breadcrumbs>}
      {(participantLoading || participationLoading) && !error && <Stack align="center" py={100}><Loader color="indigo" /><Text c="dimmed">Finding your current position…</Text></Stack>}
      {error && <Alert color="red" variant="light" icon={<IconAlertCircle size={18} />} title="The Commons could not load this position">{messageFrom(error, 'This participation state could not be loaded.')}</Alert>}
      {participant && participation?.status === 'waiting' && (
        <Box p="xl"><Stack align="flex-start" maw={680} mx="auto"><ThemeIcon color="indigo" variant="light" size="xl" radius="xl"><IconSparkles size={22} /></ThemeIcon><Box><Title order={2}>{participation.entry?.mode === 'origin' ? 'Every substrate begins somewhere' : 'The cycle is waiting'}</Title><Text c="dimmed" mt="xs">{participation.entry?.message || participation.reason?.message}</Text></Box>{participation.entry?.mode === 'origin' && participation.entry?.available && <Button color="indigo" onClick={() => { cycleEntry.reset(); openOrigin(); }}>Establish the Origin</Button>}</Stack></Box>
      )}
      {participant && plan && (
        <Box className="participant-cycle-shell">
          <Box className="cycle-context-header">
            <Group className="cycle-context-header__participant" gap="sm" wrap="nowrap" miw={0}>
              <Badge color="indigo" variant="light" leftSection={<IconUserHexagon size={12} />}>PARTICIPANT</Badge>
              <Box miw={0}><Text fw={750} truncate>{participant.callsign}</Text><Text size="xs" c="dimmed" truncate>{slug}</Text></Box>
            </Group>
            <Box className="cycle-context-header__phase">
              <Text size="10px" fw={700} c="dimmed">Current phase</Text>
              <Text size="sm" fw={650}>{phaseLabel(plan.phase.kind)}</Text>
            </Box>
            <Box aria-hidden="true" />
          </Box>
          <ParticipantWorkspace plan={{ ...plan, perspectiveLimit: participant.max_read_count }} availableActions={availableActions} onAction={openAction} />
          <ContributionForm action={selectedAction?.name} plan={plan} opened={selectedAction !== null} loading={contribution.isLoading} error={contribution.error} initialValues={selectedAction?.initialValues} onClose={() => setSelectedAction(null)} onSubmit={submit} />
        </Box>
      )}
      <OriginCycleForm opened={originOpened} loading={cycleEntry.isLoading} error={cycleEntry.error} onClose={closeOrigin} onSubmit={establishOrigin} />
    </Container>
  );
}

function phaseLabel(kind) {
  const number = phaseNumbers[kind];
  const name = kind ? kind.charAt(0).toUpperCase() + kind.slice(1) : 'Unknown';
  return number ? `Phase ${number} · ${name}` : name;
}
