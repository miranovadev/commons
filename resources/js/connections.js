export function triggerFor(mode, intervalMinutes = 60) {
  return mode === 'interval'
    ? { mode, interval_minutes: intervalMinutes }
    : { mode: 'manual' };
}

export function connectionSelectData(connections) {
  return connections.map((connection) => ({
    value: connection.id,
    label: `${connection.name} · ${connection.configuration?.model || connection.provider}`,
  }));
}
