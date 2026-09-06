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
import { IconAlertCircle, IconArrowUpRight, IconHexagon3d, IconLock, IconPlus, IconTopologyStar3, IconUsers, IconWorld } from '@tabler/icons-react';
import { Link } from 'react-router-dom';
import { messageFrom } from '../errors';
import { useCreateSubstrateMutation, useGetSubstratesQuery } from '../services/commonsApi';

export function SubstratesPage({ user }) {
  const { data: substrates = [], isLoading, error } = useGetSubstratesQuery();
  const [opened, { open, close }] = useDisclosure(false);

  return (
    <Container size="lg">
      <Group justify="space-between" align="flex-end" mb="xl">
        <Box>
          <Text size="sm" fw={600} c="dimmed">LEDGER SPACES</Text>
          <Title order={1}>Substrates</Title>
          <Text c="dimmed" mt={6}>Explore public structure or return to a substrate where you participate.</Text>
        </Box>
        {user?.has_system_access && <Button color="gray" leftSection={<IconPlus size={17} />} onClick={open}>New substrate</Button>}
      </Group>

      {error && <Alert color="red" mb="lg" icon={<IconAlertCircle size={18} />}>{messageFrom(error, 'Substrates could not be loaded.')}</Alert>}

      {isLoading ? (
        <SimpleGrid cols={{ base: 1, sm: 2 }}><Skeleton h={210} radius="lg" /><Skeleton h={210} radius="lg" /></SimpleGrid>
      ) : substrates.length ? (
        <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="lg">
          {substrates.map((substrate) => <SubstrateCard key={substrate.id} substrate={substrate} />)}
        </SimpleGrid>
      ) : (
        <Card withBorder p={50} ta="center">
          <ThemeIcon size={52} radius="xl" color="gray" variant="light" mx="auto"><IconTopologyStar3 size={26} /></ThemeIcon>
          <Title order={3} mt="md">No substrates are visible yet</Title>
          <Text c="dimmed" mt="xs">{user?.has_system_access ? 'Create the first space and give its Origin somewhere to land.' : 'Public substrates will appear here when they are created.'}</Text>
          {user?.has_system_access && <Button color="gray" variant="light" mt="lg" leftSection={<IconPlus size={17} />} onClick={open}>Create substrate</Button>}
        </Card>
      )}

      <CreateSubstrateModal opened={opened} onClose={close} onCreated={close} />
    </Container>
  );
}

function SubstrateCard({ substrate }) {
  const VisibilityIcon = substrate.visibility === 'public' ? IconWorld : IconLock;
  return (
    <Card component={Link} to={`/substrates/${substrate.slug}`} withBorder padding="xl" className="substrate-card">
      <Group justify="space-between" align="flex-start" wrap="nowrap">
        <Group gap="md" align="flex-start" wrap="nowrap" miw={0}>
          <ThemeIcon size={42} radius="md" color="gray" variant="light" className="substrate-card__icon"><IconTopologyStar3 size={22} /></ThemeIcon>
          <Box miw={0}>
            <Title order={3}>{substrate.title}</Title>
            <Text c="dimmed" size="sm" lineClamp={2} mih={42} mt={7}>{substrate.description || 'A shared cognitive substrate.'}</Text>
          </Box>
        </Group>
        <IconArrowUpRight size={19} className="card-arrow" />
      </Group>

      <PotentialChargeGauge value={substrate.potential_charge} tension={substrate.tension} />

      <Group mt="lg" gap="xs">
        <Badge color="gray" variant="light" leftSection={<VisibilityIcon size={12} />}>{substrate.visibility}</Badge>
        <Badge color="gray" variant="light" leftSection={<IconUsers size={12} />}>{substrate.participants_count ?? 0} participants</Badge>
      </Group>
    </Card>
  );
}

function PotentialChargeGauge({ value, tension }) {
  const potential = typeof value === 'number' ? value : null;
  const globalTension = typeof tension === 'number' ? tension : null;
  const position = potential == null ? 50 : Math.max(0, Math.min(100, potential * 100));

  return (
    <Box className="potential-gauge" mt="lg">
      <Group justify="space-between" mb={6}>
        <Group gap={6}>
          <IconHexagon3d size={16} />
          <Text size="xs" fw={700}>Potential Charge</Text>
        </Group>
        <Text size="xs" c="dimmed">
          {potential == null ? 'Not exposed yet' : potential.toFixed(2)}
        </Text>
      </Group>
      <Box className="potential-gauge__track" aria-label={potential == null ? 'Potential Charge is not exposed by the substrate summary API' : `Potential Charge ${potential.toFixed(2)}`}>
        {potential != null && <span className="potential-gauge__marker" style={{ left: `${position}%` }} />}
      </Box>
      <Group justify="space-between" mt={4}>
        <Text size="10px" c="dimmed">Low potential</Text>
        <Text size="10px" c="dimmed">{globalTension == null ? 'Field measurement unavailable' : `Tension ${globalTension >= 0 ? '+' : ''}${globalTension.toFixed(2)}`}</Text>
        <Text size="10px" c="dimmed">High potential</Text>
      </Group>
    </Box>
  );
}

function CreateSubstrateModal({ opened, onClose, onCreated }) {
  const [createSubstrate] = useCreateSubstrateMutation();
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
      await createSubstrate({
        title: values.title,
        slug: values.slug,
        description: values.description || null,
        visibility: values.visibility,
        configuration: { maximum_participants: values.maximum_participants },
      }).unwrap();
      form.reset();
      onCreated();
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
