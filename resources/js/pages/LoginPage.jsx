import { Alert, Box, Button, Container, Paper, PasswordInput, Stack, Text, TextInput, Title } from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconAlertCircle, IconLogin2 } from '@tabler/icons-react';
import { useNavigate } from 'react-router-dom';
import { api, csrf, messageFrom } from '../api';

export function LoginPage({ onLogin }) {
  const navigate = useNavigate();
  const form = useForm({ initialValues: { email: '', password: '', remember: true } });
  const error = form.errors.form;

  async function submit(values) {
    form.setFieldError('form', null);
    try {
      await csrf();
      const response = await api.post('/session', values);
      onLogin(response.data.data);
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
