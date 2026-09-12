/** Historical relationships are distinct from imperative action labels. */
const historyLabels: Record<string, string> = {
  variant: 'Variant', reclassify: 'Reclassified', revise: 'Revised', commit: 'Committed',
  reconcile: 'Reconciled', disperse: 'Dispersed', mediate: 'Mediated', bias: 'Biased',
};
export function operationHistoryLabel(type?: string) {
  const key = (type || '').replace(/^proposition\./, '').toLowerCase();
  return historyLabels[key] || (key ? key.charAt(0).toUpperCase() + key.slice(1) : 'Supersession');
}
