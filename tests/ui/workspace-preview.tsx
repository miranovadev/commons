import { createRoot } from 'react-dom/client';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { AppShell, Box, Group, MantineProvider, NavLink, Text } from '@mantine/core';
import { IconTopologyStar3, IconUserHexagon } from '@tabler/icons-react';
import { theme } from '../../resources/js/theme';
import { ParticipantPage } from '../../resources/js/pages/ParticipantPage';
import { CommonsLogo } from '../../resources/js/components/CommonsLogo';
import '@mantine/core/styles.css';
import '../../resources/css/app.css';
import '../../resources/css/ui-polish.css';
import '../../resources/css/workspace.css';
const mode = new URLSearchParams(location.search).get('theme') === 'light' ? 'light' : 'dark';
createRoot(document.getElementById('root')!).render(<MantineProvider theme={theme} forceColorScheme={mode}>
  <MemoryRouter initialEntries={['/substrates/testing/participants/claire']}>
    <AppShell header={{ height: 58 }} navbar={{ width: 214, breakpoint: 'sm', collapsed: { mobile: true } }} padding={0}>
      <AppShell.Header className="app-header"><Group h="100%" px="md" gap="xs"><CommonsLogo size={30} /><Text size="sm" fw={700}>The Commons</Text><Text size="xs" c="dimmed" ml="auto" data-last-action>Local synthetic preview</Text></Group></AppShell.Header>
      <AppShell.Navbar className="commons-navbar" p="sm"><NavLink label="Substrates" leftSection={<IconTopologyStar3 size={18} />} /><Box className="substrate-nav-section"><Text size="xs" p="xs">TESTING</Text><NavLink active label="Participants" leftSection={<IconUserHexagon size={18} />} /></Box></AppShell.Navbar>
      <AppShell.Main className="app-main"><Routes><Route path="/substrates/:slug/participants/:participantId" element={<ParticipantPage />} /></Routes></AppShell.Main>
    </AppShell>
  </MemoryRouter>
</MantineProvider>);
