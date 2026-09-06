import { useMemo, useState } from 'react';
import { Alert, Badge, Box, Breadcrumbs, Button, Card, Container, Divider, Group, Loader, Paper, SimpleGrid, Stack, Text, ThemeIcon, Title } from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { IconAlertCircle, IconBolt, IconGitBranch, IconPencil, IconPlugConnected, IconRefresh, IconRoute, IconSparkles, IconTool, IconUserHexagon, IconWhirl } from '@tabler/icons-react';
import { Link, useParams } from 'react-router-dom';
import { messageFrom } from '../errors';
import { PropositionGraph } from '../components/PropositionGraph';
import { ConnectionsPanel } from '../components/ConnectionsPanel';
import { ContributionForm } from '../components/ContributionForm';
import { OriginCycleForm } from '../components/OriginCycleForm';
import { useContributeMutation, useEnterCycleMutation, useGetParticipantQuery, useGetParticipationQuery, useGetPropositionsQuery } from '../services/commonsApi';

const actions = {
  'proposition.variant': { label: 'Variant', color: 'blue', icon: IconGitBranch },
  'proposition.revise': { label: 'Revise', color: 'cyan', icon: IconPencil },
  'proposition.reclassify': { label: 'Reclassify', color: 'orange', icon: IconRefresh },
  'proposition.reconcile': { label: 'Reconcile', color: 'grape', icon: IconRoute },
  'attestation.create': { label: 'Attest', color: 'violet', icon: IconWhirl },
  'instrument.execute': { label: 'Instrument', color: 'yellow', icon: IconTool },
  'proposition.commit': { label: 'Commit', color: 'indigo', icon: IconBolt },
  'proposition.select': { label: 'Select', color: 'gray', icon: IconRoute },
};

