import { useEffect } from 'react';
import { ActionIcon, AppShell, Avatar, Box, Burger, Button, Center, Group, Loader, MantineProvider, Menu, NavLink, Stack, Text, Title, useMantineColorScheme } from '@mantine/core';
import { useDisclosure, useMediaQuery } from '@mantine/hooks';
import { IconActivity, IconAdjustments, IconChevronDown, IconCirclesRelation, IconLogin2, IconLogout2, IconMoon, IconNetwork, IconSun, IconUsers, IconUserHexagon } from '@tabler/icons-react';
import { BrowserRouter, Link, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import { CommonsLogo } from './components/CommonsLogo';
import { commonsApi, useGetSessionQuery, useLogoutMutation } from './services/commonsApi';
import { systemBootstrapped } from './systemSlice';
import { HTTP_PRECONDITION_REQUIRED } from './http';
import { theme } from './theme';
import { BootstrapPage } from './pages/BootstrapPage';
import { LoginPage } from './pages/LoginPage';
import { ParticipantPage } from './pages/ParticipantPage';
import { SubstratePage } from './pages/SubstratePage';
import { SubstratesPage } from './pages/SubstratesPage';

export function App() {
  return <MantineProvider theme={theme} defaultColorScheme="auto"><BrowserRouter><CommonsApp /></BrowserRouter></MantineProvider>;
}

function CommonsApp() {
  const [opened, { toggle, close }] = useDisclosure(false);
  const mobile = useMediaQuery('(max-width: 47.99em)');
  const dispatch = useDispatch();
  const bootstrapRequired = useSelector((state) => state.system.bootstrapRequired);
  const { data: user, isLoading, isError, error } = useGetSessionQuery();
  const [logoutRequest] = useLogoutMutation();
  const location = useLocation();
  const navigate = useNavigate();

  useEffect(close, [location.pathname]);

  async function logout() {
    await logoutRequest().unwrap();
    dispatch(commonsApi.util.resetApiState());
    navigate('/');
  }

  if (isLoading) return <Center mih="100vh"><Loader color="indigo" /></Center>;
  if (isError && error?.status !== 401 && error?.status !== HTTP_PRECONDITION_REQUIRED) return <Center mih="100vh"><Text c="red">The Commons could not be reached.</Text></Center>;
  if (bootstrapRequired) return <Routes><Route path="*" element={<BootstrapPage onComplete={() => { dispatch(systemBootstrapped()); navigate('/'); }} />} /></Routes>;

  const participantActive = location.pathname.includes('/participants/');

  return (
    <AppShell
      header={{ height: 58 }}
      navbar={{ width: 214, breakpoint: 'sm', collapsed: { mobile: !opened, desktop: false } }}
      padding={participantActive ? 0 : { base: 'md', sm: 'xl' }}
    >
      <AppShell.Header className="app-header">
        <Group h="100%" px="md" justify="space-between">
          <Group gap="sm">
            <Burger opened={opened} onClick={toggle} hiddenFrom="sm" size="sm" />
            <Group component={Link} to="/" gap="sm" className="brand-link" visibleFrom="sm">
              <CommonsLogo size={30} /><Title order={4}>The Commons</Title>
            </Group>
          </Group>
          <Group gap="xs">
            <ColorSchemeButton />
            {user ? (
              <Menu position="bottom-end" width={210}>
                <Menu.Target><Button variant="subtle" color="gray" px={mobile ? 0 : 'xs'} rightSection={mobile ? undefined : <IconChevronDown size={14} />}><Group gap="xs"><Avatar size={26} color="indigo" name={user.name} />{!mobile && <Text size="sm" fw={500}>{user.name}</Text>}</Group></Button></Menu.Target>
                <Menu.Dropdown><Menu.Label>{user.email}</Menu.Label><Menu.Item color="red" leftSection={<IconLogout2 size={16} />} onClick={logout}>Sign out</Menu.Item></Menu.Dropdown>
              </Menu>
            ) : <Button component={Link} to="/login" variant="default" leftSection={<IconLogin2 size={16} />}>Sign in</Button>}
          </Group>
        </Group>
      </AppShell.Header>
      <AppShell.Navbar className="commons-navbar" p="sm">
        <Stack h="100%" gap="xs">
          <Group gap="sm" p="xs"><CommonsLogo size={34} /><Box><Text fw={750} size="sm">The Commons</Text><Text size="10px" c="dimmed">Shared cognitive substrate</Text></Box></Group>
          <Box mt="sm">
            <NavLink component={Link} to="/" label="Substrates" leftSection={<IconCirclesRelation size={18} />} active={location.pathname === '/'} />
            {participantActive && <NavLink label="Participant" leftSection={<IconUserHexagon size={18} />} active />}
            <NavLink label="Graph" leftSection={<IconNetwork size={18} />} disabled />
            <NavLink label="Activity" leftSection={<IconActivity size={18} />} disabled />
            <NavLink label="Participants" leftSection={<IconUsers size={18} />} disabled />
            <NavLink label="Settings" leftSection={<IconAdjustments size={18} />} disabled />
          </Box>
          <Box mt="auto" p="xs">{user && <Group gap="xs"><Avatar size={28} color="gray" name={user.name} /><Text size="xs" fw={600} truncate>{user.name}</Text></Group>}</Box>
        </Stack>
      </AppShell.Navbar>
      <AppShell.Main>
        <Routes>
          <Route path="/" element={<SubstratesPage user={user} />} />
          <Route path="/login" element={user ? <Navigate to="/" replace /> : <LoginPage />} />
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
  return <ActionIcon variant="subtle" color="gray" size="lg" onClick={toggleColorScheme} aria-label="Toggle colour scheme">{colorScheme === 'dark' ? <IconSun size={19} /> : <IconMoon size={19} />}</ActionIcon>;
}
