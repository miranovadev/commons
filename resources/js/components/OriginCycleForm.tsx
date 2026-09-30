import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Alert, Button, Radio, Group, Modal, Stack, Text, Textarea } from '@mantine/core';
import { IconAlertCircle, IconArrowRight } from '@tabler/icons-react';
import { messageFrom } from '../errors';
import type { AttestationDirection, OriginInput } from '../types';

interface OriginFormValue {
  statement: string;
  direction: AttestationDirection | '';
  rationale: string;
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
  direction: '',
  rationale: '',
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
    if (!value.direction) return;
    onSubmit({
      statement: value.statement,
      attestation: {
        direction: value.direction,
        ...(value.rationale ? { rationale: value.rationale } : {}),
      },
    });
  }

  return (
    <Modal opened={opened} onClose={onClose} title="Establish the Origin" centered size="lg" closeOnClickOutside={!loading} closeOnEscape={!loading}>
      <form onSubmit={submit}>
        <Stack>
          <Text size="sm" c="dimmed">
            Choose the initial Attestation for the substrate’s first Committal Proposition. No Receipt is required for Origin.
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
          <Radio.Group label="Initial Attestation" value={value.direction} onChange={(next) => update('direction', next as AttestationDirection)} withAsterisk>
            <Text size="xs" c="dimmed">Your chosen label establishes the substrate’s positive relational direction. Positive and negative do not mean good and bad.</Text>
            <Group mt="xs">
              <Radio value="support" label="Support" required />
              <Radio value="refute" label="Refute" required />
            </Group>
          </Radio.Group>
          <Textarea
            label="Attestation rationale"
            description="Optional context for the initial attestation."
            minRows={2}
            autosize
            value={value.rationale}
            onChange={(event) => update('rationale', event.currentTarget.value)}
          />
          <Group justify="flex-end">
            <Button variant="default" onClick={onClose} disabled={loading}>Cancel</Button>
            <Button type="submit" disabled={!value.direction} color="indigo" loading={loading} rightSection={<IconArrowRight size={16} />}>
              Establish and enter
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  );
}
