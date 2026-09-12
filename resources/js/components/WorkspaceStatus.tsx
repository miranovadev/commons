import { Box, Button, Group, Modal, VisuallyHidden } from '@mantine/core';
import { useEffect, useState, type ElementType, type ReactNode } from 'react';

export interface WorkspaceNotice { id: string; title: string; color: string; icon: ElementType; content: ReactNode; urgent?: boolean; satisfied?: boolean; }
export function WorkspaceStatus({ notices, closeDetails = false }: { notices: WorkspaceNotice[]; closeDetails?: boolean }) {
  const [selectedId, setSelectedId] = useState<string | null>(null);
  useEffect(() => { if (closeDetails) setSelectedId(null); }, [closeDetails]);
  const selected = notices.find(notice => notice.id === selectedId);
  if (!notices.length) return null;
  return <Box component="aside" className="workspace-status" aria-label="Workspace status">
    {notices.filter(notice => notice.urgent).map(notice => <VisuallyHidden key={notice.id} role="alert">{notice.title}. Open the status notice for details.</VisuallyHidden>)}
    <Group className="workspace-status__compact" gap={6} wrap="nowrap" aria-label="Open status details">
      {notices.map(notice => { const Icon = notice.icon; return <Button key={notice.id} className="workspace-status__chip"
        size="compact-xs" variant="light" color={notice.color} data-satisfied={notice.satisfied}
        aria-label={`Open ${notice.title}`} onClick={() => setSelectedId(notice.id)} leftSection={<Icon size={16} />}>
        {notice.title}
      </Button>; })}
    </Group>
    <Modal opened={Boolean(selected) && !closeDetails} onClose={() => setSelectedId(null)} title={selected?.title} size="md" centered>
      {selected?.content}
    </Modal>
  </Box>;
}
