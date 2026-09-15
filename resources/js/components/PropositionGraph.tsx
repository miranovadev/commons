import { PropositionInspector } from './PropositionInspector';
import { useGetPropositionQuery } from '../services/commonsApi';
import { Box, Checkbox, Group, NativeSelect, Paper, Stack, Text, ThemeIcon, Title } from '@mantine/core';
import { useLocalStorage } from '@mantine/hooks';
import { IconArrowsMove, IconSparkles } from '@tabler/icons-react';
import { memo, useCallback, useMemo, useRef, useState } from 'react';
import '../../css/substrate-graph.css';
import { PropositionCard } from './PropositionCard';
import type { GraphParticipant, Identifier, Proposition } from '../types';
import { participantsByPosition } from '../graphParticipants';
import { GraphParticipants } from './GraphParticipants';
import { GraphTensionPalette } from '../graphPresentation';
import { causalAncestors, graphParents, lineageVisibility } from '../graphTopology';
import { graphOverlayDefinitions, graphOverlayOptions, isGraphOverlayMode, resolveGraphOverlays } from '../graphOverlays';
import type { GraphOverlay } from '../graphOverlays';

interface GraphProps {
  propositions: Proposition[];
  substrateSlug?: string;
  initialFocusId?: Identifier | null;
  perspectiveLimit?: number;
  participantMode?: boolean;
  participants?: GraphParticipant[];
}

interface DragState {
  x: number;
  y: number;
  offsetX: number;
  offsetY: number;
}

const NODE_WIDTH = 220;
const NODE_HEIGHT = 160;
const COLUMN_GAP = 54;
const ROW_GAP = 96;
const CANVAS_PADDING = 115;

const NO_PARTICIPANTS: GraphParticipant[] = [];

