import { ActionIcon, Box, Button, Group, Modal, Stack, Text, ThemeIcon, Tooltip, VisuallyHidden } from '@mantine/core';
import { IconChevronDown, IconLayoutSidebarRightCollapse, IconLayoutSidebarRightExpand } from '@tabler/icons-react';
import { useEffect, useState, type ElementType, type ReactNode } from 'react';

export interface WorkspaceNotice { id: string; title: string; color: string; icon: ElementType; content: ReactNode; urgent?: boolean; }
export function WorkspaceStatus({ notices, closeDetails = false }: { notices: WorkspaceNotice[]; closeDetails?: boolean }) {
  const [collapsed, setCollapsed] = useState(false);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  useEffect(() => { if (closeDetails) setSelectedId(null); }, [closeDetails]);
  const selected = notices.find(notice => notice.id === selectedId);
  if (!notices.length) return null;
  return <Box component="aside" className="workspace-status" data-collapsed={collapsed} aria-label="Workspace status">
    {notices.filter(notice => notice.urgent).map(notice => <VisuallyHidden key={notice.id} role="alert">{notice.title}. Open the status notice for details.</VisuallyHidden>)}
    <Group className="workspace-status__header" justify="space-between" wrap="nowrap">
      <Text size="xs" fw={650} c="dimmed" className="workspace-status__heading">Status · {notices.length}</Text>
      <Tooltip label={collapsed ? 'Expand status rail' : 'Collapse status rail'}><ActionIcon variant="subtle" color="gray" size={28}
        aria-label={collapsed ? 'Expand status rail' : 'Collapse status rail'} onClick={() => setCollapsed(value => !value)}>
        {collapsed ? <IconLayoutSidebarRightExpand size={18} /> : <IconLayoutSidebarRightCollapse size={18} />}
      </ActionIcon></Tooltip>
    </Group>
    <Stack className="workspace-status__cards" gap="sm">
      {notices.map(notice => { const Icon = notice.icon; return <Box component="details" open key={notice.id} className="workspace-notice"
        style={{ '--notice-color': `var(--mantine-color-${notice.color}-6)` }}>
        <summary><ThemeIcon size={24} variant="light" color={notice.color}><Icon size={15} /></ThemeIcon>
          <Text span size="xs" fw={650}>{notice.title}</Text><IconChevronDown size={14} className="workspace-notice__chevron" /></summary>
        <Box className="workspace-notice__content">{notice.content}</Box>
      </Box>; })}
    </Stack>
    <Group className="workspace-status__compact" gap={6} wrap="nowrap" aria-label="Open status details">
      {notices.map(notice => { const Icon = notice.icon; return <Tooltip key={notice.id} label={notice.title}>
        <Button className="workspace-status__chip" size="compact-xs" variant="light" color={notice.color}
          aria-label={`Open ${notice.title}`} onClick={() => setSelectedId(notice.id)} leftSection={<Icon size={16} />}>
          <span>{notice.title}</span>
        </Button>
      </Tooltip>; })}
    </Group>
    <Modal opened={Boolean(selected) && !closeDetails} onClose={() => setSelectedId(null)} title={selected?.title} size="md" centered>
      {selected?.content}
    </Modal>
  </Box>;
}
