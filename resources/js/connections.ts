import type { Connection, ConnectionTrigger, TriggerMode, ParticipantWakeConfiguration, WakeTermination, WakeOffsetMode } from './types';

export function triggerFor(mode: TriggerMode, intervalMinutes = 60): ConnectionTrigger {
  return mode === 'interval'
    ? { mode, interval_minutes: intervalMinutes }
    : { mode: 'manual' };
}

export function connectionSelectData(connections: Connection[]) {
  return connections.map((connection) => ({
    value: connection.id,
    label: `${connection.name} · ${connection.configuration?.model || connection.provider}`,
  }));
}


export interface WakeFormValues {
  trigger_mode: TriggerMode;
  interval_minutes: number;
  offset_mode: WakeOffsetMode;
  offset_seconds: number;
  random_from: number;
  random_to: number;
  termination: WakeTermination;
  timeout_seconds: number;
}

export function wakeFormValues(configuration?: ParticipantWakeConfiguration): WakeFormValues {
  const offset = configuration?.trigger?.offset;
  return {
    trigger_mode: configuration?.trigger?.mode ?? 'manual',
    interval_minutes: configuration?.trigger?.interval_minutes ?? 60,
    offset_mode: offset?.mode ?? 'seconds',
    offset_seconds: offset?.seconds ?? 0,
    random_from: offset?.random_from ?? 0,
    random_to: offset?.random_to ?? 60,
    termination: configuration?.wake?.termination ?? 'phase',
    timeout_seconds: configuration?.wake?.timeout_seconds ?? 600,
  };
}

export function wakeConfigurationFor(values: WakeFormValues): ParticipantWakeConfiguration {
  return {
    trigger: {
      ...triggerFor(values.trigger_mode, values.interval_minutes),
      offset: { mode: values.offset_mode, seconds: values.offset_seconds, random_from: values.random_from, random_to: values.random_to },
    },
    wake: { termination: values.termination, timeout_seconds: values.timeout_seconds },
  };
}

export function validateWakeValues(values: WakeFormValues): Record<string, string> {
  const errors: Record<string, string> = {};
  if (values.trigger_mode === 'interval' && (!Number.isInteger(values.interval_minutes) || values.interval_minutes < 1)) errors.interval_minutes = 'Use a positive whole number of minutes.';
  for (const key of ['offset_seconds', 'random_from', 'random_to'] as const) {
    if (!Number.isInteger(values[key]) || values[key] < 0 || values[key] > 60) errors[key] = 'Use a whole number from 0 to 60.';
  }
  if (values.offset_mode === 'random' && values.random_from > values.random_to) errors.random_to = 'To must be greater than or equal to From.';
  if (!Number.isInteger(values.timeout_seconds) || values.timeout_seconds < 1 || values.timeout_seconds > 1800) errors.timeout_seconds = 'Use 1–1,800 seconds (up to 30 minutes).';
  return errors;
}
