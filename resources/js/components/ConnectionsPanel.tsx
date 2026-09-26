import { ActionIcon, Alert, Box, Button, Card, Divider, Drawer, Group, Select, Stack, Switch, Text, ThemeIcon } from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconAlertCircle, IconPlugConnected, IconPlus, IconTrash } from '@tabler/icons-react';
import { connectionSelectData, wakeFormValues, wakeConfigurationFor, validateWakeValues } from '../connections';
import type { WakeFormValues } from '../connections';
import { ConnectionWakeForm, ConnectionWakeSettings } from './ConnectionWakeSettings';
import { messageFrom } from '../errors';
import { useCreateParticipantConnectionMutation, useDeleteParticipantConnectionMutation, useGetConnectionOptionsQuery, useGetConnectionsQuery, useGetParticipantConnectionsQuery, useUpdateParticipantConnectionMutation } from '../services/commonsApi';
import type { Identifier, ParticipantConnection, ParticipantParameters } from '../types';

interface ConnectionsPanelProps {
  slug?: string;
  participantId?: Identifier;
  participantName?: string;
  opened: boolean;
  onClose: () => void;
}

interface AttachmentFormValues extends WakeFormValues {
  connection_id: Identifier;
}

export function ConnectionsPanel({ slug, participantId, participantName, opened, onClose }: ConnectionsPanelProps) {
  const parameters: ParticipantParameters = { slug: slug ?? '', participantId: participantId ?? '' };
  const { data: attachments = [], error: attachmentsError } = useGetParticipantConnectionsQuery(parameters, { skip: !opened });
  const { data: connections = [], error: connectionsError } = useGetConnectionsQuery(undefined, { skip: !opened });
  const { data: options = { trigger_modes: [] }, error: optionsError } = useGetConnectionOptionsQuery(undefined, { skip: !opened });
  const [createAttachment] = useCreateParticipantConnectionMutation();
  const [updateAttachment] = useUpdateParticipantConnectionMutation();
  const [deleteAttachment] = useDeleteParticipantConnectionMutation();
  const error = attachmentsError || connectionsError || optionsError;
  const attachedConnectionIds = new Set(attachments.map((attachment) => attachment.connection?.id || attachment.connection_id));
  const availableConnections = connections.filter((connection) => !attachedConnectionIds.has(connection.id));
  const form = useForm<AttachmentFormValues>({
    initialValues: { connection_id: '', ...wakeFormValues() },
    validate: (values) => ({ ...validateWakeValues(values), ...(!values.connection_id ? { connection_id: 'Choose a connection.' } : {}) }),
  });

  async function attach(values: AttachmentFormValues) {
    form.clearErrors();
    try {
      await createAttachment({
        ...parameters,
        connection_id: values.connection_id,
        configuration: wakeConfigurationFor(values),
      }).unwrap();
      form.reset();
    } catch (requestError) {
      form.setFieldError('connection_id', messageFrom(requestError, 'The connection could not be attached.'));
    }
  }

  async function remove(attachment: ParticipantConnection) {
    await deleteAttachment({ ...parameters, participantConnectionId: attachment.id }).unwrap();
  }

  return (
    <Drawer opened={opened} onClose={onClose} position="right" size="lg" title={participantName ? `${participantName} · Connections` : 'Participant connections'}>
      <Stack>
        <Text size="sm" c="dimmed">Attach one of your reusable Connections to this Participant. Wake settings belong to this Participant only, so the same Connection can behave differently in another substrate.</Text>
        {error && <Alert color="red" icon={<IconAlertCircle size={17} />}>{messageFrom(error, 'Connections could not be loaded.')}</Alert>}

        {attachments.map((attachment) => {
          const connection = attachment.connection;
          return (
            <Card key={attachment.id} withBorder padding="md">
              <Stack gap="sm">
                <Group justify="space-between" wrap="nowrap">
                  <Group wrap="nowrap"><ThemeIcon color="indigo" variant="light"><IconPlugConnected size={17} /></ThemeIcon><Box><Text fw={650}>{connection?.name || 'Connection'}</Text><Text size="xs" c="dimmed">{connection?.provider} · {connection?.configuration?.model}</Text></Box></Group>
                  <Group gap="xs"><Switch checked={attachment.is_enabled} onChange={(event) => updateAttachment({ ...parameters, participantConnectionId: attachment.id, is_enabled: event.currentTarget.checked })} aria-label={`Enable ${connection?.name || 'connection'}`} /><ActionIcon color="red" variant="subtle" onClick={() => remove(attachment)} aria-label={`Detach ${connection?.name || 'connection'}`}><IconTrash size={17} /></ActionIcon></Group>
                </Group>
                <ConnectionWakeForm key={JSON.stringify(attachment.configuration)} configuration={attachment.configuration} onSave={(configuration) => updateAttachment({ ...parameters, participantConnectionId: attachment.id, configuration }).unwrap()} />
                {attachment.next_trigger_at && <Text size="xs" c="dimmed">Next scheduled trigger: {new Date(attachment.next_trigger_at).toLocaleString()}</Text>}
                {attachment.last_error && <Text size="xs" c="red">{attachment.last_error}</Text>}
              </Stack>
            </Card>
          );
        })}

        <Divider label="Attach connection" labelPosition="center" my="sm" />
        {availableConnections.length ? (
          <form onSubmit={form.onSubmit(attach)}>
            <Stack>
              <Select label="Connection" data={connectionSelectData(availableConnections)} {...form.getInputProps('connection_id')} />
              <ConnectionWakeSettings values={form.values} onChange={(key, value) => form.setValues((current) => ({ ...current, [key]: value }))} errors={form.errors} />
              <Button type="submit" loading={form.submitting} leftSection={<IconPlus size={17} />}>Attach connection</Button>
            </Stack>
          </form>
        ) : <Text size="sm" c="dimmed">All of your Connections are already attached. Add another from the Connections page if needed.</Text>}
      </Stack>
    </Drawer>
  );
}

