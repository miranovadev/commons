import { useState } from 'react';
import { Alert, Button, Card, Container, Group, Loader, Stack, Text, Title } from '@mantine/core';
import { useParams } from 'react-router-dom';
import { SubstratePresentationFields } from '../components/SubstratePresentationFields';
import { useGetSubstrateQuery, useUpdateSubstrateMutation } from '../services/commonsApi';
import { presentationFor } from '../substratePresentation';
import { messageFrom } from '../errors';
import type { Substrate } from '../types';

export function SubstrateSettingsPage() {
  const { slug = '' } = useParams();
  const { currentData: substrate, isLoading, error } = useGetSubstrateQuery(slug);
  if (isLoading || (!substrate && !error)) return <Loader />;
  if (error) return <Alert color="red">{messageFrom(error, 'The Substrate could not be loaded.')}</Alert>;
  if (!substrate?.can_update) return <Alert color="red">You cannot configure this Substrate.</Alert>;
  return <SettingsForm key={substrate.id} substrate={substrate} />;
}

function SettingsForm({ substrate }: { substrate: Substrate }) {
  const [presentation, setPresentation] = useState(() => presentationFor(substrate));
  const [update, { isLoading, error }] = useUpdateSubstrateMutation();
  const [saved, setSaved] = useState(false);
  return <Container size="sm">
    <Title order={1}>Substrate settings</Title>
    <Text c="dimmed" mb="lg">{substrate.title}</Text>
    <Card withBorder component="form" onSubmit={async event => {
      event.preventDefault();
      if (isLoading) return;
      setSaved(false);
      try {
        const result = await update({ slug: substrate.slug, configuration: { presentation } }).unwrap();
        setPresentation(presentationFor(result));
        setSaved(true);
      } catch { /* The mutation error is displayed below; keep unsaved inputs. */ }
    }}>
      <Stack>
        <SubstratePresentationFields value={presentation} onChange={value => { setPresentation(value); setSaved(false); }} />
        {error && <Alert color="red">{messageFrom(error, 'The settings could not be saved.')}</Alert>}
        {saved && <Text role="status" c="dimmed">Settings saved.</Text>}
        <Group justify="flex-end"><Button type="submit" loading={isLoading}>Save settings</Button></Group>
      </Stack>
    </Card>
  </Container>;
}
