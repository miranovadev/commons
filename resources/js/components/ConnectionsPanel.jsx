import { useEffect, useState } from 'react';
import { ActionIcon, Alert, Box, Button, Card, Divider, Drawer, Group, NumberInput, PasswordInput, Select, Stack, Switch, Text, Textarea, TextInput, ThemeIcon, Title } from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconAlertCircle, IconPlugConnected, IconPlus, IconTrash } from '@tabler/icons-react';
import { api, csrf, messageFrom } from '../api';

export function ConnectionsPanel({ slug, participantId, opened, onClose }) {
  const base = `/substrates/${slug}/participants/${participantId}/connections`;
  const [connections, setConnections] = useState([]);
  const [options, setOptions] = useState({ providers: [], trigger_modes: [] });
  const [error, setError] = useState('');
  const form = useForm({
    initialValues: { provider: 'openai', name: '', model: '', instructions: '', api_key: '', trigger_mode: 'manual', interval_minutes: 60 },
    validate: { name: (v) => v.trim() ? null : 'A name is required.', model: (v) => v.trim() ? null : 'A model is required.', api_key: (v) => v.trim() ? null : 'An API key is required.' },
  });

  useEffect(() => {
    if (!opened) return;
    Promise.all([api.get(base), api.get('/connection-options')])
      .then(([connectionsResponse, optionsResponse]) => {
        setConnections(connectionsResponse.data.data);
        setOptions(optionsResponse.data.data);
      })
      .catch((requestError) => setError(messageFrom(requestError, 'Connections could not be loaded.')));
  }, [opened, base]);

  async function create(values) {
    form.clearErrors();
    try {
      await csrf();
      const trigger = { mode: values.trigger_mode };
      if (values.trigger_mode === 'interval') trigger.interval_minutes = values.interval_minutes;
      const response = await api.post(base, {
        provider: values.provider,
        name: values.name,
        configuration: { model: values.model, instructions: values.instructions || null, trigger },
        credentials: { api_key: values.api_key },
      });
      setConnections((current) => [...current, response.data.data]);
      form.reset();
    } catch (requestError) { form.setFieldError('name', messageFrom(requestError, 'The connection could not be saved.')); }
  }

  async function toggle(connection, is_enabled) {
    await csrf();
    const response = await api.patch(`${base}/${connection.id}`, { is_enabled });
    setConnections((current) => current.map((item) => item.id === connection.id ? response.data.data : item));
  }

  async function remove(connection) {
    await csrf();
    await api.delete(`${base}/${connection.id}`);
    setConnections((current) => current.filter((item) => item.id !== connection.id));
  }

  return (
    <Drawer opened={opened} onClose={onClose} position="right" size="lg" title="Participant connections">
      <Stack>
        <Text size="sm" c="dimmed">Connect this Participant to an external model and choose whether it wakes manually or on a wall-clock interval.</Text>
        {error && <Alert color="red" icon={<IconAlertCircle size={17} />}>{error}</Alert>}
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
