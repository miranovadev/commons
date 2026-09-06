import { Alert, Button, Group, Modal, SegmentedControl, Select, Stack, Text, Textarea, TextInput } from '@mantine/core';
import { IconAlertCircle } from '@tabler/icons-react';
import { useEffect, useMemo, useState } from 'react';
import { messageFrom } from '../errors';

function option(proposition) {
  return {
    value: proposition.id,
    label: proposition.title || proposition.statement.slice(0, 72),
  };
}

export function ContributionForm({
  action,
  plan,
  opened,
  loading,
  error,
  onClose,
  onSubmit,
}) {
  const schema = plan?.schema?.$defs?.actions?.[action];
  const [values, setValues] = useState({});
  const properties = schema?.properties || {};
  const required = schema?.required || [];
  const writeOptions = useMemo(
    () => (plan?.write_options || []).map(option),
    [plan],
  );
  const gateOptions = useMemo(
    () => (plan?.gate_options || []).map(option),
    [plan],
  );

  useEffect(() => {
    if (!opened) return;
    setValues({
      direction: 'support',
      instrument: { kind: 'citation', reference: '' },
    });
  }, [action, opened]);

  if (!schema) return null;

  function set(name, value) {
    setValues((current) => ({ ...current, [name]: value }));
  }

  function setInstrument(name, value) {
    setValues((current) => ({
      ...current,
      instrument: { ...current.instrument, [name]: value },
    }));
  }

  function submit(event) {
    event.preventDefault();
    const contribution = { action };

    Object.keys(properties).forEach((name) => {
      if (name === 'action') return;
      if (name === 'instrument') {
        contribution.instrument = values.instrument;
      } else if (values[name] !== undefined && values[name] !== '') {
        contribution[name] = values[name];
      }
    });

    onSubmit(contribution);
  }

  return (
    <Modal opened={opened} onClose={onClose} title={schema.title} centered>
      <form onSubmit={submit}>
        <Stack>
          {error && (
            <Alert color="red" icon={<IconAlertCircle size={17} />}>
              {messageFrom(error, 'The contribution could not be applied.')}
            </Alert>
          )}

          {properties.statement && (
            <Textarea
              label="Statement"
              description="The Proposition written into the current Phase register."
              minRows={5}
              maxLength={20000}
              required={required.includes('statement')}
              value={values.statement || ''}
              onChange={(event) => set('statement', event.currentTarget.value)}
            />
          )}

          {properties.proposition_id && (
            <Select
              searchable
              label="Proposition"
              data={writeOptions}
              required={required.includes('proposition_id')}
              value={values.proposition_id || null}
              onChange={(value) => set('proposition_id', value)}
            />
          )}

          {properties.other_proposition_id && (
            <Select
              searchable
              label="Reconcile with"
              data={gateOptions}
              required
              value={values.other_proposition_id || null}
              onChange={(value) => set('other_proposition_id', value)}
            />
          )}

          {properties.direction && (
            <Stack gap={5}>
              <Text size="sm" fw={500}>Direction</Text>
              <SegmentedControl
                fullWidth
                color={values.direction === 'support' ? 'violet' : 'teal'}
                data={[
                  { value: 'support', label: 'Support (+)' },
                  { value: 'refute', label: 'Refute (−)' },
                ]}
                value={values.direction || 'support'}
                onChange={(value) => set('direction', value)}
              />
            </Stack>
          )}

          {properties.rationale && (
            <Textarea
              label="Rationale"
              description="Optional context; it does not directly reinforce the Attestation."
              minRows={3}
              value={values.rationale || ''}
              onChange={(event) => set('rationale', event.currentTarget.value)}
            />
          )}

          {properties.instrument && (
            <>
              <Select
                label="Instrument"
                data={[
                  { value: 'citation', label: 'Citation' },
                  { value: 'artefact', label: 'Artefact' },
                  { value: 'hyperlink', label: 'Hyperlink' },
                ]}
                required
                value={values.instrument?.kind || 'citation'}
                onChange={(value) => setInstrument('kind', value)}
              />
              <TextInput
                label="Reference"
                required
                value={values.instrument?.reference || ''}
                onChange={(event) => setInstrument(
                  'reference',
                  event.currentTarget.value,
                )}
              />
            </>
          )}

          {action === 'proposition.commit' && (
            <Text size="sm" c="dimmed">
              This writes the current expressive Gate as an immutable
              Committal Proposition and closes the Cycle.
            </Text>
          )}

          <Group justify="flex-end">
            <Button variant="default" onClick={onClose}>Cancel</Button>
            <Button
              type="submit"
              color={action === 'proposition.commit' ? 'indigo' : 'blue'}
              loading={loading}
            >
              Apply contribution
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  );
}
