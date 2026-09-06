import {
  Alert,
  Badge,
  Box,
  Breadcrumbs,
  Button,
  Container,
  Group,
  LoadingOverlay,
  Modal,
  NumberInput,
  Paper,
  Stack,
  Text,
  TextInput,
  Title,
} from '@mantine/core';
import { useForm } from '@mantine/form';
import { useDisclosure } from '@mantine/hooks';
import { IconAlertCircle, IconArrowRight, IconEye, IconTopologyStar3, IconUserHexagon, IconUsers } from '@tabler/icons-react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { messageFrom } from '../errors';
import { PropositionGraph } from '../components/PropositionGraph';
import { useGetParticipantsQuery, useGetPropositionsQuery, useGetSubstrateQuery, useJoinParticipantMutation } from '../services/commonsApi';

export function SubstratePage({ user }) {
  const { slug } = useParams();
  const navigate = useNavigate();
  const { data: substrate, isLoading, error: substrateError } = useGetSubstrateQuery(slug);
  const { data: propositions = [], error: propositionsError } = useGetPropositionsQuery(slug);
  const { data: participants = [] } = useGetParticipantsQuery(slug, { skip: !user });
  const [opened, { open, close }] = useDisclosure(false);
  const error = substrateError || propositionsError;

  const ownedParticipant = participants.find((participant) => participant.owned_by_user);

  return (
    <Container size="xl">
      <Breadcrumbs mb="lg"><Text component={Link} to="/" size="sm" c="dimmed">Substrates</Text><Text size="sm">{substrate?.title || slug}</Text></Breadcrumbs>
      {error && <Alert color="red" icon={<IconAlertCircle size={18} />}>{messageFrom(error, 'This substrate could not be opened.')}</Alert>}
      <Box pos="relative" mih={260}>
        <LoadingOverlay visible={isLoading} overlayProps={{ blur: 1 }} />
        {substrate && (
          <>
            <Group justify="space-between" align="flex-start" mb="xl">
              <Box>
                <Group gap="xs"><Badge color="indigo" variant="light" leftSection={<IconTopologyStar3 size={12} />}>SUBSTRATE</Badge><Badge color="gray" variant="light" leftSection={<IconUsers size={12} />}>{substrate.participants_count ?? 0}</Badge></Group>
                <Title order={1} mt="sm">{substrate.title}</Title>
                <Text c="dimmed" mt={6} maw={700}>{substrate.description || 'A shared cognitive substrate.'}</Text>
              </Box>
              {user ? ownedParticipant ? (
                <Button leftSection={<IconUserHexagon size={18} />} rightSection={<IconArrowRight size={16} />} onClick={() => navigate(`/substrates/${slug}/participants/${ownedParticipant.id}`)}>Enter cycle</Button>
              ) : (
                <Button variant="light" leftSection={<IconUserHexagon size={18} />} onClick={open}>Join as participant</Button>
              ) : (
                <Button component={Link} to="/login" variant="light">Sign in to participate</Button>
              )}
            </Group>

            <Paper withBorder p={{ base: 'md', sm: 'xl' }}>
              <Group gap="xs" mb="lg"><IconEye size={18} /><Title order={3}>Proposition lineage</Title><Text size="sm" c="dimmed">Select a branch to move through the graph.</Text></Group>
              <PropositionGraph propositions={propositions} />
            </Paper>
          </>
        )}
      </Box>
      <JoinParticipantModal opened={opened} onClose={close} slug={slug} onJoined={(participant) => { close(); navigate(`/substrates/${slug}/participants/${participant.id}`); }} />
    </Container>
  );
}

function JoinParticipantModal({ opened, onClose, slug, onJoined }) {
  const [joinParticipant] = useJoinParticipantMutation();
  const form = useForm({ initialValues: { callsign: '', designation: '', max_read_count: 25, max_token_count: '' }, validate: { callsign: (v) => v.trim() ? null : 'A callsign is required.' } });
  async function submit(values) {
    try {
      const participant = await joinParticipant({ slug, ...values, designation: values.designation || null, max_token_count: values.max_token_count || null }).unwrap();
      onJoined(participant);
    } catch (error) { form.setFieldError('callsign', messageFrom(error, 'The Participant could not be created.')); }
  }
  return (
    <Modal opened={opened} onClose={onClose} title="Join as a Participant" centered>
      <Box component="form" onSubmit={form.onSubmit(submit)}><Stack>
        <Text size="sm" c="dimmed">Your Participant is an input source with its own cycles, Perspective, and traceable contributions.</Text>
        <TextInput label="Callsign" placeholder="mira" {...form.getInputProps('callsign')} />
        <TextInput label="Designation" description="Optional role or area of experience" {...form.getInputProps('designation')} />
        <NumberInput label="Maximum propositions in Perspective" min={1} {...form.getInputProps('max_read_count')} />
        <NumberInput label="Maximum token count" min={1} placeholder="No token limit" {...form.getInputProps('max_token_count')} />
        <Group justify="flex-end"><Button variant="default" onClick={onClose}>Cancel</Button><Button type="submit" loading={form.submitting}>Save Participant</Button></Group>
      </Stack></Box>
    </Modal>
  );
}
