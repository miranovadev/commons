import { Box, Group, Paper, Text, ThemeIcon, Title } from '@mantine/core';
import { IconArrowsMove, IconSparkles } from '@tabler/icons-react';
import { useMemo, useRef, useState } from 'react';
import '../../css/substrate-graph.css';
import { PropositionCard } from './PropositionCard';
import type { Identifier, Proposition } from '../types';

interface GraphProps {
  propositions: Proposition[];
  initialFocusId?: Identifier | null;
  perspectiveLimit?: number;
  participantMode?: boolean;
}

interface DragState {
  x: number;
  y: number;
  offsetX: number;
  offsetY: number;
}

const NODE_WIDTH = 190;
const NODE_HEIGHT = 104;
const COLUMN_GAP = 54;
const ROW_GAP = 96;
const CANVAS_PADDING = 90;

export function PropositionGraph({ propositions, initialFocusId, perspectiveLimit, participantMode = false }: GraphProps) {
  const viewportRef = useRef<HTMLDivElement>(null);
  const dragState = useRef<DragState | null>(null);
  const [dragging, setDragging] = useState(false);
  const [offset, setOffset] = useState({ x: 0, y: 0 });
  const [selectedId, setSelectedId] = useState(initialFocusId || null);
  const layout = useMemo(() => buildGraphLayout(propositions), [propositions]);

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
    if (event.button !== 0 || (event.target as Element).closest('.proposition-card')) return;
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
    <Box className="substrate-graph-shell">
      <Group className="substrate-graph-hint" gap={6} wrap="nowrap">
        <IconArrowsMove size={14} />
        <Text size="xs" c="dimmed">Drag the surface to explore</Text>
        {participantMode && <Text size="xs" c="indigo" fw={650}>· {propositions.length} / {perspectiveLimit || propositions.length} visible</Text>}
      </Group>

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
            {layout.edges.map((edge) => (
              <path key={`${edge.from}-${edge.to}`} d={edge.path} />
            ))}
          </svg>

          {layout.nodes.map(({ proposition, x, y }) => (
            <Box
              key={proposition.id}
              className="substrate-graph-node"
              style={{ left: x, top: y, width: NODE_WIDTH }}
            >
              <PropositionCard
                proposition={proposition}
                mini
                selected={selectedId === proposition.id}
                onClick={() => setSelectedId(proposition.id)}
              />
              {proposition.is_origin && <Text className="substrate-graph-origin-label" size="10px" c="indigo" fw={700}>ORIGIN</Text>}
            </Box>
          ))}
        </Box>
      </Box>
    </Box>
  );
}

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
