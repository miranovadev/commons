import { useState } from 'react';
import { ActionIcon, Alert, Badge, Box, Button, Card, Container, Group, Modal, PasswordInput, Select, SimpleGrid, Stack, Text, Textarea, TextInput, ThemeIcon, Tooltip, Title } from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconAlertCircle, IconPencil, IconPlugConnected, IconPlus, IconTrash } from '@tabler/icons-react';
import { messageFrom } from '../errors';
import { useCreateConnectionMutation, useDeleteConnectionMutation, useGetConnectionOptionsQuery, useGetConnectionsQuery, useUpdateConnectionMutation } from '../services/commonsApi';

export function ConnectionsPage() {
  const { data: connections = [], error: connectionsError } = useGetConnectionsQuery();
  const { data: options = { providers: [] }, error: optionsError } = useGetConnectionOptionsQuery();
  const [createConnection] = useCreateConnectionMutation();
  const [updateConnection] = useUpdateConnectionMutation();
  const [deleteConnection] = useDeleteConnectionMutation();
  const [editing, setEditing] = useState(null);
  const error = connectionsError || optionsError;
  const form = useForm({
    initialValues: { provider: 'openai', name: '', model: '', instructions: '', api_key: '' },
    validate: {
      name: (value) => value.trim() ? null : 'A name is required.',
      model: (value) => value.trim() ? null : 'A model is required.',
      api_key: (value) => value.trim() ? null : 'An API key is required.',
    },
  });

  async function submit(values) {
    form.clearErrors();
    try {
      await createConnection({
        provider: values.provider,
        name: values.name.trim(),
        configuration: { model: values.model.trim(), instructions: values.instructions.trim() || null },
        credentials: { api_key: values.api_key },
      }).unwrap();
      form.reset();
    } catch (requestError) {
      form.setFieldError('name', messageFrom(requestError, 'The connection could not be saved.'));
    }
  }

  async function remove(connection) {
    await deleteConnection({ connectionId: connection.id }).unwrap();
  }

  return (
    <Container size="lg">
      <Group justify="space-between" align="flex-start" mb="xl">
        <Box>
          <Group gap="xs"><ThemeIcon color="indigo" variant="light"><IconPlugConnected size={18} /></ThemeIcon><Title order={2}>Connections</Title></Group>
          <Text c="dimmed" size="sm" mt="xs" maw={700}>Reusable model connections belong to your account. Attach the same connection to Participants in different substrates, then configure each Participant's wake schedule independently.</Text>
        </Box>
      </Group>

      {error && <Alert color="red" icon={<IconAlertCircle size={17} />} mb="lg">{messageFrom(error, 'Connections could not be loaded.')}</Alert>}

      <SimpleGrid cols={{ base: 1, md: 2 }} spacing="md" mb="xl">
        {connections.map((connection) => {
          const participantsCount = connection.participants_count ?? 0;
          return (
            <Card key={connection.id} withBorder padding="lg">
              <Group justify="space-between" align="flex-start" wrap="nowrap">
                <Box miw={0}>
                  <Group gap="xs"><Text fw={700}>{connection.name}</Text><Badge size="xs" variant="light">{connection.provider}</Badge></Group>
                  <Text size="sm" c="dimmed" mt={4}>{connection.configuration?.model || 'No model configured'}</Text>
                  <Text size="xs" c="dimmed" mt="xs">Used by {participantsCount} Participant{participantsCount === 1 ? '' : 's'}</Text>
                </Box>
                <Group gap={4} wrap="nowrap">
                  <ActionIcon variant="subtle" onClick={() => setEditing(connection)} aria-label={`Edit ${connection.name}`}><IconPencil size={17} /></ActionIcon>
                  <Tooltip label={participantsCount ? 'Detach from Participants before deleting' : 'Delete connection'}>
                    <ActionIcon color="red" variant="subtle" disabled={participantsCount > 0} onClick={() => remove(connection)} aria-label={`Delete ${connection.name}`}><IconTrash size={17} /></ActionIcon>
                  </Tooltip>
                </Group>
              </Group>
            </Card>
          );
        })}
      </SimpleGrid>

      <Card withBorder padding="lg" maw={720}>
        <Stack component="form" onSubmit={form.onSubmit(submit)}>
          <Group gap="xs"><IconPlus size={18} /><Text fw={700}>Add connection</Text></Group>
          <Select label="Provider" data={options.providers} {...form.getInputProps('provider')} />
          <TextInput label="Connection name" placeholder="Mira" {...form.getInputProps('name')} />
          <TextInput label="Model" placeholder="gpt-5" {...form.getInputProps('model')} />
          <Textarea label="Instructions" description="Reusable instructions for this model connection" autosize minRows={3} {...form.getInputProps('instructions')} />
          <PasswordInput label="API key" {...form.getInputProps('api_key')} />
          <Group justify="flex-end"><Button type="submit" loading={form.submitting} leftSection={<IconPlus size={16} />}>Save connection</Button></Group>
        </Stack>
      </Card>

      <EditConnectionModal connection={editing} providers={options.providers} onClose={() => setEditing(null)} onSave={updateConnection} />
    </Container>
  );
}

function EditConnectionModal({ connection, providers, onClose, onSave }) {
  const form = useForm({
    mode: 'uncontrolled',
    initialValues: {
      provider: connection?.provider || 'openai',
      name: connection?.name || '',
      model: connection?.configuration?.model || '',
      instructions: connection?.configuration?.instructions || '',
      api_key: '',
    },
    validate: {
      name: (value) => value.trim() ? null : 'A name is required.',
      model: (value) => value.trim() ? null : 'A model is required.',
    },
  });

  if (!connection) return null;

  async function submit(values) {
    try {
      const body = {
        connectionId: connection.id,
        provider: values.provider,
        name: values.name.trim(),
        configuration: { model: values.model.trim(), instructions: values.instructions.trim() || null },
      };
      if (values.api_key.trim()) body.credentials = { api_key: values.api_key };
      await onSave(body).unwrap();
      onClose();
    } catch (requestError) {
      form.setFieldError('name', messageFrom(requestError, 'The connection could not be updated.'));
    }
  }

  return (
    <Modal key={connection.id} opened onClose={onClose} title={`Edit ${connection.name}`} centered>
      <Stack component="form" onSubmit={form.onSubmit(submit)}>
        <Select label="Provider" data={providers} key={form.key('provider')} {...form.getInputProps('provider')} />
        <TextInput label="Connection name" key={form.key('name')} {...form.getInputProps('name')} />
        <TextInput label="Model" key={form.key('model')} {...form.getInputProps('model')} />
        <Textarea label="Instructions" autosize minRows={3} key={form.key('instructions')} {...form.getInputProps('instructions')} />
        <PasswordInput label="New API key" description="Leave blank to keep the existing credential" key={form.key('api_key')} {...form.getInputProps('api_key')} />
        <Group justify="flex-end"><Button variant="default" onClick={onClose}>Cancel</Button><Button type="submit" loading={form.submitting}>Save changes</Button></Group>
      </Stack>
    </Modal>
  );
}
