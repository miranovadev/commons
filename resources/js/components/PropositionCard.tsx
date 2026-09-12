import { Card } from '@mantine/core';
import { metaForRegister } from '../registers';
import { PropositionContent } from './PropositionContent';
import type { Observables, Proposition } from '../types';

interface PropositionCardProps {
  proposition: Proposition;
  selected?: boolean;
  compact?: boolean;
  mini?: boolean;
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
  const structurallyHighlighted = indigoHighlight;
  const classes = [
    'proposition-card',
    mini && 'proposition-card--mini',
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
      style={{ '--register-color': `var(--mantine-color-${register.color}-6)` }}
      onClick={onClick}
    >
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
