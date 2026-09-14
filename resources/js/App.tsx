import { useEffect } from 'react';
import { ActionIcon, AppShell, Avatar, Box, Burger, Button, Center, Group, Loader, Menu, NavLink, Stack, Text, useMantineColorScheme } from '@mantine/core';
import { useDisclosure, useMediaQuery } from '@mantine/hooks';
import { IconAdjustments, IconBinaryTree2, IconChevronDown, IconLogin2, IconLogout2, IconMoon, IconPlugConnected, IconSettings, IconSun, IconTopologyStar3, IconUserHexagon } from '@tabler/icons-react';
import { BrowserRouter, Link, Navigate, Route, Routes, matchPath, useLocation, useNavigate } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import '../css/ui-polish.css';
import '../css/workspace.css';
import { CommonsLogo } from './components/CommonsLogo';
import { commonsApi, useGetSessionQuery, useGetSubstrateQuery, useLogoutMutation } from './services/commonsApi';
import { systemBootstrapped } from './systemSlice';
import { HTTP_PRECONDITION_REQUIRED } from './http';
import { SubstrateTheme } from './SubstrateTheme';
import { presentationFor } from './substratePresentation';
import { SubstrateSettingsPage } from './pages/SubstrateSettingsPage';
import type { RootState } from './store';
import { BootstrapPage } from './pages/BootstrapPage';
import { ConnectionsPage } from './pages/ConnectionsPage';
import { LoginPage } from './pages/LoginPage';
import { ParticipantPage } from './pages/ParticipantPage';
import { ParticipantsPage } from './pages/ParticipantsPage';
import { SubstratePage } from './pages/SubstratePage';
import { SubstratesPage } from './pages/SubstratesPage';

export function App() {
  return <BrowserRouter><ThemedApp /></BrowserRouter>;
}

function ThemedApp() {
  const location = useLocation();
  const slug = matchPath('/substrates/:slug/*', location.pathname)?.params.slug;
  // currentData avoids briefly applying the previous Substrate's theme on navigation.
  const { currentData } = useGetSubstrateQuery(slug ?? '', { skip: !slug });
  return <SubstrateTheme presentation={presentationFor(slug ? currentData : undefined)}><CommonsApp /></SubstrateTheme>;
}

