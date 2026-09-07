import { useEffect, useState } from 'react';
import { Alert, Button, Group, Modal, Select, Stack, Text, Textarea } from '@mantine/core';
import { IconAlertCircle, IconArrowRight } from '@tabler/icons-react';
import { messageFrom } from '../errors';
import { AttestationDirectionControl } from './AttestationDirectionControl';

const initialValue = {
  statement: '',
  direction: 'support',
  rationale: '',
  instrumentKind: 'hyperlink',
  reference: '',
};

export function OriginCycleForm({ opened, loading, error, onClose, onSubmit }) {
  const [value, setValue] = useState(initialValue);

  useEffect(() => {
    if (!opened) setValue(initialValue);
  }, [opened]);

  function update(field, next) {
    setValue((current) => ({ ...current, [field]: next }));
  }

  function submit(event) {
    event.preventDefault();
    onSubmit({
      statement: value.statement,
      attestation: {
        direction: value.direction,
        ...(value.rationale ? { rationale: value.rationale } : {}),
      },
      instrument: {
        kind: value.instrumentKind,
        reference: value.reference,
      },
    });
  }

  return (
    <Modal opened={opened} onClose={onClose} title="Establish the Origin" centered size="lg" closeOnClickOutside={!loading} closeOnEscape={!loading}>
      <form onSubmit={submit}>
        <Stack>
          <Text size="sm" c="dimmed">
            The Origin is the substrate’s first Committal Proposition. Its Attestation and Receipt are recorded beside it before the first live Cycle begins.
          </Text>
          {error && (
            <Alert color="red" icon={<IconAlertCircle size={18} />} title="The Origin could not be established">
              {messageFrom(error, 'Please check the Origin and try again.')}
            </Alert>
          )}
          <Textarea
            label="Origin statement"
            description="The proposition from which this substrate will descend."
            minRows={5}
            autosize
            required
            value={value.statement}
            onChange={(event) => update('statement', event.currentTarget.value)}
          />
          <Stack gap={5}>
            <Text size="sm" fw={500}>Attestation</Text>
            <AttestationDirectionControl
              value={value.direction}
              onChange={(next) => update('direction', next)}
              showLabels={false}
            />
          </Stack>
          <Textarea
            label="Attestation rationale"
            description="Optional context for the initial attestation."
            minRows={2}
            autosize
            value={value.rationale}
            onChange={(event) => update('rationale', event.currentTarget.value)}
          />
          <Select
            label="Receipt instrument"
            data={[
              { value: 'hyperlink', label: 'Hyperlink' },
              { value: 'citation', label: 'Citation' },
              { value: 'artefact', label: 'Artefact' },
            ]}
            value={value.instrumentKind}
            onChange={(next) => update('instrumentKind', next || 'hyperlink')}
          />
          <Textarea
            label="Receipt reference"
            description="A URL, citation, or artefact reference that grounds the Origin."
            minRows={2}
            autosize
            required
            value={value.reference}
            onChange={(event) => update('reference', event.currentTarget.value)}
          />
          <Group justify="flex-end">
            <Button variant="default" onClick={onClose} disabled={loading}>Cancel</Button>
            <Button type="submit" color="indigo" loading={loading} rightSection={<IconArrowRight size={16} />}>
              Establish and enter
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  );
}
