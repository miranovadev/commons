import { Alert, Box, Button, Container, Paper, PasswordInput, Stack, Text, TextInput, Title } from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconAlertCircle, IconLogin2 } from '@tabler/icons-react';
import { useNavigate } from 'react-router-dom';
import { messageFrom } from '../errors';
import { useLoginMutation } from '../services/commonsApi';
import type { LoginInput } from '../types';

interface LoginFormValues extends LoginInput {
  remember: boolean;
}

export function LoginPage() {
  const navigate = useNavigate();
  const [login] = useLoginMutation();
  const form = useForm<LoginFormValues>({ initialValues: { email: '', password: '', remember: true } });
  const error = form.errors.form;

  async function submit(values: LoginFormValues) {
    form.setFieldError('form', null);
    try {
      await login(values).unwrap();
      navigate('/');
    } catch (requestError) {
      form.setFieldError('form', messageFrom(requestError, 'Sign in failed.'));
    }
  }

  return (
    <Container size="xs" py={60}>
      <Title order={1}>Welcome back</Title>
      <Text c="dimmed" mt={6} mb="xl">Sign in to participate and access private substrates.</Text>
      <Paper withBorder p="xl">
        <Box component="form" onSubmit={form.onSubmit(submit)}>
          <Stack>
            {error && <Alert color="red" icon={<IconAlertCircle size={18} />}>{error}</Alert>}
            <TextInput label="Email" type="email" autoComplete="email" {...form.getInputProps('email')} />
            <PasswordInput label="Password" autoComplete="current-password" {...form.getInputProps('password')} />
            <Button type="submit" color="blue" loading={form.submitting} leftSection={<IconLogin2 size={17} />}>Sign in</Button>
          </Stack>
        </Box>
      </Paper>
    </Container>
  );
}
