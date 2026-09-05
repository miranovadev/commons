import { ActionIcon, Alert, Box, Button, Card, Divider, Drawer, Group, NumberInput, PasswordInput, Select, Stack, Switch, Text, Textarea, TextInput, ThemeIcon, Title } from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconAlertCircle, IconPlugConnected, IconPlus, IconTrash } from '@tabler/icons-react';
import { messageFrom } from '../errors';
import { useCreateConnectionMutation, useDeleteConnectionMutation, useGetConnectionOptionsQuery, useGetConnectionsQuery, useUpdateConnectionMutation } from '../services/commonsApi';

export function ConnectionsPanel({ slug, participantId, opened, onClose }) {
  const parameters = { slug, participantId };
  const { data: connections = [], error: connectionsError } = useGetConnectionsQuery(parameters, { skip: !opened });
  const { data: options = { providers: [], trigger_modes: [] }, error: optionsError } = useGetConnectionOptionsQuery(undefined, { skip: !opened });
  const [createConnection] = useCreateConnectionMutation();
  const [updateConnection] = useUpdateConnectionMutation();
  const [deleteConnection] = useDeleteConnectionMutation();
  const error = connectionsError || optionsError;
  const form = useForm({
    initialValues: { provider: 'openai', name: '', model: '', instructions: '', api_key: '', trigger_mode: 'manual', interval_minutes: 60 },
    validate: { name: (v) => v.trim() ? null : 'A name is required.', model: (v) => v.trim() ? null : 'A model is required.', api_key: (v) => v.trim() ? null : 'An API key is required.' },
  });

  async function create(values) {
    form.clearErrors();
    try {
      const trigger = { mode: values.trigger_mode };
      if (values.trigger_mode === 'interval') trigger.interval_minutes = values.interval_minutes;
      await createConnection({
        ...parameters,
        provider: values.provider,
        name: values.name,
        configuration: { model: values.model, instructions: values.instructions || null, trigger },
        credentials: { api_key: values.api_key },
      }).unwrap();
      form.reset();
    } catch (requestError) { form.setFieldError('name', messageFrom(requestError, 'The connection could not be saved.')); }
  }

  async function toggle(connection, is_enabled) {
    await updateConnection({ ...parameters, connectionId: connection.id, is_enabled }).unwrap();
  }

  async function remove(connection) {
    await deleteConnection({ ...parameters, connectionId: connection.id }).unwrap();
  }

  return (
    <Drawer opened={opened} onClose={onClose} position="right" size="lg" title="Participant connections">
      <Stack>
        <Text size="sm" c="dimmed">Connect this Participant to an external model and choose whether it wakes manually or on a wall-clock interval.</Text>
        {error && <Alert color="red" icon={<IconAlertCircle size={17} />}>{messageFrom(error, 'Connections could not be loaded.')}</Alert>}
        {connections.map((connection) => (
          <Card key={connection.id} withBorder padding="md">
            <Group justify="space-between" wrap="nowrap">
              <Group wrap="nowrap"><ThemeIcon color="blue" variant="light"><IconPlugConnected size={17} /></ThemeIcon><Box><Text fw={650}>{connection.name}</Text><Text size="xs" c="dimmed">{connection.provider} · {connection.configuration.model}</Text></Box></Group>
              <Group gap="xs"><Switch checked={connection.is_enabled} onChange={(event) => toggle(connection, event.currentTarget.checked)} aria-label={`Enable ${connection.name}`} /><ActionIcon color="red" variant="subtle" onClick={() => remove(connection)} aria-label={`Delete ${connection.name}`}><IconTrash size={17} /></ActionIcon></Group>
            </Group>
            {connection.next_trigger_at && <Text size="xs" c="dimmed" mt="sm">Next wake: {new Date(connection.next_trigger_at).toLocaleString()}</Text>}
            {connection.last_error && <Text size="xs" c="red" mt="sm">{connection.last_error}</Text>}
          </Card>
        ))}

        <Divider label="Add connection" labelPosition="center" my="sm" />
        <Box component="form" onSubmit={form.onSubmit(create)}>
          <Stack>
            <Select label="Provider" data={options.providers} {...form.getInputProps('provider')} />
            <TextInput label="Connection name" placeholder="Mira" {...form.getInputProps('name')} />
            <TextInput label="Model" placeholder="gpt-5" {...form.getInputProps('model')} />
            <Textarea label="Participant instructions" autosize minRows={3} {...form.getInputProps('instructions')} />
            <PasswordInput label="API key" {...form.getInputProps('api_key')} />
            <Select label="Trigger" data={options.trigger_modes} {...form.getInputProps('trigger_mode')} />
            {form.values.trigger_mode === 'interval' && <NumberInput label="Interval in minutes" min={1} {...form.getInputProps('interval_minutes')} />}
            <Button type="submit" color="green" loading={form.submitting} leftSection={<IconPlus size={17} />}>Save connection</Button>
          </Stack>
        </Box>
      </Stack>
    </Drawer>
  );
}
