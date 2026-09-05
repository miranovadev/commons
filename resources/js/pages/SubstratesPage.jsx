import { useEffect, useState } from 'react';
import {
  Alert,
  Badge,
  Box,
  Button,
  Card,
  Container,
  Group,
  Modal,
  NumberInput,
  Radio,
  SimpleGrid,
  Skeleton,
  Stack,
  Text,
  Textarea,
  TextInput,
  ThemeIcon,
  Title,
} from '@mantine/core';
import { useForm } from '@mantine/form';
import { useDisclosure } from '@mantine/hooks';
import { IconAlertCircle, IconArrowUpRight, IconLock, IconPlus, IconTopologyStar3, IconUsers, IconWorld } from '@tabler/icons-react';
import { Link } from 'react-router-dom';
import { api, csrf, messageFrom } from '../api';

export function SubstratesPage({ user }) {
  const [substrates, setSubstrates] = useState(null);
  const [error, setError] = useState('');
  const [opened, { open, close }] = useDisclosure(false);

  async function load() {
    try {
      const response = await api.get('/substrates');
      setSubstrates(response.data.data);
    } catch (requestError) {
      setError(messageFrom(requestError, 'Substrates could not be loaded.'));
    }
  }

  useEffect(() => { load(); }, [user]);

  return (
    <Container size="lg">
      <Group justify="space-between" align="flex-end" mb="xl">
        <Box>
          <Text size="sm" fw={600} c="indigo">LEDGER SPACES</Text>
          <Title order={1}>Substrates</Title>
          <Text c="dimmed" mt={6}>Explore public structure or return to a substrate where you participate.</Text>
        </Box>
        {user?.has_system_access && <Button color="blue" leftSection={<IconPlus size={17} />} onClick={open}>New substrate</Button>}
      </Group>

      {error && <Alert color="red" mb="lg" icon={<IconAlertCircle size={18} />}>{error}</Alert>}

      {substrates === null ? (
        <SimpleGrid cols={{ base: 1, sm: 2 }}><Skeleton h={210} radius="lg" /><Skeleton h={210} radius="lg" /></SimpleGrid>
      ) : substrates.length ? (
        <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="lg">
          {substrates.map((substrate) => <SubstrateCard key={substrate.id} substrate={substrate} />)}
        </SimpleGrid>
      ) : (
        <Card withBorder p={50} ta="center">
          <ThemeIcon size={52} radius="xl" color="indigo" variant="light" mx="auto"><IconTopologyStar3 size={26} /></ThemeIcon>
          <Title order={3} mt="md">No substrates are visible yet</Title>
          <Text c="dimmed" mt="xs">{user?.has_system_access ? 'Create the first space and give its Origin somewhere to land.' : 'Public substrates will appear here when they are created.'}</Text>
          {user?.has_system_access && <Button color="blue" variant="light" mt="lg" leftSection={<IconPlus size={17} />} onClick={open}>Create substrate</Button>}
        </Card>
      )}

      <CreateSubstrateModal opened={opened} onClose={close} onCreated={(substrate) => { setSubstrates((current) => [...(current || []), substrate].sort((a, b) => a.title.localeCompare(b.title))); close(); }} />
    </Container>
  );
}

function SubstrateCard({ substrate }) {
  const VisibilityIcon = substrate.visibility === 'public' ? IconWorld : IconLock;
  return (
    <Card component={Link} to={`/substrates/${substrate.slug}`} withBorder padding="xl" className="substrate-card">
      <Group justify="space-between" align="flex-start">
        <ThemeIcon size={42} radius="md" color="indigo" variant="light"><IconTopologyStar3 size={22} /></ThemeIcon>
        <IconArrowUpRight size={19} className="card-arrow" />
      </Group>
      <Title order={3} mt="xl">{substrate.title}</Title>
      <Text c="dimmed" size="sm" lineClamp={2} mih={42} mt={7}>{substrate.description || 'A shared cognitive substrate.'}</Text>
      <Group mt="xl" gap="xs">
        <Badge color={substrate.visibility === 'public' ? 'blue' : 'gray'} variant="light" leftSection={<VisibilityIcon size={12} />}>{substrate.visibility}</Badge>
        <Badge color="gray" variant="light" leftSection={<IconUsers size={12} />}>{substrate.participants_count ?? 0} participants</Badge>
      </Group>
    </Card>
  );
}

function CreateSubstrateModal({ opened, onClose, onCreated }) {
  const form = useForm({
    initialValues: { title: '', slug: '', description: '', visibility: 'public', maximum_participants: 100 },
    validate: { title: (v) => v.trim() ? null : 'A title is required.', slug: (v) => /^[a-z0-9-]+$/.test(v) ? null : 'Use lowercase letters, numbers and hyphens.' },
  });

  function setTitle(event) {
    const title = event.currentTarget.value;
    form.setFieldValue('title', title);
    if (!form.isDirty('slug')) form.setFieldValue('slug', title.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''));
  }

  async function submit(values) {
    form.clearErrors();
    try {
      await csrf();
      const response = await api.post('/substrates', {
        title: values.title,
        slug: values.slug,
        description: values.description || null,
        visibility: values.visibility,
        configuration: { maximum_participants: values.maximum_participants },
      });
      form.reset();
      onCreated(response.data.data);
    } catch (error) {
      form.setFieldError('title', messageFrom(error, 'The substrate could not be created.'));
    }
  }

  return (
    <Modal opened={opened} onClose={onClose} title="Create substrate" centered>
      <Box component="form" onSubmit={form.onSubmit(submit)}>
        <Stack>
          <TextInput label="Title" value={form.values.title} onChange={setTitle} error={form.errors.title} />
          <TextInput label="Slug" {...form.getInputProps('slug')} />
          <Textarea label="Description" autosize minRows={3} {...form.getInputProps('description')} />
          <Radio.Group label="Visibility" {...form.getInputProps('visibility')}><Group mt="xs"><Radio value="public" label="Public" /><Radio value="private" label="Private" /></Group></Radio.Group>
          <NumberInput label="Maximum participants" min={1} {...form.getInputProps('maximum_participants')} />
          <Group justify="flex-end"><Button variant="default" onClick={onClose}>Cancel</Button><Button type="submit" color="green" loading={form.submitting}>Save substrate</Button></Group>
        </Stack>
      </Box>
    </Modal>
  );
}
