import '@mantine/core/styles.css';
import '../../resources/css/workspace.css';
import { MantineProvider, Box, Button, Group, Text, Title, useMantineColorScheme } from '@mantine/core';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
import { useState } from 'react';
import { PropositionGraph } from '../../resources/js/components/PropositionGraph';
import { store } from '../../resources/js/store';
import type { DomainName, GraphParticipant, Proposition, RegisterName } from '../../resources/js/types';

function node(id: string, title: string, register: RegisterName, domain: DomainName, tension: number, touches: number, parents: string[], committal = false): Proposition {
  return { id, title, statement: title, register, tier: committal ? 'committal' : 'expressive', is_origin: parents.length === 0,
    parent_ids: parents, context_horizon: { id: domain, sequence: domain === 'emergence' ? 1 : 2, domain },
    observables: { correspondence: .6, chirality: -.3, closure: .2 },
    ...(committal ? { domain_transition: 'emergence-settlement' as const, closure_history: { source: 'selected-committal' as const, points: [
      { proposition_id: 'older', depth: -.6, cadence: -.3, depth_direction: 'negative' as const, cadence_direction: 'negative' as const },
      { proposition_id: id, depth: .2, cadence: .5, depth_direction: 'positive' as const, cadence_direction: 'positive' as const }
    ], current: { proposition_id: id, depth: .2, cadence: .5, depth_direction: 'positive' as const, cadence_direction: 'positive' as const } } } : { confluence: id === 'c' }),
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
  const [step, setStep] = useState(0);
  const path = ['origin', 'origin', 'a', 'c', 'commit', 'commit'];
  const participants: GraphParticipant[] = [
    { id: 'nova', callsign: 'Nova', current_proposition_id: path[step], is_active: step > 0 && step < 5, activity_expires_at: null },
    { id: 'vela', callsign: 'Vela', current_proposition_id: 'b', is_active: false, activity_expires_at: null },
    { id: 'luna', callsign: 'Luna', current_proposition_id: 'origin', is_active: false, activity_expires_at: null },
  ];
  return <Box h="100dvh" style={{ display: 'flex', flexDirection: 'column' }}>
    <Group p="md" justify="space-between"><Box><Title order={3}>The living substrate</Title><Text size="xs" c="dimmed">Synthetic visual review · drag to explore both Domains</Text></Box>
      <Group><Button size="xs" variant="light" onClick={() => setStep(current => (current + 1) % path.length)}>Advance synthetic wake</Button>
      <Button size="xs" variant="light" onClick={toggleColorScheme}>Toggle light / dark</Button></Group></Group>
    <Box className="substrate-view__graph"><PropositionGraph propositions={propositions} participants={participants} /></Box>
  </Box>;
}
createRoot(document.getElementById('root')!).render(<Provider store={store}><MantineProvider defaultColorScheme="dark"><Preview /></MantineProvider></Provider>);

