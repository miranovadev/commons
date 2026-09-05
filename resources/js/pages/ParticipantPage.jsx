import { useEffect, useMemo, useState } from 'react';
import { Alert, Badge, Box, Breadcrumbs, Button, Card, Container, Divider, Group, Loader, Paper, SimpleGrid, Stack, Text, ThemeIcon, Title } from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { IconAlertCircle, IconBolt, IconGitBranch, IconPencil, IconPlugConnected, IconRefresh, IconRoute, IconTool, IconUserHexagon, IconWhirl } from '@tabler/icons-react';
import { Link, useParams } from 'react-router-dom';
import { api, messageFrom } from '../api';
import { PropositionGraph } from '../components/PropositionGraph';
import { ConnectionsPanel } from '../components/ConnectionsPanel';

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
  const [participant, setParticipant] = useState(null);
  const [plan, setPlan] = useState(null);
  const [propositions, setPropositions] = useState([]);
  const [error, setError] = useState('');
  const [connectionsOpened, { open: openConnections, close: closeConnections }] = useDisclosure(false);

  useEffect(() => {
    let active = true;
    Promise.all([
      api.get(`/substrates/${slug}/participants/${participantId}`),
      api.get(`/substrates/${slug}/participants/${participantId}/plan`),
      api.get(`/substrates/${slug}/propositions`),
    ]).then(([participantResponse, planResponse, graphResponse]) => {
      if (!active) return;
      setParticipant(participantResponse.data.data);
      setPlan(planResponse.data.data);
      setPropositions(graphResponse.data.data);
    }).catch((requestError) => active && setError(messageFrom(requestError, 'This cycle is not ready yet.')));
    return () => { active = false; };
  }, [slug, participantId]);

  const availableActions = useMemo(() => plan?.schema?.oneOf?.map((schema) => schema.properties?.action?.const).filter(Boolean) || [], [plan]);
  const graph = useMemo(() => {
    if (!plan) return [];
    const relevantIds = new Set([plan.gate, ...(plan.lineage || []), ...(plan.write_options || []), ...(plan.gate_options || [])].filter(Boolean).map((item) => item.id));
    return propositions.filter((item) => relevantIds.has(item.id));
  }, [plan, propositions]);

  return (
    <Container size="xl">
      <Breadcrumbs mb="lg"><Text component={Link} to="/" size="sm" c="dimmed">Substrates</Text><Text component={Link} to={`/substrates/${slug}`} size="sm" c="dimmed">{slug}</Text><Text size="sm">Participant</Text></Breadcrumbs>
      {!participant && !error && <Stack align="center" py={100}><Loader color="indigo" /><Text c="dimmed">Finding your current position…</Text></Stack>}
      {error && (
        <Alert color="indigo" variant="light" icon={<IconAlertCircle size={18} />} title="The cycle is waiting">
          {error} This is expected while cycle creation and Potential Charge selection are still being implemented.
        </Alert>
      )}
      {participant && plan && (
        <>
          <Group justify="space-between" align="flex-start" mb="xl">
            <Box><Badge color="indigo" variant="light" leftSection={<IconUserHexagon size={12} />}>PARTICIPANT</Badge><Title order={1} mt="sm">{participant.callsign}</Title><Text c="dimmed">{participant.designation || 'Commons participant'} · Phase {plan.phase.kind}</Text></Box>
            <Group><Button variant="default" leftSection={<IconPlugConnected size={17} />} onClick={openConnections}>Connections</Button><Badge size="lg" color="indigo" variant="outline">{plan.phase.write_register || 'No write register'}</Badge></Group>
          </Group>

          <SimpleGrid cols={{ base: 1, lg: 4 }} spacing="lg">
            <Paper withBorder p="lg" style={{ gridColumn: 'span 3' }}>
              <Group justify="space-between" mb="lg"><Box><Title order={3}>Current Perspective</Title><Text size="sm" c="dimmed">{plan.phase.description}</Text></Box><Badge color="gray" variant="light">Gate · {plan.gate.title || plan.gate.statement.slice(0, 32)}</Badge></Group>
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
                  return <Button key={name} color={meta.color} variant={name === 'proposition.commit' ? 'filled' : 'light'} justify="flex-start" leftSection={<Icon className={name === 'attestation.create' ? 'chirality-icon chirality-icon--slow' : ''} size={17} />} disabled title="Contribution dispatch is introduced with each domain operation.">{meta.label}</Button>;
                })}
                {!availableActions.length && <Text size="sm" c="dimmed">No action is currently admissible.</Text>}
              </Stack>
              <Text size="xs" c="dimmed" mt="md">Controls are shown from the live input schema. They become active as operation dispatch is implemented.</Text>
            </Card>
          </SimpleGrid>
          <ConnectionsPanel slug={slug} participantId={participantId} opened={connectionsOpened} onClose={closeConnections} />
        </>
      )}
    </Container>
  );
}
