import { Tooltip } from '@mantine/core';
import { IconArrowsJoin, IconBolt, IconDropletFilled, IconSeedling, IconSunrise, IconSunset } from '@tabler/icons-react';
import type { Proposition } from '../types';

const definitions = {
  tragedy: { icon: IconDropletFilled, label: 'Tragedy', color: 'red.6' },
  renewal: { icon: IconSeedling, label: 'Renewal', color: 'green.6' },
  confluence: { icon: IconArrowsJoin, label: 'Confluence', color: 'indigo.5' },
  committal: { icon: IconBolt, label: 'Committal', color: 'gray.6' },
  'settlement-emergence': { icon: IconSunrise, label: 'Settlement → Emergence', color: 'indigo.4' },
  'emergence-settlement': { icon: IconSunset, label: 'Emergence → Settlement', color: 'indigo.9' },
} as const;

/** Display only: provenance flags are supplied by the observation API. */
export function specialBadgesFor(proposition: Partial<Proposition>) {
  const types: Array<keyof typeof definitions> = [];
  if (proposition.tragedy) types.push('tragedy');
  if (proposition.renewal) types.push('renewal');
  if (proposition.confluence) types.push('confluence');
  if (proposition.tier === 'committal') {
    types.push('committal');
    if (proposition.domain_transition === 'settlement-emergence' || proposition.domain_transition === 'emergence-settlement') {
      types.push(proposition.domain_transition);
    }
  }
  return types.map(type => ({ type, ...definitions[type] }));
}

export function SpecialPropositionBadges({ proposition, compact = false }: { proposition: Partial<Proposition>; compact?: boolean }) {
  const badges = specialBadgesFor(proposition);
  if (!badges.length) return null;
  return <span className="special-proposition-badges" data-compact={compact || undefined}>
    {badges.map(({ type, icon: Icon, label, color }) => <Tooltip key={type} label={label} withArrow>
      <span className="special-proposition-badge" data-event={type} role="img" aria-label={label} title={label}
        style={{ color: `var(--mantine-color-${color.replace('.', '-')})` }}>
        <Icon size={compact ? 12 : 14} aria-hidden="true" />
      </span>
    </Tooltip>)}
  </span>;
}
