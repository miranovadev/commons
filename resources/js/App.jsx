import { useEffect } from 'react';
import { ActionIcon, AppShell, Avatar, Box, Burger, Button, Center, Group, Loader, MantineProvider, Menu, NavLink, Stack, Text, useMantineColorScheme } from '@mantine/core';
import { useDisclosure, useMediaQuery } from '@mantine/hooks';
import { IconAdjustments, IconBinaryTree2, IconChevronDown, IconChevronRight, IconLogin2, IconLogout2, IconMoon, IconPlugConnected, IconSettings, IconSun, IconTopologyStar3, IconUserHexagon } from '@tabler/icons-react';
import { BrowserRouter, Link, Navigate, Route, Routes, matchPath, useLocation, useNavigate } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import { CommonsLogo } from './components/CommonsLogo';
import { commonsApi, useGetSessionQuery, useGetSubstrateQuery, useLogoutMutation } from './services/commonsApi';
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
  const substrateMatch = matchPath({ path: '/substrates/:slug/*', end: false }, location.pathname)
    || matchPath('/substrates/:slug', location.pathname);
  const substrateSlug = substrateMatch?.params?.slug;
  const { data: selectedSubstrate } = useGetSubstrateQuery(substrateSlug, { skip: !substrateSlug });

  useEffect(close, [location.pathname]);

  async function logout() {
    await logoutRequest().unwrap();
    dispatch(commonsApi.util.resetApiState());
    navigate('/');
  }

  if (isLoading) return <Center mih="100vh"><Loader /></Center>;
  if (isError && error?.status !== 401 && error?.status !== HTTP_PRECONDITION_REQUIRED) return <Center mih="100vh"><Text c="red">The Commons could not be reached.</Text></Center>;
  if (bootstrapRequired) return <Routes><Route path="*" element={<BootstrapPage onComplete={() => { dispatch(systemBootstrapped()); navigate('/'); }} />} /></Routes>;

  const participantActive = location.pathname.includes('/participants/');
  const substrateRoot = substrateSlug && location.pathname === `/substrates/${substrateSlug}`;

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
            <Group component={Link} to="/" gap="xs" className="brand-link">
              <CommonsLogo size={30} />
              <Box>
                <Text fw={750} size="sm" lh={1.1}>The Commons</Text>
                <Text size="10px" c="dimmed" visibleFrom="sm" mt={2}>A Shared Cognitive Substrate</Text>
              </Box>
            </Group>
          </Group>
          <Group gap="xs">
            <ColorSchemeButton />
            {user ? (
              <Menu position="bottom-end" width={210}>
                <Menu.Target><Button variant="subtle" color="gray" px={mobile ? 0 : 'xs'} rightSection={mobile ? undefined : <IconChevronDown size={14} />}><Group gap="xs"><Avatar size={26} color="indigo" name={user.name} />{!mobile && <Text size="sm" fw={500}>{user.name}</Text>}</Group></Button></Menu.Target>
                <Menu.Dropdown>
                  <Menu.Label>{user.email}</Menu.Label>
                  <Menu.Item leftSection={<IconSettings size={16} />}>Account Settings</Menu.Item>
                  <Menu.Divider />
                  <Menu.Item color="red" leftSection={<IconLogout2 size={16} />} onClick={logout}>Sign out</Menu.Item>
                </Menu.Dropdown>
              </Menu>
            ) : <Button component={Link} to="/login" leftSection={<IconLogin2 size={16} />}>Sign in</Button>}
          </Group>
        </Group>
      </AppShell.Header>
      <AppShell.Navbar className="commons-navbar" p="sm">
        <Stack h="100%" gap={4} pt="xs">
          <NavLink
            component={Link}
            to="/"
            label="Substrates"
            leftSection={<IconTopologyStar3 size={18} />}
            rightSection={substrateSlug ? <IconChevronDown size={15} /> : <IconChevronRight size={15} />}
            active={location.pathname === '/' || Boolean(substrateSlug)}
          />
          {substrateSlug && (
            <Box className="substrate-nav-section">
              <Text className="substrate-nav-section__title" size="xs" fw={700} lineClamp={2}>{selectedSubstrate?.title || substrateSlug}</Text>
              <Stack gap={2} mt={4}>
                <NavLink component={Link} to={`/substrates/${substrateSlug}`} label="Graph" leftSection={<IconBinaryTree2 size={16} />} active={Boolean(substrateRoot)} />
                <NavLink label="Participants" leftSection={<IconUserHexagon size={16} />} active={participantActive} disabled={!participantActive} />
                <NavLink label="Settings" leftSection={<IconAdjustments size={16} />} disabled />
              </Stack>
            </Box>
          )}
          <NavLink label="Connections" leftSection={<IconPlugConnected size={18} />} disabled />
        </Stack>
      </AppShell.Navbar>
      <AppShell.Main className="app-main">
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
