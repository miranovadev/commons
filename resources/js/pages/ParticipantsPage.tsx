import { ParticipantAvatar } from '../components/ParticipantIdentity';
import { useMemo, useState } from 'react';
import { ActionIcon, Alert, Badge, Box, Button, Card, Container, Group, Modal, NumberInput, Select, SimpleGrid, Stack, Switch, Text, TextInput, ThemeIcon, Title, Tooltip } from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconAlertCircle, IconArrowRight, IconPlugConnected, IconPlus, IconRoute, IconTrash, IconUserHexagon, IconUsers } from '@tabler/icons-react';
import { useNavigate, useParams } from 'react-router-dom';
import { messageFrom } from '../errors';
import { ConnectionsPanel } from '../components/ConnectionsPanel';
import { useDeleteParticipantConnectionMutation, useGetConnectionsQuery, useGetParticipantConnectionsQuery, useGetParticipantsQuery, useGetSubstrateQuery, useJoinParticipantMutation, useUpdateParticipantConnectionMutation } from '../services/commonsApi';
import type { Connection, ConnectionTrigger, JoinParticipantInput, Participant, User } from '../types';

interface ParticipantsPageProps { user?: User | null }
interface ParticipantCardProps {
  participant: Participant;
  slug: string;
  onEnter: () => void;
  onConnections: () => void;
}
interface ParticipantConnectionSummaryProps {
  slug: string;
  participant: Participant;
  onManage: () => void;
}
interface CreateParticipantValues {
  callsign: string;
  designation: string;
  max_read_count: number;
  max_token_count: number | string;
  connection_id: string | null;
}
interface CreateParticipantModalProps {
  opened: boolean;
  onClose: () => void;
  slug: string;
  user?: User | null;
  connections: Connection[];
  onCreated: (participant: Participant, enter: boolean) => void;
}

export function ParticipantsPage({ user }: ParticipantsPageProps) {
  const { slug: routeSlug } = useParams();
  const slug = routeSlug ?? '';
  const navigate = useNavigate();
  const { data: substrate } = useGetSubstrateQuery(slug);
  const { data: allParticipants = [], error } = useGetParticipantsQuery(slug);
  const { data: connections = [] } = useGetConnectionsQuery(undefined);
  const [opened, setOpened] = useState(false);
  const [connectionParticipant, setConnectionParticipant] = useState<Participant | null>(null);
  const participants = useMemo(
    () => allParticipants.filter((participant) => participant.owned_by_user),
    [allParticipants],
  );

  return (
    <Container size="lg">
      <Group justify="space-between" align="flex-start" mb="xl">
        <Box>
          <Group gap="xs">
            <ThemeIcon color="primary" variant="light"><IconUsers size={18} /></ThemeIcon>
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
            <ThemeIcon size={52} radius="xl" color="primary" variant="light"><IconUserHexagon size={25} /></ThemeIcon>
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
              slug={slug}
              onEnter={() => navigate(`/substrates/${slug}/participants/${participant.id}`)}
              onConnections={() => setConnectionParticipant(participant)}
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
      <ConnectionsPanel
        slug={slug}
        participantId={connectionParticipant?.id}
        participantName={connectionParticipant?.callsign}
        opened={Boolean(connectionParticipant)}
        onClose={() => setConnectionParticipant(null)}
      />
    </Container>
  );
}

function ParticipantCard({ participant, slug, onEnter, onConnections }: ParticipantCardProps) {
  const phase = participant.position?.phase;
  const gate = participant.position?.gate;
  const position = participant.position;

  return (
    <Card withBorder padding="lg">
      <Group justify="space-between" align="flex-start" wrap="nowrap">
        <Group gap="sm" wrap="nowrap">
          <ParticipantAvatar size={42} callsign={participant.callsign} />
          <Box miw={0}>
            <Text fw={750} truncate>{participant.callsign}</Text>
            <Text size="xs" c="dimmed" truncate>{participant.designation || 'Participant'}</Text>
          </Box>
        </Group>
        {phase && <Badge color="primary" variant="light">Phase {phase.number}</Badge>}
      </Group>

      <Box className="participant-position-preview" mt="lg">
        {position ? (
          <Stack gap={5}>
            <Group gap={6}><IconRoute size={14} /><Text size="xs" fw={700}>Cycle {position.cycle_sequence} · {phase ? `${capitalize(phase.name)}${phase.write_register ? ` · ${capitalize(phase.write_register)}` : ''}` : 'Between cycles'}</Text></Group>
            <Text size="sm" lineClamp={2}>{gate?.title || 'Ready for the next structural move.'}</Text>
          </Stack>
        ) : (
          <Text size="sm" c="dimmed">Ready to enter the first cycle.</Text>
        )}
      </Box>

      <ParticipantConnectionSummary slug={slug} participant={participant} onManage={onConnections} />

      <Button mt="lg" fullWidth rightSection={<IconArrowRight size={15} />} onClick={onEnter}>
        {position ? 'Continue Cycle' : 'Enter Cycle'}
      </Button>
    </Card>
  );
}

