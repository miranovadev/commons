import { useContextHorizonRealtime } from '../realtime/useRealtime';
import { Alert, Badge, Box, Breadcrumbs, Button, Group, LoadingOverlay, Stack, Text, Title } from '@mantine/core';
import { IconAlertCircle, IconArrowRight, IconTopologyStar3, IconUserHexagon, IconUsers } from '@tabler/icons-react';
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
              <Stack gap="sm">
                <Breadcrumbs>
                  <Text component={Link} to="/" size="sm" c="dimmed">Substrates</Text>
                  <Text size="sm">{substrate.title}</Text>
                  <Text size="sm" c="dimmed">Graph</Text>
                </Breadcrumbs>
                <Group justify="space-between" align="flex-start" wrap="wrap">
                  <Box>
                    <Group gap="xs"><Badge color="indigo" variant="light" leftSection={<IconTopologyStar3 size={12} />}>SUBSTRATE</Badge><Badge color="gray" variant="light" leftSection={<IconUsers size={12} />}>{substrate.participants_count ?? 0}</Badge></Group>
                    <Title order={2} mt="xs">{substrate.title}</Title>
                    <Text c="dimmed" size="sm" mt={4} maw={720}>{substrate.description || 'A shared cognitive substrate.'}</Text>
                  </Box>
                  {user ? (
                    <Button leftSection={<IconUserHexagon size={18} />} rightSection={<IconArrowRight size={16} />} onClick={() => navigate(`/substrates/${slug}/participants`)}>Participants</Button>
                  ) : (
                    <Button component={Link} to="/login" variant="light">Sign in to participate</Button>
                  )}
                </Group>
              </Stack>
            </Box>
            <Box className="substrate-view__graph"><PropositionGraph propositions={propositions} substrateSlug={slug} participants={participants} /></Box>
          </>
        )}
      </Box>
    </Box>
  );
}
