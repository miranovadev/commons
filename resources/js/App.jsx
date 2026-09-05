import { useEffect, useState } from 'react';
import {
  ActionIcon,
  AppShell,
  Avatar,
  Box,
  Burger,
  Button,
  Center,
  Group,
  Loader,
  MantineProvider,
  Menu,
  Text,
  Title,
  useMantineColorScheme,
} from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { IconChevronDown, IconLogin2, IconLogout2, IconMoon, IconSun } from '@tabler/icons-react';
import { BrowserRouter, Link, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import { api, csrf } from './api';
import { CommonsLogo } from './components/CommonsLogo';
import { theme } from './theme';
import { BootstrapPage } from './pages/BootstrapPage';
import { LoginPage } from './pages/LoginPage';
import { ParticipantPage } from './pages/ParticipantPage';
import { SubstratePage } from './pages/SubstratePage';
import { SubstratesPage } from './pages/SubstratesPage';

export function App() {
  return (
    <MantineProvider theme={theme} defaultColorScheme="auto">
      <BrowserRouter><CommonsApp /></BrowserRouter>
    </MantineProvider>
  );
}

function CommonsApp() {
  const [opened, { toggle, close }] = useDisclosure(false);
  const [bootState, setBootState] = useState('loading');
  const [user, setUser] = useState(null);
  const location = useLocation();
  const navigate = useNavigate();

  useEffect(() => {
    let active = true;
    (async () => {
      try {
        await api.get('/bootstrap');
        if (!active) return;
        setBootState('ready');
        try {
          const response = await api.get('/session');
          if (active) setUser(response.data.data);
        } catch (error) {
          if (error.response?.status !== 401) throw error;
        }
      } catch (error) {
        if (!active) return;
        if (error.response?.status === 428) setBootState('required');
        else setBootState('error');
      }
    })();
    return () => { active = false; };
  }, []);

  useEffect(close, [location.pathname]);

  async function logout() {
    await csrf();
    await api.delete('/session');
    setUser(null);
    navigate('/');
  }

  if (bootState === 'loading') {
    return <Center mih="100vh"><Loader color="indigo" /></Center>;
  }

  if (bootState === 'error') {
    return <Center mih="100vh"><Text c="red">The Commons could not be reached.</Text></Center>;
  }

  if (bootState === 'required') {
    return <Routes><Route path="*" element={<BootstrapPage onComplete={(created) => { setUser(created); setBootState('ready'); navigate('/'); }} />} /></Routes>;
  }

  return (
    <AppShell
      header={{ height: 68 }}
      navbar={{ width: 260, breakpoint: 'sm', collapsed: { mobile: !opened, desktop: true } }}
      padding={{ base: 'md', sm: 'xl' }}
    >
      <AppShell.Header className="app-header">
        <Group h="100%" px={{ base: 'md', sm: 'xl' }} justify="space-between">
          <Group gap="sm">
            <Burger opened={opened} onClick={toggle} hiddenFrom="sm" size="sm" />
            <Group component={Link} to="/" gap="sm" className="brand-link">
              <CommonsLogo size={40} />
              <Box>
                <Title order={3} lh={1}>The Commons</Title>
                <Text size="xs" c="dimmed" mt={3}>Shared cognitive substrate</Text>
              </Box>
            </Group>
          </Group>
          <Group gap="xs">
            <ColorSchemeButton />
            {user ? (
              <Menu position="bottom-end" width={210}>
                <Menu.Target>
                  <Button variant="subtle" color="gray" px="xs" rightSection={<IconChevronDown size={14} />}>
                    <Group gap="xs"><Avatar size={28} color="indigo" name={user.name} /><Text size="sm" fw={500}>{user.name}</Text></Group>
                  </Button>
                </Menu.Target>
                <Menu.Dropdown>
                  <Menu.Label>{user.email}</Menu.Label>
                  <Menu.Item color="red" leftSection={<IconLogout2 size={16} />} onClick={logout}>Sign out</Menu.Item>
                </Menu.Dropdown>
              </Menu>
            ) : (
              <Button component={Link} to="/login" variant="default" leftSection={<IconLogin2 size={16} />}>Sign in</Button>
            )}
          </Group>
        </Group>
      </AppShell.Header>
      <AppShell.Navbar p="md">
        <Button component={Link} to="/" variant="subtle" justify="flex-start">Substrates</Button>
      </AppShell.Navbar>
      <AppShell.Main>
        <Routes>
          <Route path="/" element={<SubstratesPage user={user} />} />
          <Route path="/login" element={user ? <Navigate to="/" replace /> : <LoginPage onLogin={setUser} />} />
          <Route path="/substrates/:slug" element={<SubstratePage user={user} />} />
          <Route path="/substrates/:slug/participants/:participantId" element={user ? <ParticipantPage /> : <Navigate to="/login" replace />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AppShell.Main>
    </AppShell>
  );
}

function ColorSchemeButton() {
  const { colorScheme, toggleColorScheme } = useMantineColorScheme();
  return (
    <ActionIcon variant="subtle" color="gray" size="lg" onClick={toggleColorScheme} aria-label="Toggle colour scheme">
      {colorScheme === 'dark' ? <IconSun size={19} /> : <IconMoon size={19} />}
    </ActionIcon>
  );
}