function ParticipantConnectionSummary({ slug, participant, onManage }: ParticipantConnectionSummaryProps) {
  const parameters = { slug, participantId: participant.id };
  const { data: attachments = [], isLoading } = useGetParticipantConnectionsQuery(parameters);
  const [updateAttachment] = useUpdateParticipantConnectionMutation();
  const [deleteAttachment] = useDeleteParticipantConnectionMutation();

  return (
    <Stack className="participant-connections" gap="xs" mt="md">
      {attachments.map((attachment) => {
        const connection = attachment.connection;
        const trigger: ConnectionTrigger = attachment.configuration?.trigger || { mode: 'manual' };

        return (
          <Group key={attachment.id} className="participant-connection" justify="space-between" gap="xs" wrap="nowrap">
            <Group gap="xs" wrap="nowrap" miw={0}>
              <ThemeIcon color="primary" variant="light" size="sm"><IconPlugConnected size={14} /></ThemeIcon>
              <Box miw={0}>
                <Group gap={5} wrap="nowrap">
                  <Text size="xs" fw={650} truncate>{connection?.name || 'Connection'}</Text>
                  <Badge size="xs" variant="light" color="gray">{connection?.provider || 'provider'}</Badge>
                </Group>
                <Text size="10px" c="dimmed">{triggerLabel(trigger)}</Text>
              </Box>
            </Group>
            <Group gap={2} wrap="nowrap">
              <Tooltip label={attachment.is_enabled ? 'Disable connection' : 'Enable connection'}>
                <Switch
                  size="xs"
                  checked={attachment.is_enabled}
                  onChange={(event) => updateAttachment({ ...parameters, participantConnectionId: attachment.id, is_enabled: event.currentTarget.checked })}
                  aria-label={`${attachment.is_enabled ? 'Disable' : 'Enable'} ${connection?.name || 'connection'}`}
                />
              </Tooltip>
              <Tooltip label="Detach connection">
                <ActionIcon color="red" variant="subtle" size="sm" onClick={() => deleteAttachment({ ...parameters, participantConnectionId: attachment.id })} aria-label={`Detach ${connection?.name || 'connection'}`}>
                  <IconTrash size={14} />
                </ActionIcon>
              </Tooltip>
            </Group>
          </Group>
        );
      })}
      <Button
        size="compact-sm"
        variant="subtle"
        color="gray"
        loading={isLoading}
        leftSection={<IconPlugConnected size={14} />}
        onClick={onManage}
        className="participant-connection__manage"
      >
        {attachments.length ? 'Configure connections' : 'Add connection'}
      </Button>
    </Stack>
  );
}

function triggerLabel(trigger: ConnectionTrigger) {
  if (trigger.mode === 'interval') return `Every ${trigger.interval_minutes || 60} minutes`;
  if (trigger.mode === 'scheduled') return 'Scheduled wake';
  return 'Manual wake';
}

function CreateParticipantModal({ opened, onClose, slug, user, connections, onCreated }: CreateParticipantModalProps) {
  const [joinParticipant] = useJoinParticipantMutation();
  const defaultCallsign = firstWord(user?.name);
  const form = useForm<CreateParticipantValues>({
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

  async function create(enter: boolean) {
    const validation = form.validate();
    if (validation.hasErrors) return;

    try {
      const values = form.getValues();
      const payload: JoinParticipantInput = {
        slug,
        callsign: values.callsign.trim(),
        designation: values.designation.trim() || null,
        max_read_count: values.max_read_count,
        max_token_count: typeof values.max_token_count === 'number' ? values.max_token_count : null,
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

function firstWord(name?: string) {
  return String(name || '').trim().split(/\s+/)[0] || '';
}

function capitalize(value?: string) {
  const text = String(value || '');
  return text ? text.charAt(0).toUpperCase() + text.slice(1) : text;
}
