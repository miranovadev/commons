import { useState } from 'react';
import { useContextHorizonRealtime } from '../realtime/useRealtime';
import { Alert, Badge, Box, Button, Group, LoadingOverlay, NativeSelect, Title } from '@mantine/core';
import { IconAlertCircle, IconArrowRight, IconUserHexagon, IconUsers } from '@tabler/icons-react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { messageFrom } from '../errors';
import { PropositionGraph } from '../components/PropositionGraph';
import { useGetPropositionsQuery, useGetObservedPropositionsQuery, useGetSubstrateQuery } from '../services/commonsApi';
import type { User } from '../types';
import { useGraphParticipants } from '../realtime/useGraphParticipants';

export function SubstratePage({ user }: { user?: User | null }) {
  const { slug: routeSlug } = useParams();
  const slug = routeSlug ?? '';
  const navigate = useNavigate();
  const { data: substrate, isLoading, error: substrateError } = useGetSubstrateQuery(slug);
  const [view, setView] = useState<'active' | 'exhausted' | 'history'>('active');
  const { data: activePropositions = [], error: propositionsError } = useGetPropositionsQuery(slug);
  const { currentData: observedPropositions = [], error: historyError } = useGetObservedPropositionsQuery(
    { slug, view: view === 'active' ? 'exhausted' : view }, { skip: view === 'active' });
  const propositions = view === 'active' ? activePropositions : observedPropositions;
  useContextHorizonRealtime(slug, Boolean(substrate) && !substrateError);
  const { participants, error: participantsError } = useGraphParticipants(slug, Boolean(substrate) && !substrateError, propositions);
  const error = substrateError || propositionsError || (view !== 'active' && historyError);

  return (
    <Box className="substrate-view">
      {error && <Alert m="md" color="red" icon={<IconAlertCircle size={18} />}>{messageFrom(error, 'This substrate could not be opened.')}</Alert>}
      {participantsError && <Alert m="xs" color="gray">Participant positions could not be refreshed.</Alert>}
      <Box pos="relative" className="substrate-view__body">
        <LoadingOverlay visible={isLoading} overlayProps={{ blur: 1 }} />
        {substrate && (
          <>
            <Box className="substrate-view__header">
              <Group justify="space-between" gap="xs" wrap="wrap">
                <Group gap="sm" wrap="nowrap" miw={0} style={{ flex: 1 }}>
                  <Title order={3} lineClamp={1} title={substrate.title}>{substrate.title}</Title>
                  <Badge color="gray" variant="light" leftSection={<IconUsers size={12} />} style={{ flexShrink: 0 }}
                    aria-label={`${substrate.participants_count ?? 0} Participants`}>{substrate.participants_count ?? 0}</Badge>
                </Group>
                <NativeSelect size="xs" aria-label="Graph visibility" value={view}
                  onChange={event => setView(event.currentTarget.value as typeof view)}
                  data={[{ value: 'active', label: 'Active structure' }, { value: 'exhausted', label: 'Include exhausted alternatives' }, { value: 'history', label: 'Full history' }]} />
                {user ? (
                  <Button size="xs" variant="light" leftSection={<IconUserHexagon size={16} />} rightSection={<IconArrowRight size={14} />}
                    onClick={() => navigate(`/substrates/${slug}/participants`)}>Participants</Button>
                ) : <Button size="xs" component={Link} to="/login" variant="light">Sign in to participate</Button>}
              </Group>
            </Box>
            <Box className="substrate-view__graph"><PropositionGraph key={slug} propositions={propositions} substrateSlug={slug} participants={participants} /></Box>
          </>
        )}
      </Box>
    </Box>
  );
}
