import { Alert, Button, Group, Modal, Select, Stack, Text, Textarea, TextInput } from '@mantine/core';
import { IconAlertCircle } from '@tabler/icons-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { messageFrom } from '../errors';
import { actionPropositions } from '../participationOptions';
import { AttestationDirectionControl } from './AttestationDirectionControl';
import type {
  ActionName,
  AttestationDirection,
  ContributionInitialValues,
  ContributionInput,
  InstrumentInput,
  ParticipantPlan,
  Proposition,
} from '../types';

type FormValues = ContributionInitialValues & {
  direction: AttestationDirection;
  instrument: InstrumentInput;
};

const emptyValues: ContributionInitialValues = {};

function option(proposition: Proposition) {
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
  initialValues = emptyValues,
  onClose,
  onSubmit,
}: {
  action: ActionName | null | undefined;
  plan: ParticipantPlan;
  opened: boolean;
  loading: boolean;
  error?: unknown;
  initialValues?: ContributionInitialValues;
  onClose: () => void;
  onSubmit: (contribution: ContributionInput) => void;
}) {
  const schema = action ? plan.schema?.$defs?.actions?.[action] : undefined;
  const [values, setValues] = useState<FormValues>({
    direction: 'support',
    instrument: { kind: 'citation', reference: '' },
  });
  const properties = schema?.properties || {};
  const required = schema?.required || [];
  const propositionOptions = useMemo(
    () => action ? actionPropositions(plan, action).map(option) : [],
    [action, plan],
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
      ...initialValues,
    });
  }, [action, initialValues, opened]);

  if (!schema || !action) return null;
  const activeAction = action;

  function set<K extends keyof FormValues>(name: K, value: FormValues[K]) {
    setValues((current) => ({ ...current, [name]: value }));
  }

  function setInstrument<K extends keyof InstrumentInput>(name: K, value: InstrumentInput[K]) {
    setValues((current) => ({
      ...current,
      instrument: { ...current.instrument, [name]: value },
    }));
  }

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const contribution: ContributionInput = { action: properties.action?.const || activeAction };

    (Object.keys(properties) as Array<keyof ContributionInput>).forEach((name) => {
      if (name === 'action') return;
      if (name === 'instrument') {
        contribution.instrument = values.instrument;
      } else if (values[name] !== undefined && values[name] !== '') {
        Object.assign(contribution, { [name]: values[name] });
      }
    });

    onSubmit(contribution);
  }

  return (
    <Modal opened={opened} onClose={onClose} title={schema.title} centered>
      <form onSubmit={submit}>
        <Stack>
          {Boolean(error) && (
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
              data={propositionOptions}
              required={required.includes('proposition_id')}
              value={values.proposition_id || null}
              onChange={(value) => set('proposition_id', value ?? undefined)}
            />
          )}

          {properties.other_proposition_id && (
            <Select
              searchable
              label="Reconcile with"
              data={gateOptions}
              required
              value={values.other_proposition_id || null}
              onChange={(value) => set('other_proposition_id', value ?? undefined)}
            />
          )}

          {properties.direction && (
            <Stack gap={5}>
              <Text size="sm" fw={500}>Direction</Text>
              <AttestationDirectionControl
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
                  { value: 'hyperlink', label: 'Hyperlink' },
                ]}
                required
                value={values.instrument?.kind || 'citation'}
                onChange={(value) => value && setInstrument('kind', value as InstrumentInput['kind'])}
              />
              <TextInput
                label="Reference"
                description={values.instrument?.kind === 'hyperlink'
                  ? 'HTTP(S) URL reference only; page contents are not fetched or verified.'
                  : 'The ID of an existing Proposition or Attestation in this substrate.'}
                required
                value={values.instrument?.reference || ''}
                onChange={(event) => setInstrument(
                  'reference',
                  event.currentTarget.value,
                )}
              />
            </>
          )}

          {action === 'participant.rest' && <Text size="sm" c="dimmed">End this Cycle without creating content. Your current position is retained for recovery when the next Cycle begins.</Text>}

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
