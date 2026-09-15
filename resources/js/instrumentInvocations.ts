import type { InstrumentInput, ParticipantPlan } from './types';

export function normalizedInvocation(input: InstrumentInput): string | null {
  if (input.kind === 'web_search') return `web_search:${input.query.trim().replace(/\s+/gu, ' ')}`;
  if (input.kind === 'citation') return `citation:${input.reference.trim()}`;
  try {
    const url = new URL(input.reference.trim());
    if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) return null;
    url.pathname = url.pathname.replace(/%([a-f\d]{2})/gi, (encoded, hex: string) => {
      const character = String.fromCharCode(parseInt(hex, 16));
      return /^[A-Za-z\d._~-]$/.test(character) ? character : encoded.toUpperCase();
    });
    return `hyperlink:${url.href}`;
  } catch { return null; }
}

export function duplicateInstrument(plan: ParticipantPlan, target: string | undefined, input: InstrumentInput) {
  if (!target) return undefined;
  const signature = normalizedInvocation(input);
  return plan.schema?.['x-commons']?.instrument_exclusions?.[target]
    ?.find(item => signature !== null && normalizedInvocation(item.invocation) === signature);
}
