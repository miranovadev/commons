import { Box, Group, Text, UnstyledButton } from '@mantine/core';
import { IconSeedling, IconSunHigh, IconSunWind } from '@tabler/icons-react';
import { metaForDomain, metaForRegister } from '../registers';
import type { DomainName, Proposition, PropositionSelection, WorkspaceHorizon } from '../types';

/** Causal Horizon boundary and structural provenance are independent references. */
export function HorizonContext({ horizon, domain, provenance, onInspect }: {
  horizon?: WorkspaceHorizon | null; domain: DomainName; provenance: Proposition;
  onInspect: (selection: PropositionSelection) => void;
}) {
  const DomainIcon = domain === 'settlement' ? IconSunWind : IconSunHigh;
  function reference(proposition: Proposition, kind: string) {
    return <UnstyledButton className="horizon-context__reference" data-proposition-id={proposition.id}
      onClick={() => onInspect({ kind, proposition })}>
      <Text size="xs" truncate>{proposition.title || proposition.statement.split('\n')[0]}</Text>
      <Text size="10px">{metaForRegister(proposition.register).label} · {proposition.tier === 'committal' ? 'Committal' : 'Expressive'}</Text>
    </UnstyledButton>;
  }
  return <Box className="horizon-context">
    <Box component="section" className="horizon-context__boundary" data-domain={domain} aria-label="Cycle Horizon" data-horizon-id={horizon?.id}>
      <Group gap={8} wrap="nowrap"><DomainIcon size={22} aria-hidden="true" /><Box>
        <Text size="10px">Horizon</Text>
        <Text size="xs" fw={700}>{metaForDomain(domain).label}{horizon && ` · H${horizon.sequence}`}</Text>
      </Box></Group>
      {horizon?.opening_proposition ? <Box className="horizon-context__opening"><Text size="10px">Opened by</Text>
        {reference(horizon.opening_proposition, 'Horizon boundary')}</Box>
        : <Text size="xs">{!horizon ? 'Horizon details unavailable'
          : horizon.sequence === 1 && horizon.parent_context_horizon_id === null && horizon.opened_by_proposition_id === null
            ? 'Origin Horizon · no opening Committal' : 'Opening Committal details unavailable'}</Text>}
    </Box>
    <Box component="section" className="horizon-context__provenance" aria-label="Participant provenance">
      <Group gap={6}><IconSeedling size={16} aria-hidden="true" /><Text size="xs" fw={600}>Provenance</Text></Group>
      {reference(provenance, 'provenance')}
    </Box>
  </Box>;
}