export function PropositionGraph({ propositions, substrateSlug, initialFocusId, perspectiveLimit, participantMode = false, participants = NO_PARTICIPANTS }: GraphProps) {
  const viewportRef = useRef<HTMLDivElement>(null);
  const dragState = useRef<DragState | null>(null);
  const [dragging, setDragging] = useState(false);
  const [offset, setOffset] = useState({ x: 0, y: 0 });
  const [selectedId, setSelectedId] = useState(initialFocusId || null);
  const [isolate, setIsolate] = useState(false);
  const [inspectedId, setInspectedId] = useState<Identifier | null>(null);
  const [storedOverlay, setOverlay] = useLocalStorage<string>({ key: 'commons.graph.overlay', defaultValue: 'none' });
  const overlayMode = isGraphOverlayMode(storedOverlay) ? storedOverlay : 'none';
  const overlays = useMemo(() => resolveGraphOverlays(propositions, overlayMode), [propositions, overlayMode]);
  const layout = useMemo(() => buildGraphLayout(propositions), [propositions]);
  const parents = useMemo(() => graphParents(propositions), [propositions]);
  const ancestors = useMemo(() => causalAncestors(parents, selectedId), [parents, selectedId]);
  const hasSelection = selectedId !== null && parents.has(selectedId);
  const visible = useMemo(() => lineageVisibility(parents, selectedId, ancestors, isolate), [parents, selectedId, ancestors, isolate]);
  const positions = useMemo(() => participantsByPosition(participants), [participants]);
  const select = useCallback((id: Identifier) => { setSelectedId(id); setInspectedId(id); }, []);

  if (!layout.origin) {
    return (
      <Paper withBorder p={50} ta="center">
        <ThemeIcon color="indigo" variant="light" size={50} radius="xl" mx="auto"><IconSparkles size={24} /></ThemeIcon>
        <Title order={3} mt="md">The Origin has not been written</Title>
        <Text c="dimmed" mt="xs">This substrate is balanced, empty, and carrying its full available Charge.</Text>
      </Paper>
    );
  }

  function beginDrag(event: React.PointerEvent<HTMLDivElement>) {
    if (event.button !== 0 || (event.target as Element).closest('.proposition-card, .graph-participants')) return;
    const viewport = viewportRef.current;
    if (!viewport) return;
    dragState.current = {
      x: event.clientX,
      y: event.clientY,
      offsetX: offset.x,
      offsetY: offset.y,
    };
    viewport.setPointerCapture(event.pointerId);
    setDragging(true);
  }

  function moveDrag(event: React.PointerEvent<HTMLDivElement>) {
    const start = dragState.current;
    if (!start) return;
    setOffset({
      x: start.offsetX + (event.clientX - start.x),
      y: start.offsetY + (event.clientY - start.y),
    });
  }

  function endDrag(event: React.PointerEvent<HTMLDivElement>) {
    const viewport = viewportRef.current;
    if (viewport?.hasPointerCapture?.(event.pointerId)) viewport.releasePointerCapture(event.pointerId);
    dragState.current = null;
    setDragging(false);
  }

  return (
    <GraphTensionPalette value={overlayMode === 'tension' ? 'analytical' : 'default'}>
    <Box className="substrate-graph-shell" data-has-selection={hasSelection || undefined}>
      {inspectedId && <GraphInspector key={`${substrateSlug}/${inspectedId}`} slug={substrateSlug} propositionId={inspectedId}
        fallback={propositions.find(item => item.id === inspectedId)} onClose={() => setInspectedId(null)} />}
      <Stack className="substrate-graph-controls" gap={5}>
        <NativeSelect label="Visualisation" size="xs" value={overlayMode} data={graphOverlayOptions}
          onChange={event => setOverlay(event.currentTarget.value)} />
        <Text size="10px" c="dimmed">{graphOverlayDefinitions[overlayMode].legend}</Text>
      <Checkbox size="xs" label="Isolate Selected Lineage" checked={isolate} disabled={!hasSelection} onChange={event => setIsolate(event.currentTarget.checked)} />
      <Group className="substrate-graph-hint" gap={6} wrap="nowrap">
        <IconArrowsMove size={14} />
        <Text size="xs" c="dimmed">Drag the surface to explore</Text>
        {participantMode && <Text size="xs" c="indigo" fw={650}>· {propositions.length} / {perspectiveLimit || propositions.length} visible</Text>}
      </Group>
      </Stack>

      <Box
        ref={viewportRef}
        className={`substrate-graph-viewport ${dragging ? 'is-dragging' : ''}`}
        onPointerDown={beginDrag}
        onPointerMove={moveDrag}
        onPointerUp={endDrag}
        onPointerCancel={endDrag}
      >
        <Box
          className="substrate-graph-canvas"
          style={{
            width: layout.width,
            height: layout.height,
            transform: `translate3d(${offset.x}px, ${offset.y}px, 0)`,
          }}
        >
          <svg className="substrate-graph-edges" width={layout.width} height={layout.height} aria-hidden="true">
            {layout.edges.filter(edge => !visible || (visible.has(edge.from) && visible.has(edge.to))).map((edge) => (
              <path key={`${edge.from}-${edge.to}`} d={edge.path}
                data-lineage={(ancestors.has(edge.from) && (ancestors.has(edge.to) || edge.to === selectedId)) || undefined} />
            ))}
          </svg>

          {layout.nodes.filter(node => !visible || visible.has(node.proposition.id)).map(({ proposition, x, y }) => (
            <GraphNode key={proposition.id} proposition={proposition} x={x} y={y}
              lineage={selectedId === proposition.id ? 'selected' : ancestors.has(proposition.id) ? 'ancestor' : 'unrelated'}
              overlay={overlays.get(proposition.id)} participants={positions.get(proposition.id) || NO_PARTICIPANTS} onSelect={select} />
          ))}
        </Box>
      </Box>
    </Box>
    </GraphTensionPalette>
  );
}

const GraphNode = memo(function GraphNode({ proposition, x, y, lineage, overlay, participants, onSelect }: {
  proposition: Proposition; x: number; y: number; lineage: string; overlay?: GraphOverlay;
  participants: GraphParticipant[]; onSelect: (id: Identifier) => void;
}) {
  return <Box className="substrate-graph-node" data-lineage={lineage}
    style={{ left: x, top: y, width: NODE_WIDTH, '--graph-node-height': `${NODE_HEIGHT}px` }}>
    <GraphParticipants participants={participants} />
    <PropositionCard proposition={proposition} mini graph overlay={overlay} selected={lineage === 'selected'} onClick={() => onSelect(proposition.id)} />
    {proposition.is_origin && <Text className="substrate-graph-origin-label" size="10px" c="indigo" fw={700}>ORIGIN</Text>}
  </Box>;
}, (previous, next) => previous.proposition === next.proposition && previous.x === next.x && previous.y === next.y
  && previous.lineage === next.lineage && previous.onSelect === next.onSelect
  && previous.overlay?.color === next.overlay?.color && previous.overlay?.intensity === next.overlay?.intensity
  && previous.overlay?.label === next.overlay?.label
  && previous.participants.length === next.participants.length
  && previous.participants.every((participant, index) => participant.id === next.participants[index].id
    && participant.callsign === next.participants[index].callsign && participant.is_active === next.participants[index].is_active));

