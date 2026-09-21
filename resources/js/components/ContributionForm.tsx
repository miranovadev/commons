import { Alert, Button, Group, Modal, MultiSelect, Select, Stack, Text, Textarea } from '@mantine/core';
import { IconAlertCircle } from '@tabler/icons-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { messageFrom } from '../errors';
import { actionPropositions, attestationDirections, receiptChoices } from '../participationOptions';
import { duplicateInstrument, replayReceiptIds } from '../instrumentInvocations';
import { InstrumentFields } from './InstrumentFields';
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
    () => actionPropositions(plan, 'proposition.reconcile').map(option),
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

  const directions = attestationDirections(plan, values.proposition_id);
  const direction = directions.includes(values.direction) ? values.direction : directions[0];
  const duplicate = action === 'instrument.execute' ? duplicateInstrument(plan, values.proposition_id, values.instrument) : undefined;

  const invalidReplay = action === 'instrument.execute' && 'replay_receipt_id' in values.instrument
    && !replayReceiptIds(plan, values.proposition_id).includes(values.instrument.replay_receipt_id);

  if (!schema || !action) return null;
  const activeAction = action;
  const commitment = values.proposition_id ? plan.commitment_options?.[values.proposition_id] : undefined;

  function set<K extends keyof FormValues>(name: K, value: FormValues[K]) {
    setValues((current) => ({ ...current, [name]: value }));
  }


  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (duplicate || invalidReplay) return;
    const contribution: ContributionInput = { action: properties.action?.const || activeAction };

    (Object.keys(properties) as Array<keyof ContributionInput>).forEach((name) => {
      if (name === 'action') return;
      if (name === 'instrument') {
        contribution.instrument = values.instrument;
      } else if (values[name] !== undefined && values[name] !== '') {
        Object.assign(contribution, { [name]: values[name] });
      }
    });

    if (activeAction === 'attestation.create') contribution.direction = direction;
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

          {properties.receipt_options && <Stack gap="xs">
            <Text size="sm" fw={500}>Gate Receipt loading</Text>
            <Text size="xs" c="dimmed">Apply these changes with your contribution. Loaded evidence appears when its Proposition occupies Read.</Text>
            {(['load', 'unload'] as const).map(operation => {
              const opposite = operation === 'load' ? 'unload' : 'load';
              const refs = receiptChoices(plan);
              return <MultiSelect key={operation} label={operation === 'load' ? 'Load Receipts' : 'Unload Receipts'}
                data={(properties.receipt_options?.properties?.[operation]?.items?.enum ?? [])
                  .filter(id => !values.receipt_options?.[opposite]?.includes(id))
                  .map(id => { const ref = refs.find(item => item.id === id); return { value: id, label: ref ? `${ref.reference} · ${ref.loaded ? 'Loaded' : 'Unloaded'}` : id }; })}
                value={values.receipt_options?.[operation] ?? []}
                onChange={ids => set('receipt_options', { ...values.receipt_options, [operation]: ids })} />;
            })}
          </Stack>}

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

          {properties.receipt_id && (
            <Select label="Receipt" description="Shared Commit Region evidence. Reclassification consumes this Receipt once."
              data={receiptChoices(plan).filter(receipt => properties.receipt_id?.enum?.includes(receipt.id))
                .map(receipt => ({ value: receipt.id, label: `${receipt.type === 'tragedy' ? 'Tragedy Receipt' : receipt.type} · ${receipt.id}` }))}
              required={required.includes('receipt_id')} value={values.receipt_id || null}
              onChange={value => set('receipt_id', value ?? undefined)} />
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
                supportDirection={actionPropositions(plan, 'attestation.create').find(item => item.id === values.proposition_id)?.attestation_summary?.support_direction}
                value={direction || 'support'}
                directions={directions}
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

          {properties.instrument && <InstrumentFields replayIds={replayReceiptIds(plan, values.proposition_id)} error={duplicate ? `This resource already has relevant evidence (Receipt ${duplicate.receipt_id}). Use the existing Receipt or choose another resource.` : undefined} value={values.instrument} onChange={(instrument) => set('instrument', instrument)} />}

          {action === 'proposition.reclassify' && <Text size="sm" c="dimmed">The statement is preserved. The selected Receipt attaches atomically and becomes consumed; the new classification begins without Attestations.</Text>}
          {action === 'proposition.reconcile' && <Text size="sm" c="dimmed">The new Proposition copies the current Gate statement exactly. The selected counterpart contributes a second hard parent edge.</Text>}


          {action === 'participant.rest' && <Text size="sm" c="dimmed">No admissible non-Rest action remains in this Phase. End this Cycle and prepare Phase 1 at an eligible Committal in a viable Domain.</Text>}

          {action === 'proposition.commit' && commitment?.closure && <Text size="sm">Selected source: Depth {commitment.closure.depth} ({commitment.closure.depth_direction}) · Cadence {commitment.closure.cadence} ({commitment.closure.cadence_direction}).</Text>}

          {action === 'proposition.commit' && (
            <Text size="sm" c="dimmed">
              This writes the selected expressive Proposition as an immutable
              Committal Proposition and closes the Cycle.
            </Text>
          )}

          <Group justify="flex-end">
            <Button variant="default" onClick={onClose}>Cancel</Button>
            <Button
              type="submit"
              color={action === 'proposition.commit' ? 'indigo' : 'blue'}
              loading={loading}
              disabled={Boolean(duplicate) || invalidReplay || (action === 'attestation.create' && !direction)}
            >
              Apply contribution
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  );
}
