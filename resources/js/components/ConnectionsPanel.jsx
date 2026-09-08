import { ActionIcon, Alert, Box, Button, Card, Divider, Drawer, Group, NumberInput, Select, Stack, Switch, Text, ThemeIcon } from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconAlertCircle, IconPlugConnected, IconPlus, IconTrash } from '@tabler/icons-react';
import { connectionSelectData, triggerFor } from '../connections';
import { messageFrom } from '../errors';
import { useCreateParticipantConnectionMutation, useDeleteParticipantConnectionMutation, useGetConnectionOptionsQuery, useGetConnectionsQuery, useGetParticipantConnectionsQuery, useUpdateParticipantConnectionMutation } from '../services/commonsApi';

export function ConnectionsPanel({ slug, participantId, participantName, opened, onClose }) {
  const parameters = { slug, participantId };
  const { data: attachments = [], error: attachmentsError } = useGetParticipantConnectionsQuery(parameters, { skip: !opened });
  const { data: connections = [], error: connectionsError } = useGetConnectionsQuery(undefined, { skip: !opened });
  const { data: options = { trigger_modes: [] }, error: optionsError } = useGetConnectionOptionsQuery(undefined, { skip: !opened });
  const [createAttachment] = useCreateParticipantConnectionMutation();
  const [updateAttachment] = useUpdateParticipantConnectionMutation();
  const [deleteAttachment] = useDeleteParticipantConnectionMutation();
  const error = attachmentsError || connectionsError || optionsError;
  const attachedConnectionIds = new Set(attachments.map((attachment) => attachment.connection?.id || attachment.connection_id));
  const availableConnections = connections.filter((connection) => !attachedConnectionIds.has(connection.id));
  const form = useForm({
    initialValues: { connection_id: '', trigger_mode: 'manual', interval_minutes: 60 },
    validate: { connection_id: (value) => value ? null : 'Choose a connection.' },
  });

  async function attach(values) {
    form.clearErrors();
    try {
      await createAttachment({
        ...parameters,
        connection_id: values.connection_id,
        configuration: { trigger: triggerFor(values.trigger_mode, values.interval_minutes) },
      }).unwrap();
      form.reset();
    } catch (requestError) {
      form.setFieldError('connection_id', messageFrom(requestError, 'The connection could not be attached.'));
    }
  }

  async function updateSchedule(attachment, mode, intervalMinutes = 60) {
    await updateAttachment({
      ...parameters,
      participantConnectionId: attachment.id,
      configuration: { trigger: triggerFor(mode, intervalMinutes) },
    }).unwrap();
  }

  async function remove(attachment) {
    await deleteAttachment({ ...parameters, participantConnectionId: attachment.id }).unwrap();
  }

  return (
    <Drawer opened={opened} onClose={onClose} position="right" size="lg" title={participantName ? `${participantName} · Connections` : 'Participant connections'}>
      <Stack>
        <Text size="sm" c="dimmed">Attach one of your reusable Connections to this Participant. Wake settings belong to this Participant only, so the same Connection can behave differently in another substrate.</Text>
        {error && <Alert color="red" icon={<IconAlertCircle size={17} />}>{messageFrom(error, 'Connections could not be loaded.')}</Alert>}

        {attachments.map((attachment) => {
          const connection = attachment.connection || {};
          const trigger = attachment.configuration?.trigger || { mode: 'manual' };
          return (
            <Card key={attachment.id} withBorder padding="md">
              <Stack gap="sm">
                <Group justify="space-between" wrap="nowrap">
                  <Group wrap="nowrap"><ThemeIcon color="indigo" variant="light"><IconPlugConnected size={17} /></ThemeIcon><Box><Text fw={650}>{connection.name || 'Connection'}</Text><Text size="xs" c="dimmed">{connection.provider} · {connection.configuration?.model}</Text></Box></Group>
                  <Group gap="xs"><Switch checked={attachment.is_enabled} onChange={(event) => updateAttachment({ ...parameters, participantConnectionId: attachment.id, is_enabled: event.currentTarget.checked })} aria-label={`Enable ${connection.name || 'connection'}`} /><ActionIcon color="red" variant="subtle" onClick={() => remove(attachment)} aria-label={`Detach ${connection.name || 'connection'}`}><IconTrash size={17} /></ActionIcon></Group>
                </Group>
                <Select label="Wake mode" value={trigger.mode} data={options.trigger_modes} onChange={(value) => value && updateSchedule(attachment, value, trigger.interval_minutes || 60)} />
                {trigger.mode === 'interval' && <NumberInput label="Interval in minutes" min={1} defaultValue={trigger.interval_minutes || 60} onBlur={(event) => updateSchedule(attachment, 'interval', Math.max(1, Number(event.currentTarget.value) || 60))} />}
                {attachment.next_trigger_at && <Text size="xs" c="dimmed">Next wake: {new Date(attachment.next_trigger_at).toLocaleString()}</Text>}
                {attachment.last_error && <Text size="xs" c="red">{attachment.last_error}</Text>}
              </Stack>
            </Card>
          );
        })}

        <Divider label="Attach connection" labelPosition="center" my="sm" />
        {availableConnections.length ? (
          <Stack component="form" onSubmit={form.onSubmit(attach)}>
            <Select label="Connection" data={connectionSelectData(availableConnections)} {...form.getInputProps('connection_id')} />
            <Select label="Wake mode" data={options.trigger_modes} {...form.getInputProps('trigger_mode')} />
            {form.values.trigger_mode === 'interval' && <NumberInput label="Interval in minutes" min={1} {...form.getInputProps('interval_minutes')} />}
            <Button type="submit" loading={form.submitting} leftSection={<IconPlus size={17} />}>Attach connection</Button>
          </Stack>
        ) : <Text size="sm" c="dimmed">All of your Connections are already attached. Add another from the Connections page if needed.</Text>}
      </Stack>
    </Drawer>
  );
}
