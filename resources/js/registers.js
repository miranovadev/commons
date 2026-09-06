export const registerMeta = {
  activity: { label: 'Activity', color: 'red' },
  frontier: { label: 'Frontier', color: 'green' },
  canon: { label: 'Canon', color: 'blue' },
  signal: { label: 'Signal', color: 'yellow' },
  stance: { label: 'Stance', color: 'pink' },
  concord: { label: 'Concord', color: 'cyan' },
};

export function metaForRegister(register) {
  return registerMeta[register] || {
    label: register || 'Unclassified',
    color: 'gray',
  };
}
