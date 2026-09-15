import '@mantine/core/styles.css';
import '../../resources/css/workspace.css';
import { MantineProvider, Box, Button, Group, Text, Title, useMantineColorScheme } from '@mantine/core';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
import { PropositionGraph } from '../../resources/js/components/PropositionGraph';
import { store } from '../../resources/js/store';
import type { DomainName, Proposition, RegisterName } from '../../resources/js/types';

function node(id: string, title: string, register: RegisterName, domain: DomainName, tension: number, touches: number, parents: string[], committal = false): Proposition {
  return { id, title, statement: title, register, tier: committal ? 'committal' : 'expressive', is_origin: parents.length === 0,
    parent_ids: parents, context_horizon: { id: domain, sequence: domain === 'emergence' ? 1 : 2, domain },
    participation_count: touches, attestation_counts: { support: 7, refute: 3 },
    field: { tension, charge: 1 - Math.abs(tension), potential_charge: .6 - Math.abs(tension) / 2, status: 'current',
      potential_status: 'current', source: committal ? 'commitment_snapshot' : 'measurement',
      potential_basis: committal ? 'region' : 'proposition', committal_proposition_id: 'origin' } };
}
const propositions = [
  node('origin', 'A shared ground for inquiry', 'canon', 'emergence', .8, 240, [], true),
  node('a', 'Let evidence reshape our assumptions', 'activity', 'emergence', -.85, 2, ['origin']),
  node('b', 'A useful distinction is emerging', 'frontier', 'emergence', .85, 20, ['origin']),
  node('c', 'Carry both paths into the next question', 'canon', 'emergence', .03, 80, ['a', 'b']),
  node('commit', 'Shared structure, ready to be tested', 'concord', 'settlement', -.4, 110, ['c'], true),
  node('signal', 'An observation challenges the model', 'signal', 'settlement', -.65, 6, ['commit']),
  node('stance', 'The boundary needs closer attention', 'stance', 'settlement', .55, 45, ['commit']),
];
function Preview() {
  const { toggleColorScheme } = useMantineColorScheme();
  return <Box h="100dvh" style={{ display: 'flex', flexDirection: 'column' }}>
    <Group p="md" justify="space-between"><Box><Title order={3}>The living substrate</Title><Text size="xs" c="dimmed">Synthetic visual review · drag to explore both Domains</Text></Box>
      <Button size="xs" variant="light" onClick={toggleColorScheme}>Toggle light / dark</Button></Group>
    <Box className="substrate-view__graph"><PropositionGraph propositions={propositions} /></Box>
  </Box>;
}
createRoot(document.getElementById('root')!).render(<Provider store={store}><MantineProvider defaultColorScheme="dark"><Preview /></MantineProvider></Provider>);
