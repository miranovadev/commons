import { Alert, Box, Button, Center, Container, Paper, PasswordInput, Stack, Text, TextInput, Title } from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconArrowRight, IconInfoCircle } from '@tabler/icons-react';
import { CommonsLogo } from '../components/CommonsLogo';
import { messageFrom } from '../errors';
import { useBootstrapMutation } from '../services/commonsApi';
import type { BootstrapInput } from '../types';

export function BootstrapPage({ onComplete }: { onComplete: () => void }) {
  const [bootstrap] = useBootstrapMutation();
  const form = useForm<BootstrapInput>({
    initialValues: { name: '', email: '', password: '', password_confirmation: '' },
    validate: {
      name: (value) => value.trim() ? null : 'Your name is required.',
      email: (value) => /^\S+@\S+$/.test(value) ? null : 'Enter a valid email address.',
      password: (value) => value.length >= 8 ? null : 'Use at least eight characters.',
      password_confirmation: (value, values) => value === values.password ? null : 'The passwords do not match.',
    },
  });

  async function submit(values: BootstrapInput) {
    form.clearErrors();
    try {
      await bootstrap(values).unwrap();
      onComplete();
    } catch (error) {
      form.setFieldError('email', messageFrom(error, 'The Commons could not be initialised.'));
    }
  }

  return (
    <Center mih="100vh" className="bootstrap-page">
      <Container size={460} w="100%" py="xl">
        <Stack align="center" gap="xs" mb="xl">
          <CommonsLogo size={76} />
          <Title order={1}>Begin The Commons</Title>
          <Text c="dimmed" ta="center">Create the first user and establish system stewardship.</Text>
        </Stack>
        <Paper withBorder shadow="sm" p={{ base: 'lg', sm: 'xl' }} radius="lg">
          <Alert mb="lg" color="indigo" variant="light" icon={<IconInfoCircle size={18} />}>
            This account receives system administration access. Additional participants remain bound by the same substrate rules.
          </Alert>
          <Box component="form" onSubmit={form.onSubmit(submit)}>
            <Stack>
              <TextInput label="Name" placeholder="Claire Mira Shaw" autoComplete="name" {...form.getInputProps('name')} />
              <TextInput label="Email" placeholder="you@example.com" autoComplete="email" {...form.getInputProps('email')} />
              <PasswordInput label="Password" autoComplete="new-password" {...form.getInputProps('password')} />
              <PasswordInput label="Confirm password" autoComplete="new-password" {...form.getInputProps('password_confirmation')} />
              <Button type="submit" color="green" loading={form.submitting} rightSection={<IconArrowRight size={17} />}>Initialise Commons</Button>
            </Stack>
          </Box>
        </Paper>
        <Text size="xs" c="dimmed" ta="center" mt="lg">Ideas may exist here when they can persist under constraint.</Text>
      </Container>
    </Center>
  );
}
