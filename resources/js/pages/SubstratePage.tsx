import { useContextHorizonRealtime } from '../realtime/useRealtime';
import { Alert, Badge, Box, Button, Group, LoadingOverlay, Title } from '@mantine/core';
import { IconAlertCircle, IconArrowRight, IconUserHexagon, IconUsers } from '@tabler/icons-react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { messageFrom } from '../errors';
import { PropositionGraph } from '../components/PropositionGraph';
import { useGetPropositionsQuery, useGetSubstrateQuery } from '../services/commonsApi';
import type { User } from '../types';
import { useGraphParticipants } from '../realtime/useGraphParticipants';

export function SubstratePage({ user }: { user?: User | null }) {
  const { slug: routeSlug } = useParams();
  const slug = routeSlug ?? '';
  const navigate = useNavigate();
  const { data: substrate, isLoading, error: substrateError } = useGetSubstrateQuery(slug);
  const { data: propositions = [], error: propositionsError } = useGetPropositionsQuery(slug);
  useContextHorizonRealtime(slug, Boolean(substrate) && !substrateError);
  const { participants, error: participantsError } = useGraphParticipants(slug, Boolean(substrate) && !substrateError, propositions);
  const error = substrateError || propositionsError;

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
