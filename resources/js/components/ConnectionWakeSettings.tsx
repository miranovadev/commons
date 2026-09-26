import { Alert, Button, Group, NumberInput, Select, Stack, Text } from '@mantine/core';
import { useForm } from '@mantine/form';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { validateWakeValues, wakeConfigurationFor, wakeFormValues } from '../connections';
import type { WakeFormValues } from '../connections';
import type { ParticipantWakeConfiguration } from '../types';
import { messageFrom } from '../errors';

interface FieldsProps {
  values: WakeFormValues;
  onChange: <K extends keyof WakeFormValues>(key: K, value: WakeFormValues[K]) => void;
  errors?: Record<string, ReactNode>;
}

export function ConnectionWakeSettings({ values, onChange, errors = {} }: FieldsProps) {
  const integer = (key: 'interval_minutes' | 'offset_seconds' | 'random_from' | 'random_to' | 'timeout_seconds', label: string, min: number, max?: number) => (
    <NumberInput label={label} value={Number.isNaN(values[key]) ? '' : values[key]} min={min} max={max} allowDecimal={false} allowNegative={false} clampBehavior="none" onChange={(value) => onChange(key, value === '' ? NaN : Number(value))} error={errors[key]} />
  );
  return <Stack gap="sm">
    <Select label="Wake mode" value={values.trigger_mode} data={[{ value: 'manual', label: 'Manual' }, { value: 'interval', label: 'Interval' }]} allowDeselect={false} onChange={(value) => value && onChange('trigger_mode', value as WakeFormValues['trigger_mode'])} />
    {values.trigger_mode === 'interval' && <>
      {integer('interval_minutes', 'Interval in minutes', 1)}
      <Select label="Offset" value={values.offset_mode} data={[{ value: 'seconds', label: 'Seconds' }, { value: 'random', label: 'Random' }]} allowDeselect={false} onChange={(value) => value && onChange('offset_mode', value as WakeFormValues['offset_mode'])} />
      {values.offset_mode === 'seconds' ? integer('offset_seconds', 'Offset seconds', 0, 60) : <Group grow align="start">{integer('random_from', 'From (seconds)', 0, 60)}{integer('random_to', 'To (seconds)', 0, 60)}</Group>}
      <Text size="xs" c="dimmed">{values.offset_mode === 'random' ? 'Sampled independently for each scheduled wake. ' : ''}Offsets delay each wake without changing the interval.</Text>
    </>}
    <Select label="End wake job after" value={values.termination} data={[{ value: 'phase', label: 'Phase' }, { value: 'cycle', label: 'Cycle' }, { value: 'rest', label: 'Rest' }]} allowDeselect={false} onChange={(value) => value && onChange('termination', value as WakeFormValues['termination'])} />
    {values.termination === 'rest' && <Text size="xs" c="dimmed">Continue across Cycles until a valid Rest occurs. This does not force Rest.</Text>}
    {integer('timeout_seconds', 'Timeout (seconds)', 1, 1800)}
    <Text size="xs" c="dimmed">600 seconds = 10 minutes. Maximum 30 minutes for the whole wake job.</Text>
  </Stack>;
}

export function ConnectionWakeForm({ configuration, onSave }: { configuration?: ParticipantWakeConfiguration; onSave: (configuration: ParticipantWakeConfiguration) => Promise<unknown> }) {
  const [error, setError] = useState<string | null>(null);
  const form = useForm<WakeFormValues>({ initialValues: wakeFormValues(configuration), validate: validateWakeValues });
  return <form onSubmit={form.onSubmit(async (values) => {
    setError(null);
    try { await onSave(wakeConfigurationFor(values)); form.resetDirty(values); }
    catch (failure) { setError(messageFrom(failure, 'Wake settings could not be saved.')); }
  })}>
    <Stack gap="sm">
      <ConnectionWakeSettings values={form.values} onChange={(key, value) => form.setValues((current) => ({ ...current, [key]: value }))} errors={form.errors} />
      {error && <Alert color="red">{error}</Alert>}
      <Button type="submit" size="xs" loading={form.submitting} disabled={!form.isDirty()}>Save wake settings</Button>
    </Stack>
  </form>;
}
