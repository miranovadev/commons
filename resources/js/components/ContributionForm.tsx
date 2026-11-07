import { Alert, Button, Group, Modal, MultiSelect, Select, Stack, Text, Textarea } from '@mantine/core';
import { IconAlertCircle } from '@tabler/icons-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { messageFrom } from '../errors';
import { actionTargetIds, attestationDirections, reclassificationReceiptRule } from '../participationOptions';
import { duplicateInstrument, replayReceiptIds } from '../instrumentInvocations';
import { PreparationFields } from './PreparationFields';
import { InstrumentFields } from './InstrumentFields';
import { AttestationDirectionControl } from './AttestationDirectionControl';
import type {
  ActionName,
  AttestationDirection,
  ContributionInitialValues,
  ContributionInput,
  InstrumentInput,
  ReadyParticipation,
} from '../types';

type FormValues = ContributionInitialValues & {
  direction: AttestationDirection;
  instrument: InstrumentInput;
};

const emptyValues: ContributionInitialValues = {};

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
  plan: ReadyParticipation;
  opened: boolean;
  loading: boolean;
  error?: unknown;
  initialValues?: ContributionInitialValues;
  onClose: () => void;
  onSubmit: (contribution: ContributionInput) => void;
}) {
  const schema = action ? plan.schema.$defs?.actions?.[action] : undefined;
  const [values, setValues] = useState<FormValues>({
    direction: 'support',
    instrument: { kind: 'citation', reference: '' },
  });
  const properties = schema?.properties || {};
  const required = schema?.required || [];
  const labels = useMemo(() => new Map([plan.context.read, plan.context.gate, plan.context.write]
    .flatMap(register => register.propositions.map(node => [node.id, node.statement.slice(0, 72)] as const))), [plan.context]);
  const propositionOptions = action ? [...actionTargetIds(plan, action)].map(id => ({ value: id, label: labels.get(id) || id })) : [];
  useEffect(() => {
    if (!opened) return;
    setValues({
      direction: 'support',
      instrument: { kind: 'citation', reference: '' },
      ...(action === 'preparation.start' ? { proposition_id: plan.context.gate.acting_id, artifacts: [] } : {}),
      ...(properties.execution_id?.const ? { execution_id: properties.execution_id.const } : {}),
      ...initialValues,
    });
  }, [action, initialValues, opened]);

  const directions = attestationDirections(plan, values.proposition_id);
  const direction = directions.includes(values.direction) ? values.direction : directions[0];
  const duplicate = action === 'instrument.execute' ? duplicateInstrument(plan, values.proposition_id, values.instrument) : undefined;

  const forms = properties.instrument?.anyOf ?? plan.schema.$defs?.instrument?.anyOf ?? [];
  const kinds = forms.flatMap(form => form.properties?.kind?.const ? [form.properties.kind.const] : []);
  const invalidKind = action === 'instrument.execute' && 'kind' in values.instrument && !kinds.includes(values.instrument.kind);
  const invalidReplay = action === 'instrument.execute' && 'replay_receipt_id' in values.instrument
    && !replayReceiptIds(plan, values.proposition_id).includes(values.instrument.replay_receipt_id);

  const receiptRule = reclassificationReceiptRule(plan, values.proposition_id);
  const receiptIds = receiptRule?.const ? [receiptRule.const] : receiptRule?.enum ?? [];
  const invalidReceipt = action === 'proposition.reclassify' && !receiptIds.includes(values.receipt_id ?? '');

  if (!schema || !action) return null;
  const activeAction = action;

  function set<K extends keyof FormValues>(name: K, value: FormValues[K]) {
    setValues((current) => ({ ...current, [name]: value }));
  }

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (duplicate || invalidKind || invalidReplay || invalidReceipt) return;
    const contribution: ContributionInput = { action: (properties.action?.const as ActionName) || activeAction };

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
            <Text size="sm" fw={500}>Receipt context loading</Text>
            <Text size="xs" c="dimmed">Apply these changes with your contribution. Ordinary evidence appears at Read; Tragedy memory recalls the failed lineage without Reinforcement.</Text>
            {(['load', 'unload'] as const).map(operation => {
              const opposite = operation === 'load' ? 'unload' : 'load';
              const refs = plan.context.receipts;
              return <MultiSelect key={operation} label={operation === 'load' ? 'Load Receipts' : 'Unload Receipts'}
                data={(properties.receipt_options?.properties?.[operation]?.items?.enum ?? [])
                  .filter(id => !values.receipt_options?.[opposite]?.includes(id))
                  .map(id => { const ref = refs.find(item => item.id === id); return { value: id, label: ref ? `${ref.type === 'tragedy' ? 'Tragedy memory · ' : ''}${ref.reference} · ${ref.loaded ? 'Loaded' : 'Unloaded'}` : id }; })}
                maxValues={properties.receipt_options?.properties?.[operation]?.maxItems}
                value={values.receipt_options?.[operation] ?? []}
                onChange={ids => set('receipt_options', { ...values.receipt_options, [operation]: ids })} />;
            })}
          </Stack>}

          {properties.purpose && <Textarea label="Preparation purpose" required maxLength={properties.purpose.maxLength} value={values.purpose ?? ''} onChange={event => set('purpose', event.currentTarget.value)} />}
          {properties.artifacts && <PreparationFields options={plan.context.gate.preparation_options ?? []} value={values.artifacts ?? []} onChange={items => set('artifacts', items)} />}
          {properties.reason && <Textarea label="Deferment reason" description="Your reason is shared historical context, not a verified claim about the sources." required maxLength={properties.reason.maxLength} value={values.reason ?? ''} onChange={event => set('reason', event.currentTarget.value)} />}
          {properties.statement && (
            <Textarea
              label="Statement"
              description="The Proposition written into the current Phase register."
              minRows={5}
              maxLength={properties.statement.maxLength}
              required={required.includes('statement')}
              value={values.statement || ''}
              onChange={(event) => set('statement', event.currentTarget.value)}
            />
          )}

          {properties.proposition_id && action !== 'preparation.start' && (
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
            <Select label="Receipt" description={receiptRule?.description ?? "Shared Commit Region evidence. Reclassification consumes this Receipt once."}
              data={receiptIds.map(id => {
                const receipt = plan.context.receipts.find(item => item.id === id);
                return { value: id, label: receipt ? `${receipt.type} · ${receipt.reference} · ${id}` : id };
              })}
              required={required.includes('receipt_id')} value={receiptIds.includes(values.receipt_id ?? '') ? values.receipt_id : null}
              onChange={value => set('receipt_id', value ?? undefined)} />
          )}

          {properties.other_proposition_id && (
            <Select
              searchable
              label="Reconcile with"
              data={propositionOptions}
              required
              value={values.other_proposition_id || null}
              onChange={(value) => set('other_proposition_id', value ?? undefined)}
            />
          )}

          {properties.direction && (
            <Stack gap={5}>
              <Text size="sm" fw={500}>Direction</Text>
              <AttestationDirectionControl
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

          {properties.instrument && <InstrumentFields kinds={kinds} replayIds={replayReceiptIds(plan, values.proposition_id)} error={duplicate ? `This resource already has relevant evidence (Receipt ${duplicate.receipt_id}). Use the existing Receipt or choose another resource.` : undefined} value={values.instrument} onChange={(instrument) => set('instrument', instrument)} />}

          {action === 'proposition.reclassify' && <Text size="sm" c="dimmed">Select one eligible Receipt and write what it supports about the source. You may keep the original wording. The Receipt attaches to the new Proposition, which starts without Attestations.</Text>}
          {action === 'proposition.reconcile' && <Text size="sm" c="dimmed">Explain how the two Propositions relate. Both become parents of the new Proposition. No Receipt is consumed.</Text>}

          {action === 'participant.rest' && <Text size="sm" c="dimmed">Rest ends this Cycle and begins recovery.</Text>}

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
              disabled={(Boolean(duplicate) || invalidKind || invalidReplay || invalidReceipt || (action === 'attestation.create' && !direction))}
            >
              Apply contribution
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  );
}