function buildGraphLayout(propositions: Proposition[]) {
  const origin = propositions.find((proposition) => proposition.is_origin) || propositions[0];
  if (!origin) return { origin: null, nodes: [], edges: [], width: 0, height: 0 };

  const byId = new Map(propositions.map((proposition) => [proposition.id, proposition]));
  const childrenById = new Map<Identifier, Set<Identifier>>(
    propositions.map((proposition) => [proposition.id, new Set<Identifier>()]),
  );

  propositions.forEach((proposition) => {
    (proposition.parent_ids || []).forEach((parentId) => {
      if (childrenById.has(parentId)) childrenById.get(parentId)!.add(proposition.id);
    });
    (proposition.children || []).forEach((child) => {
      if (byId.has(child.id)) childrenById.get(proposition.id)?.add(child.id);
    });
  });

  const levels: Identifier[][] = [];
  const visited = new Set<Identifier>();
  let frontier = [origin.id];

  while (frontier.length) {
    const current = frontier.filter((id) => byId.has(id) && !visited.has(id));
    if (!current.length) break;
    levels.push(current);
    current.forEach((id) => visited.add(id));
    frontier = [...new Set(current.flatMap((id) => [...(childrenById.get(id) || [])]))];
  }

  const unvisited = propositions.filter((proposition) => !visited.has(proposition.id)).map((proposition) => proposition.id);
  if (unvisited.length) levels.push(unvisited);

  const maxNodesInLevel = Math.max(...levels.map((level) => level.length), 1);
  const contentWidth = maxNodesInLevel * NODE_WIDTH + Math.max(maxNodesInLevel - 1, 0) * COLUMN_GAP;
  const width = Math.max(960, contentWidth + CANVAS_PADDING * 2);
  const height = Math.max(560, levels.length * NODE_HEIGHT + Math.max(levels.length - 1, 0) * ROW_GAP + CANVAS_PADDING * 2);
  const positionById = new Map<Identifier, { x: number; y: number }>();
  const nodes: Array<{ proposition: Proposition; x: number; y: number }> = [];

  levels.forEach((level, levelIndex) => {
    const rowWidth = level.length * NODE_WIDTH + Math.max(level.length - 1, 0) * COLUMN_GAP;
    const startX = (width - rowWidth) / 2;
    const y = CANVAS_PADDING + levelIndex * (NODE_HEIGHT + ROW_GAP);

    level.forEach((id, index) => {
      const x = startX + index * (NODE_WIDTH + COLUMN_GAP);
      positionById.set(id, { x, y });
      nodes.push({ proposition: byId.get(id)!, x, y });
    });
  });

  const edges: Array<{ from: Identifier; to: Identifier; path: string }> = [];
  childrenById.forEach((childIds, parentId) => {
    const parent = positionById.get(parentId);
    if (!parent) return;
    childIds.forEach((childId) => {
      const child = positionById.get(childId);
      if (!child) return;
      const x1 = parent.x + NODE_WIDTH / 2;
      const y1 = parent.y + NODE_HEIGHT;
      const x2 = child.x + NODE_WIDTH / 2;
      const y2 = child.y;
      const bend = (y1 + y2) / 2;
      edges.push({ from: parentId, to: childId, path: `M ${x1} ${y1} C ${x1} ${bend}, ${x2} ${bend}, ${x2} ${y2}` });
    });
  });

  return { origin, nodes, edges, width, height };
}

/** Reuses the workspace drawer; fetched detail also lives in RTK Query. */
function GraphInspector({ slug, propositionId, fallback, onClose }: {
  slug?: string; propositionId: Identifier; fallback?: Proposition; onClose: () => void;
}) {
  const { data, error } = useGetPropositionQuery({ slug: slug || '', propositionId }, { skip: !slug, refetchOnMountOrArgChange: true });
  const proposition = data || fallback;
  return <PropositionInspector selection={proposition ? { kind: 'graph', proposition } : null} onClose={onClose} error={error} />;
}
