import { metaForRegister } from './registers';
import type { Proposition } from './types';

export interface GraphOverlay {
  color: string;
  intensity: number;
  label: string;
}

interface OverlayDefinition {
  label: string;
  legend: string;
  resolve: (proposition: Proposition, scale: number) => GraphOverlay;
}
const wash = (color: string, intensity: number, label: string): GraphOverlay => ({ color, intensity, label });
const clamp = (value: number) => Math.max(0, Math.min(1, value));
const available = (value: unknown): value is number => typeof value === 'number' && Number.isFinite(value);
const measured = (label: string, value: number | null | undefined, status?: string) =>
  `${label}: ${available(value) ? value.toFixed(2) : 'awaiting measurement'}${status === 'stale' ? ' · last measured' : ''}`;

/** Adding a lens changes this registry, never the card's rendering rules. */
export const graphOverlayDefinitions = {
  none: { label: 'None', legend: 'Register and causal structure', resolve: () => wash('indigo', 0, '') },
  participation: {
    label: 'Participation', legend: 'Phase touches · low → high',
    resolve: (p, scale) => {
      const count = available(p.participation_count) ? Math.max(0, p.participation_count) : 0;
      return wash('indigo', count === 0 ? 0 : .16 + .84 * clamp(Math.log1p(count) / scale),
        `${count} Phase ${count === 1 ? 'touch' : 'touches'}`);
    },
  },
  'potential-charge': {
    label: 'Potential Charge', legend: 'Potential Charge · 0 → 1',
    resolve: p => {
      const value = p.field?.potential_charge;
      return wash('indigo', available(value) ? clamp(value) : 0,
        measured(p.field?.potential_basis === 'region' ? 'Region Potential Charge' : 'Potential Charge', value, p.field?.potential_status));
    },
  },
  tension: {
    label: 'Tension', legend: 'Blue −1 · neutral 0 · Violet +1',
    resolve: p => {
      const value = p.field?.tension;
      return wash(available(value) && value < 0 ? 'blue' : 'violet', available(value) ? clamp(Math.abs(value)) : 0,
        measured('Tension', value, p.field?.status));
    },
  },
  register: {
    label: 'Register', legend: 'Register colour · equal intensity',
    resolve: p => { const register = metaForRegister(p.register); return wash(register.color, .55, register.label); },
  },
} satisfies Record<string, OverlayDefinition>;

export type GraphOverlayMode = keyof typeof graphOverlayDefinitions;
export const graphOverlayOptions = Object.entries(graphOverlayDefinitions).map(([value, definition]) => ({ value, label: definition.label }));
export function isGraphOverlayMode(value: unknown): value is GraphOverlayMode {
  return typeof value === 'string' && Object.hasOwn(graphOverlayDefinitions, value);
}

export function resolveGraphOverlays(propositions: Proposition[], mode: GraphOverlayMode) {
  // log1p compresses count skew; p90 limits the effect of a single very hot node.
  // A small nonzero floor keeps even one touch legible in tiny/outlier datasets.
  const counts = mode === 'participation' ? propositions.map(p => p.participation_count ?? 0).filter(n => available(n) && n > 0).sort((a, b) => a - b) : [];
  const scale = Math.log1p(counts[Math.max(0, Math.ceil(counts.length * .9) - 1)] || 1);
  const definition: OverlayDefinition = graphOverlayDefinitions[mode];
  return new Map(propositions.map(p => [p.id, definition.resolve(p, scale)]));
}
