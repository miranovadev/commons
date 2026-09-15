import { Card, VisuallyHidden } from '@mantine/core';
import { IconSunHigh, IconSunWind } from '@tabler/icons-react';
import { metaForRegister } from '../registers';
import { PropositionContent } from './PropositionContent';
import type { Observables, Proposition } from '../types';
import type { GraphOverlay } from '../graphOverlays';

interface PropositionCardProps {
  proposition: Proposition;
  selected?: boolean;
  compact?: boolean;
  mini?: boolean;
  graph?: boolean;
  overlay?: GraphOverlay;
  wireframe?: boolean;
  muted?: boolean;
  indigoHighlight?: boolean;
  actionLabel?: string;
  actionIcon?: React.ElementType;
  projectedObservables?: Observables;
  committalObservables?: Observables;
  flow?: string;
  onClick?: () => void;
}

export function PropositionCard({
  proposition,
  selected = false,
  compact = false,
  mini = false,
  graph = false,
  overlay,
  wireframe = false,
  muted = false,
  indigoHighlight = false,
  actionLabel,
  actionIcon,
  projectedObservables,
  committalObservables,
  flow,
  onClick,
}: PropositionCardProps) {
  const register = metaForRegister(proposition.register);
  const domain = graph ? proposition.context_horizon?.domain : undefined;
  const DomainIcon = domain === 'settlement' ? IconSunWind : IconSunHigh;
  const structurallyHighlighted = indigoHighlight;
  const classes = [
    'proposition-card',
    mini && 'proposition-card--mini',
    graph && 'proposition-card--graph',
    selected && 'proposition-card--selected',
    wireframe && 'proposition-card--wireframe',
    muted && 'proposition-card--muted',
    structurallyHighlighted && 'proposition-card--indigo',
  ].filter(Boolean).join(' ');

  return (
    <Card
      component={onClick ? 'button' : 'div'}
      type={onClick ? 'button' : undefined}
      withBorder
      padding={mini ? 'xs' : compact ? 'sm' : 'lg'}
      className={classes}
      data-flow={flow || undefined}
      data-register={proposition.register}
      data-proposition-id={proposition.id}
      data-domain={domain}
      aria-pressed={onClick ? selected : undefined}
      title={overlay?.label || undefined}
      style={{ '--register-color': `var(--mantine-color-${register.color}-6)`,
        '--graph-overlay-color': `var(--mantine-color-${overlay?.color || 'indigo'}-6)`,
        '--graph-overlay-strength': `${(overlay?.intensity || 0) * 25}%` }}
      onClick={onClick}
    >
      {domain && <>
        <DomainIcon className="proposition-card__domain" size={152} stroke={2.1} aria-hidden="true" />
        <VisuallyHidden>{domain === 'settlement' ? 'Settlement' : 'Emergence'} Domain.</VisuallyHidden>
      </>}
      <PropositionContent
        proposition={proposition}
        mode={mini ? 'minimal' : 'card'}
        actionLabel={actionLabel}
        actionIcon={actionIcon}
        wireframe={wireframe}
        projectedObservables={projectedObservables}
        committalObservables={committalObservables}
      />
    </Card>
  );
}
