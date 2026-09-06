import { useMemo, useState } from 'react';
import { Alert, Avatar, Badge, Box, Button, Card, Container, Group, Modal, NumberInput, Select, SimpleGrid, Stack, Text, TextInput, ThemeIcon, Title } from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconAlertCircle, IconArrowRight, IconPlus, IconRoute, IconUserHexagon, IconUsers } from '@tabler/icons-react';
import { useNavigate, useParams } from 'react-router-dom';
import { messageFrom } from '../errors';
import { useGetConnectionsQuery, useGetParticipantsQuery, useGetSubstrateQuery, useJoinParticipantMutation } from '../services/commonsApi';

export function ParticipantsPage({ user }) {
  const { slug } = useParams();
  const navigate = useNavigate();
  const { data: substrate } = useGetSubstrateQuery(slug);
  const { data: allParticipants = [], error } = useGetParticipantsQuery(slug);
  const { data: connections = [] } = useGetConnectionsQuery();
  const [opened, setOpened] = useState(false);
  const participants = useMemo(
    () => allParticipants.filter((participant) => participant.owned_by_user),
    [allParticipants],
  );

  return (
    <Container size="lg">
      <Group justify="space-between" align="flex-start" mb="xl">
        <Box>
          <Group gap="xs">
            <ThemeIcon color="indigo" variant="light"><IconUsers size={18} /></ThemeIcon>
            <Title order={2}>Participants</Title>
          </Group>
          <Text c="dimmed" size="sm" mt="xs">
            {substrate?.title ? `Your Participants in ${substrate.title}.` : 'Your Participants in this substrate.'}
          </Text>
        </Box>
        <Button leftSection={<IconPlus size={16} />} onClick={() => setOpened(true)}>Create Participant</Button>
      </Group>

      {error && <Alert color="red" icon={<IconAlertCircle size={17} />} mb="lg">{messageFrom(error, 'Participants could not be loaded.')}</Alert>}

      {participants.length === 0 ? (
        <Card withBorder padding="xl" className="participants-empty">
          <Stack align="center" gap="sm" py="xl">
            <ThemeIcon size={52} radius="xl" color="indigo" variant="light"><IconUserHexagon size={25} /></ThemeIcon>
            <Box ta="center">
              <Text fw={750}>No Participants yet</Text>
              <Text size="sm" c="dimmed" mt={4} maw={520}>Create a Participant to establish a distinct point of participation in this substrate. A reusable Connection is optional and can be attached later.</Text>
            </Box>
            <Button mt="xs" leftSection={<IconPlus size={16} />} onClick={() => setOpened(true)}>Create Participant</Button>
          </Stack>
        </Card>
      ) : (
        <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="md">
          {participants.map((participant) => (
            <ParticipantCard
              key={participant.id}
              participant={participant}
              onEnter={() => navigate(`/substrates/${slug}/participants/${participant.id}`)}
            />
          ))}
        </SimpleGrid>
      )}

      <CreateParticipantModal
        opened={opened}
        onClose={() => setOpened(false)}
        slug={slug}
        user={user}
        connections={connections}
        onCreated={(participant, enter) => {
          setOpened(false);
          if (enter) navigate(`/substrates/${slug}/participants/${participant.id}`);
        }}
      />
    </Container>
  );
}

function ParticipantCard({ participant, onEnter }) {
  const phase = participant.position?.phase;
  const gate = participant.position?.gate;
  const started = Boolean(participant.position);

  return (
    <Card withBorder padding="lg">
      <Group justify="space-between" align="flex-start" wrap="nowrap">
        <Group gap="sm" wrap="nowrap">
          <Avatar size={42} radius="xl" color="indigo" name={participant.callsign} />
          <Box miw={0}>
            <Text fw={750} truncate>{participant.callsign}</Text>
            <Text size="xs" c="dimmed" truncate>{participant.designation || 'Participant'}</Text>
          </Box>
        </Group>
        {phase && <Badge color="indigo" variant="light">Phase {phase.number}</Badge>}
      </Group>

      <Box className="participant-position-preview" mt="lg">
        {started ? (
          <Stack gap={5}>
            <Group gap={6}><IconRoute size={14} /><Text size="xs" fw={700}>Cycle {participant.position.cycle_sequence} · {phase ? `${capitalize(phase.name)}${phase.write_register ? ` · ${capitalize(phase.write_register)}` : ''}` : 'Between cycles'}</Text></Group>
            <Text size="sm" lineClamp={2}>{gate?.title || 'Ready for the next structural move.'}</Text>
          </Stack>
        ) : (
          <Text size="sm" c="dimmed">Ready to enter the first cycle.</Text>
        )}
      </Box>

      <Button mt="lg" fullWidth rightSection={<IconArrowRight size={15} />} onClick={onEnter}>
        {started ? 'Continue Cycle' : 'Enter Cycle'}
      </Button>
    </Card>
  );
}

function CreateParticipantModal({ opened, onClose, slug, user, connections, onCreated }) {
  const [joinParticipant] = useJoinParticipantMutation();
  const defaultCallsign = firstWord(user?.name);
  const form = useForm({
    initialValues: {
      callsign: defaultCallsign,
      designation: '',
      max_read_count: 25,
      max_token_count: '',
      connection_id: null,
    },
    validate: { callsign: (value) => value.trim() ? null : 'A callsign is required.' },
  });

  const connectionOptions = connections.map((connection) => ({
    value: connection.id,
    label: `${connection.name} · ${connection.configuration?.model || connection.provider}`,
  }));

  async function create(enter) {
    const validation = form.validate();
    if (validation.hasErrors) return;

    try {
      const values = form.getValues();
      const payload = {
        slug,
        callsign: values.callsign.trim(),
        designation: values.designation.trim() || null,
        max_read_count: values.max_read_count,
        max_token_count: values.max_token_count || null,
      };
      if (values.connection_id) payload.connection = { id: values.connection_id };
      const participant = await joinParticipant(payload).unwrap();
      form.reset();
      onCreated(participant, enter);
    } catch (requestError) {
      form.setFieldError('callsign', messageFrom(requestError, 'The Participant could not be created.'));
    }
  }

  return (
    <Modal opened={opened} onClose={onClose} title="Create Participant" centered>
      <Stack>
        <Text size="sm" c="dimmed">A Participant is a distinct input source. Connections are reusable and optional.</Text>
        <TextInput label="Callsign" placeholder={defaultCallsign || 'Participant'} {...form.getInputProps('callsign')} />
        <TextInput label="Designation" description="Optional role or area of experience" {...form.getInputProps('designation')} />
        <Select label="Connection" placeholder="No connection" clearable data={connectionOptions} {...form.getInputProps('connection_id')} />
        <NumberInput label="Maximum propositions in Perspective" min={1} {...form.getInputProps('max_read_count')} />
        <NumberInput label="Maximum token count" min={1} placeholder="No token limit" {...form.getInputProps('max_token_count')} />
        <Group justify="flex-end" mt="sm">
          <Button variant="default" onClick={onClose}>Cancel</Button>
          <Button variant="light" onClick={() => create(false)}>Create</Button>
          <Button onClick={() => create(true)} rightSection={<IconArrowRight size={15} />}>Create & Enter Cycle</Button>
        </Group>
      </Stack>
    </Modal>
  );
}

function firstWord(name) {
  return String(name || '').trim().split(/\s+/)[0] || '';
}

function capitalize(value) {
  const text = String(value || '');
  return text ? text.charAt(0).toUpperCase() + text.slice(1) : text;
}
