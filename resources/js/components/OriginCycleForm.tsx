import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Alert, Button, Checkbox, Group, Modal, Stack, Text, Textarea } from '@mantine/core';
import { IconAlertCircle, IconArrowRight } from '@tabler/icons-react';
import { messageFrom } from '../errors';
import { InstrumentFields } from './InstrumentFields';
import { AttestationDirectionControl } from './AttestationDirectionControl';
import type { AttestationDirection, InstrumentInput, OriginInput } from '../types';

interface OriginFormValue {
  statement: string;
  includeAttestation: boolean;
  direction: AttestationDirection;
  rationale: string;
  instrument: InstrumentInput;
}

interface OriginCycleFormProps {
  opened: boolean;
  loading: boolean;
  error?: unknown;
  onClose: () => void;
  onSubmit: (origin: OriginInput) => void;
}

const initialValue: OriginFormValue = {
  statement: '',
  includeAttestation: false,
  direction: 'support',
  rationale: '',
  instrument: { kind: 'hyperlink', reference: '' },
};

export function OriginCycleForm({ opened, loading, error, onClose, onSubmit }: OriginCycleFormProps) {
  const [value, setValue] = useState(initialValue);

  useEffect(() => {
    if (!opened) setValue(initialValue);
  }, [opened]);

  function update<K extends keyof OriginFormValue>(field: K, next: OriginFormValue[K]) {
    setValue((current) => ({ ...current, [field]: next }));
  }

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    onSubmit({
      statement: value.statement,
      ...(value.includeAttestation ? { attestation: {
        direction: value.direction,
        ...(value.rationale ? { rationale: value.rationale } : {}),
      } } : {}),
      instrument: value.instrument,
    });
  }

  return (
    <Modal opened={opened} onClose={onClose} title="Establish the Origin" centered size="lg" closeOnClickOutside={!loading} closeOnEscape={!loading}>
      <form onSubmit={submit}>
        <Stack>
          <Text size="sm" c="dimmed">
            The Origin is the substrate’s first Committal Proposition. Its Receipt is recorded before the first live Cycle begins. An initial Attestation is optional.
          </Text>
          {Boolean(error) && (
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
          <Checkbox label="Add an initial Attestation" checked={value.includeAttestation}
            onChange={(event) => update('includeAttestation', event.currentTarget.checked)} />
          {value.includeAttestation && (
            <>
              <Stack gap={5}>
                <Text size="sm" fw={500}>Initial Attestation label</Text>
                <Text size="xs" c="dimmed">Origin starts in the positive direction. Choose whether to label that direction Support or Refute; this fixes the label polarity for its Commit Region.</Text>
                <AttestationDirectionControl
                  initialLabelChoice
                  value={value.direction}
                  onChange={(next) => update('direction', next)}
                  showLabels
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
            </>
          )}
          <InstrumentFields value={value.instrument} onChange={(instrument) => update('instrument', instrument)} includeCitation={false} />
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
