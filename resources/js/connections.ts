import type { Connection, ConnectionTrigger, TriggerMode } from './types';

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
