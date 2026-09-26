import { SpecialPropositionBadges } from './SpecialPropositionBadges';
import { Card, VisuallyHidden } from '@mantine/core';
import { metaForRegister } from '../registers';
import { PropositionContent } from './PropositionContent';
import type { Observables, Proposition } from '../types';
import type { GraphDensity } from '../graphLayout';
import type { GraphOverlay } from '../graphOverlays';

interface PropositionCardProps {
  proposition: Proposition;
  selected?: boolean;
  compact?: boolean;
  mini?: boolean;
  graph?: boolean;
  density?: GraphDensity;
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
  density = 'detailed',
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
  const structurallyHighlighted = indigoHighlight;
  const classes = [
    'proposition-card',
    mini && 'proposition-card--mini',
    graph && 'proposition-card--graph',
    graph && density === 'topology' && 'proposition-card--topology',
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
      aria-label={density === 'topology' ? `${proposition.tier} ${register.label} proposition: ${proposition.statement || proposition.title || proposition.id}` : undefined}
      title={density === 'topology' ? [proposition.title || proposition.statement, overlay?.label].filter(Boolean).join(' · ') : overlay?.label || undefined}
      style={{ '--register-color': `var(--mantine-color-${register.color}-6)`,
        '--graph-overlay-color': `var(--mantine-color-${overlay?.color || 'indigo'}-6)`,
        '--graph-overlay-strength': `${(overlay?.intensity || 0) * 25}%` }}
      onClick={onClick}
    >
      {domain && <>
        <VisuallyHidden>{domain === 'settlement' ? 'Settlement' : 'Emergence'} Domain.</VisuallyHidden>
      </>}
      {density === 'topology' && <SpecialPropositionBadges proposition={proposition} compact />}
      {density !== 'topology' && <PropositionContent
        proposition={proposition}
        mode={mini ? 'minimal' : 'card'}
        actionLabel={actionLabel}
        actionIcon={actionIcon}
        wireframe={wireframe}
        projectedObservables={projectedObservables}
        committalObservables={committalObservables}
      />}
    </Card>
  );
}