function CommonsApp() {
  const [opened, { toggle, close }] = useDisclosure(false);
  const mobile = useMediaQuery('(max-width: 47.99em)');
  const dispatch = useDispatch();
  const bootstrapRequired = useSelector((state: RootState) => state.system.bootstrapRequired);
  const { data: user, isLoading, isError, error } = useGetSessionQuery(undefined);
  const [logoutRequest] = useLogoutMutation();
  const location = useLocation();
  const navigate = useNavigate();
  const substrateMatch = matchPath({ path: '/substrates/:slug/*', end: false }, location.pathname)
    || matchPath('/substrates/:slug', location.pathname);
  const substrateSlug = substrateMatch?.params?.slug;
  const { data: selectedSubstrate } = useGetSubstrateQuery(substrateSlug ?? '', { skip: !substrateSlug });

  useEffect(close, [location.pathname]);

  async function logout() {
    await logoutRequest(undefined).unwrap();
    dispatch(commonsApi.util.resetApiState());
    navigate('/');
  }

  if (isLoading) return <Center mih="100vh"><Loader /></Center>;
  if (isError && (!error || !('status' in error) || (error.status !== 401 && error.status !== HTTP_PRECONDITION_REQUIRED))) return <Center mih="100vh"><Text c="red">The Commons could not be reached.</Text></Center>;
  if (bootstrapRequired) return <Routes><Route path="*" element={<BootstrapPage onComplete={() => { dispatch(systemBootstrapped()); navigate('/'); }} />} /></Routes>;

  const participantDetailActive = location.pathname.includes('/participants/') && !location.pathname.endsWith('/participants');
  const participantsActive = Boolean(substrateSlug) && (location.pathname === `/substrates/${substrateSlug}/participants` || participantDetailActive);
  const connectionsActive = location.pathname === '/connections';
  const substrateRoot = substrateSlug && location.pathname === `/substrates/${substrateSlug}`;
  const flushMain = participantDetailActive || substrateRoot;
  const substratesListActive = location.pathname === '/';

  return (
    <AppShell
      header={{ height: 58 }}
      navbar={{ width: 214, breakpoint: 'sm', collapsed: { mobile: !opened, desktop: false } }}
      padding={flushMain ? 0 : { base: 'md', sm: 'xl' }}
    >
      <AppShell.Header className="app-header">
        <Group h="100%" px="md" justify="space-between">
          <Group gap="sm">
            <Burger opened={opened} onClick={toggle} hiddenFrom="sm" size="sm" />
            <Link to="/" className="brand-link">
              <Group gap="xs">
                <CommonsLogo size={30} />
                <Box>
                  <Text fw={750} size="sm" lh={1.1}>The Commons</Text>
                  <Text size="10px" c="dimmed" visibleFrom="sm" mt={2}>A Shared Cognitive Substrate</Text>
                </Box>
              </Group>
            </Link>
          </Group>
          <Group gap="xs">
            <ColorSchemeButton />
            {user ? (
              <Menu position="bottom-end" width={210}>
                <Menu.Target><Button variant="subtle" color="gray" px={mobile ? 0 : 'xs'} rightSection={mobile ? undefined : <IconChevronDown size={14} />}><Group gap="xs"><Avatar size={26} color="primary" name={user.name} />{!mobile && <Text size="sm" fw={500}>{user.name}</Text>}</Group></Button></Menu.Target>
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
            active={substratesListActive || Boolean(substrateSlug)}
            className={substrateSlug && !substratesListActive ? 'substrates-nav-context' : undefined}
          />
          {substrateSlug && (
            <Box className="substrate-nav-section">
              <Text className="substrate-nav-section__title" size="xs" fw={700} lineClamp={2}>{selectedSubstrate?.title || substrateSlug}</Text>
              <Stack gap={2} mt={4}>
                <NavLink component={Link} to={`/substrates/${substrateSlug}`} label="Graph" leftSection={<IconBinaryTree2 size={16} />} active={Boolean(substrateRoot)} />
                <NavLink component={Link} to={`/substrates/${substrateSlug}/participants`} label="Participants" leftSection={<IconUserHexagon size={16} />} active={participantsActive} />
                <NavLink component={Link} to={`/substrates/${substrateSlug}/settings`} label="Settings" leftSection={<IconAdjustments size={16} />} active={location.pathname.endsWith('/settings')} disabled={!selectedSubstrate?.can_update} />
              </Stack>
            </Box>
          )}
          {user && <NavLink component={Link} to="/connections" label="Connections" leftSection={<IconPlugConnected size={18} />} active={connectionsActive} />}
        </Stack>
      </AppShell.Navbar>
      <AppShell.Main className="app-main">
        <Routes>
          <Route path="/" element={<SubstratesPage user={user} />} />
          <Route path="/login" element={user ? <Navigate to="/" replace /> : <LoginPage />} />
          <Route path="/connections" element={user ? <ConnectionsPage /> : <Navigate to="/login" replace />} />
          <Route path="/substrates/:slug" element={<SubstratePage user={user} />} />
          <Route path="/substrates/:slug/settings" element={user ? <SubstrateSettingsPage /> : <Navigate to="/login" replace />} />
          <Route path="/substrates/:slug/participants" element={user ? <ParticipantsPage user={user} /> : <Navigate to="/login" replace />} />
          <Route path="/substrates/:slug/participants/:participantId" element={user ? <ParticipantPage /> : <Navigate to="/login" replace />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AppShell.Main>
    </AppShell>
  );
}

function ColorSchemeButton() {
  const { colorScheme, setColorScheme } = useMantineColorScheme();
  const dark = colorScheme === 'dark';
  return <ActionIcon variant="subtle" color="gray" aria-label="Toggle color scheme" onClick={() => setColorScheme(dark ? 'light' : 'dark')}>{dark ? <IconSun size={18} /> : <IconMoon size={18} />}</ActionIcon>;
}
