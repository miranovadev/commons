import { Card } from '@mantine/core';
import { metaForRegister } from '../registers';
import { PropositionContent } from './PropositionContent';

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
  flow,
  onClick,
}) {
  const register = metaForRegister(proposition.register);
  const structurallyHighlighted = indigoHighlight || proposition.tier === 'committal';
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
      />
    </Card>
  );
}
