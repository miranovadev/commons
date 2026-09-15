import { Box, NavLink, Stack, Text } from '@mantine/core';
import { IconAdjustments, IconBinaryTree2, IconChevronLeft, IconPlugConnected, IconTopologyStar3, IconUserHexagon } from '@tabler/icons-react';
import { Link, matchPath, useLocation } from 'react-router-dom';

/** URL-owned context survives direct entry, reloads and browser back/forward. */
export function MainNavigation({ hasUser, substrateTitle }: { hasUser: boolean; substrateTitle?: string }) {
  const { pathname } = useLocation();
  const slug = matchPath('/substrates/:slug/*', pathname)?.params.slug;
  if (slug) {
    const base = `/substrates/${encodeURIComponent(slug)}`;
    const participants = Boolean(matchPath(`${base}/participants/*`, pathname));
    return <Stack component="nav" aria-label="Substrate navigation" h="100%" gap={4} pt="xs">
      <Box className="substrate-navigation__back">
        <NavLink component={Link} to="/" label="Substrates" aria-label="Back to Substrates"
          leftSection={<IconChevronLeft size={18} />} />
      </Box>
      <Text className="substrate-navigation__title" size="xs" fw={650} lineClamp={2}>{substrateTitle || slug}</Text>
      <NavLink component={Link} to={base} label="Graph" leftSection={<IconBinaryTree2 size={18} />}
        active={Boolean(matchPath(base, pathname))} />
      <NavLink component={Link} to={`${base}/participants`} label="Participants" leftSection={<IconUserHexagon size={18} />}
        active={participants} />
      <NavLink label="Settings" leftSection={<IconAdjustments size={18} />} disabled />
    </Stack>;
  }
  return <Stack component="nav" aria-label="Main navigation" h="100%" gap={4} pt="xs">
    <NavLink component={Link} to="/" label="Substrates" leftSection={<IconTopologyStar3 size={18} />} active={pathname === '/'} />
    {hasUser && <NavLink component={Link} to="/connections" label="Connections" leftSection={<IconPlugConnected size={18} />} active={pathname === '/connections'} />}
  </Stack>;
}