export function ParticipantPage() {
  const { slug, participantId } = useParams();
  const parameters = { slug, participantId };
  const { data: participant, isLoading: participantLoading, error: participantError } = useGetParticipantQuery(parameters);
  const { data: participation, isLoading: participationLoading, error: participationError } = useGetParticipationQuery(parameters);
  const { data: propositions = [] } = useGetPropositionsQuery(slug);
  const [contribute, contribution] = useContributeMutation();
  const [enterCycle, cycleEntry] = useEnterCycleMutation();
  const [selectedAction, setSelectedAction] = useState(null);
  const [originOpened, { open: openOrigin, close: closeOrigin }] = useDisclosure(false);
  const plan = participation?.plan;
  const error = participantError || participationError;
  const [connectionsOpened, { open: openConnections, close: closeConnections }] = useDisclosure(false);

  const availableActions = useMemo(
    () => plan?.schema?.oneOf
      ?.map((entry) => entry.$ref?.split('/').pop())
      .filter(Boolean) || [],
    [plan],
  );
  const graph = useMemo(() => {
    if (!plan) return [];
    const relevantIds = new Set([plan.gate, ...(plan.lineage || []), ...(plan.write_options || []), ...(plan.gate_options || [])].filter(Boolean).map((item) => item.id));
    return propositions.filter((item) => relevantIds.has(item.id));
  }, [plan, propositions]);

  async function submit(contributionInput) {
    try {
      await contribute({
        slug,
        participantId,
        phaseId: plan.phase.id,
        contribution: contributionInput,
      }).unwrap();
      setSelectedAction(null);
    } catch {
      // The mutation error remains visible inside the contribution modal.
    }
  }

  async function establishOrigin(origin) {
    try {
      await enterCycle({ slug, participantId, origin }).unwrap();
      closeOrigin();
    } catch {
      // The mutation error remains visible inside the Origin modal.
    }
  }

  return (
    <Container size="xl">
      <Breadcrumbs mb="lg"><Text component={Link} to="/" size="sm" c="dimmed">Substrates</Text><Text component={Link} to={`/substrates/${slug}`} size="sm" c="dimmed">{slug}</Text><Text size="sm">Participant</Text></Breadcrumbs>
      {(participantLoading || participationLoading) && !error && <Stack align="center" py={100}><Loader color="indigo" /><Text c="dimmed">Finding your current position…</Text></Stack>}
      {error && (
        <Alert color="red" variant="light" icon={<IconAlertCircle size={18} />} title="The Commons could not load this position">
          {messageFrom(error, 'This participation state could not be loaded.')}
        </Alert>
      )}
      {participant && participation?.status === 'waiting' && (
        <Card withBorder p={{ base: 'lg', sm: 'xl' }} maw={680} mx="auto">
          <Stack align="flex-start">
            <ThemeIcon color="indigo" variant="light" size="xl" radius="xl"><IconSparkles size={22} /></ThemeIcon>
            <Box>
              <Title order={2}>{participation.entry?.mode === 'origin' ? 'Every substrate begins somewhere' : 'The cycle is waiting'}</Title>
              <Text c="dimmed" mt="xs">{participation.entry?.message || participation.reason?.message}</Text>
            </Box>
            {participation.entry?.mode === 'origin' && participation.entry?.available && (
              <Button color="indigo" onClick={() => { cycleEntry.reset(); openOrigin(); }}>
                Establish the Origin
              </Button>
            )}
            {participation.entry?.mode === 'potential' && (
              <Alert color="indigo" variant="light" icon={<IconAlertCircle size={18} />}>
                The Origin already exists. Entry will open here once Potential selection is connected.
              </Alert>
            )}
          </Stack>
        </Card>
      )}
      {participant && plan && (
        <>
          <Group justify="space-between" align="flex-start" mb="xl">
            <Box><Badge color="indigo" variant="light" leftSection={<IconUserHexagon size={12} />}>PARTICIPANT</Badge><Title order={1} mt="sm">{participant.callsign}</Title><Text c="dimmed">{participant.designation || 'Commons participant'} · Phase {plan.phase.kind}</Text></Box>
            <Group><Button variant="default" leftSection={<IconPlugConnected size={17} />} onClick={openConnections}>Connections</Button><Badge size="lg" color="indigo" variant="outline">{plan.phase.write_register || 'No write register'}</Badge></Group>
          </Group>

          <SimpleGrid cols={{ base: 1, lg: 4 }} spacing="lg">
            <Paper withBorder p="lg" style={{ gridColumn: 'span 3' }}>
              <Group justify="space-between" mb="lg">
                <Box>
                  <Title order={3}>Current Perspective</Title>
                  <Text size="sm" c="dimmed">{plan.phase.description}</Text>
                </Box>
                <Stack gap={5} align="flex-end">
                  <Badge color="gray" variant="light">Gate · {plan.gate.title || plan.gate.statement.slice(0, 32)}</Badge>
                  <Badge color="gray" variant="outline" size="xs">
                    Attenuation pending
                  </Badge>
                </Stack>
              </Group>
              <PropositionGraph propositions={graph} initialFocusId={plan.gate.id} perspectiveLimit={participant.max_read_count} participantMode />
            </Paper>

            <Card withBorder p="lg" h="fit-content">
              <Title order={4}>Admissible moves</Title>
              <Text size="sm" c="dimmed" mt={4}>Actions permitted by the current Phase and Gate.</Text>
              <Divider my="md" />
              <Stack gap="xs">
                {availableActions.map((name) => {
                  const meta = actions[name] || { label: name, color: 'gray', icon: IconRoute };
                  const Icon = meta.icon;
                  return <Button key={name} color={meta.color} variant={name === 'proposition.commit' ? 'filled' : 'light'} justify="flex-start" leftSection={<Icon className={name === 'attestation.create' ? 'chirality-icon chirality-icon--slow' : ''} size={17} />} onClick={() => { contribution.reset(); setSelectedAction(name); }}>{meta.label}</Button>;
                })}
                {!availableActions.length && <Text size="sm" c="dimmed">No action is currently admissible.</Text>}
              </Stack>
              <Text size="xs" c="dimmed" mt="md">Every control is generated from the live admissibility schema for this Phase.</Text>
            </Card>
          </SimpleGrid>
          <ContributionForm
            action={selectedAction}
            plan={plan}
            opened={selectedAction !== null}
            loading={contribution.isLoading}
            error={contribution.error}
            onClose={() => setSelectedAction(null)}
            onSubmit={submit}
          />
          <ConnectionsPanel slug={slug} participantId={participantId} opened={connectionsOpened} onClose={closeConnections} />
        </>
      )}
      <OriginCycleForm
        opened={originOpened}
        loading={cycleEntry.isLoading}
        error={cycleEntry.error}
        onClose={closeOrigin}
        onSubmit={establishOrigin}
      />
    </Container>
  );
}
