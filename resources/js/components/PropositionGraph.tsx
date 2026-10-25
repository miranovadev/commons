import { GraphInspectorShell, InspectorContent } from './PropositionInspector';
import { useGetPropositionQuery } from '../services/commonsApi';
import { Alert, Box, Group, Paper, Text, ThemeIcon, Title } from '@mantine/core';
import { useLocalStorage } from '@mantine/hooks';
import { IconArrowsMove, IconSparkles } from '@tabler/icons-react';
import { memo, useCallback, useEffect, useLayoutEffect, useMemo, useReducer, useRef, useState } from 'react';
import '../../css/substrate-graph.css';
import { PropositionCard } from './PropositionCard';
import type { GraphParticipant, Identifier, Proposition } from '../types';
import { participantsByPosition } from '../graphParticipants';
import { GraphParticipants } from './GraphParticipants';
import { GraphTensionPalette } from '../graphPresentation';
import { GraphWorkspaceRail } from './GraphWorkspaceRail';
import { buildGraphLayout, buildGraphStructure, graphDensities, isGraphDensity, type GraphDensity } from '../graphLayout';
import { graphRailReducer } from '../graphWorkspace';
import { causalAncestors, graphParents, lineageVisibility, graphNavigation, rememberLineagePath, nextInLineage } from '../graphTopology';
import { isGraphOverlayMode, resolveGraphOverlays } from '../graphOverlays';
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

const NO_PARTICIPANTS: GraphParticipant[] = [];

export function PropositionGraph({ propositions, substrateSlug, initialFocusId, perspectiveLimit, participantMode = false, participants = NO_PARTICIPANTS }: GraphProps) {
  const viewportRef = useRef<HTMLDivElement>(null);
  const dragState = useRef<DragState | null>(null);
  const [dragging, setDragging] = useState(false);
  const [animating, setAnimating] = useState(false);
  const [offset, setOffset] = useState({ x: 0, y: 0 });
  const [selectedId, setSelectedId] = useState(initialFocusId || null);
  useEffect(() => { if (initialFocusId) setSelectedId(initialFocusId); }, [initialFocusId]);
  const [isolate, setIsolate] = useState(false);
  const [railState, dispatchRail] = useReducer(graphRailReducer, 'open');
  const [forwardPath, setForwardPath] = useState<Map<Identifier, Identifier>>(new Map());
  const [storedDensity, setDensity] = useLocalStorage<string>({ key: 'commons.graph.density', defaultValue: 'detailed' });
  const density = isGraphDensity(storedDensity) ? storedDensity : 'detailed';
  const { nodeWidth, nodeHeight } = graphDensities[density];
  const [storedOverlay, setOverlay] = useLocalStorage<string>({ key: 'commons.graph.overlay', defaultValue: 'none' });
  const overlayMode = isGraphOverlayMode(storedOverlay) ? storedOverlay : 'none';
  const overlays = useMemo(() => resolveGraphOverlays(propositions, overlayMode), [propositions, overlayMode]);
  const parents = useMemo(() => graphParents(propositions), [propositions]);
  const structure = useMemo(() => buildGraphStructure(propositions, parents), [propositions, parents]);
  const layout = useMemo(() => buildGraphLayout(structure, density), [structure, density]);
  const ancestors = useMemo(() => causalAncestors(parents, selectedId), [parents, selectedId]);
  const hasSelection = selectedId !== null && parents.has(selectedId);
  const visible = useMemo(() => lineageVisibility(parents, selectedId, ancestors, isolate), [parents, selectedId, ancestors, isolate]);
  const selectedProposition = useMemo(() => propositions.find(p => p.id === selectedId), [propositions, selectedId]);
  const positions = useMemo(() => participantsByPosition(participants), [participants]);
  const navigation = useMemo(() => graphNavigation(propositions, parents), [propositions, parents]);
  const remembered = useMemo(() => selectedId ? rememberLineagePath(navigation.previous, selectedId, forwardPath) : forwardPath,
    [navigation, selectedId, forwardPath]);
  const previousId = selectedId ? navigation.previous.get(selectedId) : undefined;
  const nextId = nextInLineage(navigation.children, selectedId, remembered);
  const select = useCallback((id: Identifier) => {
    setSelectedId(id);
    setForwardPath(current => rememberLineagePath(navigation.previous, id, current));
  }, [navigation]);
  const navigateLineage = (id: Identifier) => {
    setForwardPath(remembered);
    setSelectedId(id);
    // Arrow navigation preserves Workspace state and the active lens.
  };
  const deselect = () => {
    viewportRef.current?.querySelector<HTMLButtonElement>('.proposition-card[aria-pressed="true"]')?.focus({ preventScroll: true });
    setSelectedId(null);
    setForwardPath(new Map());
  };
  const showOrigin = () => {
    const viewport = viewportRef.current;
    const node = layout.nodes.find(item => item.proposition.id === layout.origin?.id);
    if (!viewport || !node) return;
    const inspector = viewport.parentElement?.querySelector<HTMLElement>('.graph-inspector');
    const mobile = window.matchMedia('(max-width: 48em)').matches;
    const width = inspector && !mobile ? viewport.clientWidth - inspector.offsetWidth - 24 : viewport.clientWidth;
    const height = inspector && mobile ? viewport.clientHeight - inspector.offsetHeight - 12 : viewport.clientHeight;
    setAnimating(true);
    setOffset({ x: (width - nodeWidth) / 2 - node.x, y: Math.max(45, (height - nodeHeight) / 2) - node.y });
  };
  const lastReveal = useRef('');
  useLayoutEffect(() => {
    const viewport = viewportRef.current;
    // Deselect only clears selection; Origin is an explicit camera action.
    if (!selectedId && lastReveal.current !== '') return;
    const focus = selectedId || layout.origin?.id;
    const key = `${focus}/${density}`;
    const node = layout.nodes.find(item => item.proposition.id === focus);
    if (!viewport || !node || !viewport.clientWidth || lastReveal.current === key) return;
    const initial = lastReveal.current === '';
    lastReveal.current = key;
    // Only a new selection/density reveals a target; live refreshes preserve pan.
    const inspector = viewport.parentElement?.querySelector<HTMLElement>('.graph-inspector');
    const mobile = window.matchMedia('(max-width: 48em)').matches;
    const availableWidth = inspector && !mobile ? viewport.clientWidth - inspector.offsetWidth - 24 : viewport.clientWidth;
    const availableHeight = inspector && mobile ? viewport.clientHeight - inspector.offsetHeight - 12 : viewport.clientHeight;
    setAnimating(false);
    setOffset(current => {
      const x = initial || node.x + current.x < 20 || node.x + current.x + nodeWidth > availableWidth - 20
        ? (availableWidth - nodeWidth) / 2 - node.x : current.x;
      const y = node.y + current.y < 45 || node.y + current.y + nodeHeight > availableHeight - 20
        ? Math.max(45, (availableHeight - nodeHeight) / 2) - node.y : current.y;
      return x === current.x && y === current.y ? current : { x, y };
    });
  }, [selectedId, layout, density, nodeWidth, nodeHeight]);

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
    if (event.button !== 0 || (event.target as Element).closest('.proposition-card, .graph-participants, .graph-inspector')) return;
    const viewport = viewportRef.current;
    if (!viewport) return;
    setAnimating(false);
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
    <Box className="substrate-graph-shell" data-has-selection={hasSelection || undefined} data-rail-state={railState} data-density={density}>

      <Box className="substrate-graph-stage">
      <Box
        ref={viewportRef}
        className={`substrate-graph-viewport ${dragging ? 'is-dragging' : ''}`}
        onPointerDown={beginDrag}
        onPointerMove={moveDrag}
        onPointerUp={endDrag}
        onPointerCancel={endDrag}
      >
        <Group className="substrate-graph-hint" gap={6} wrap="nowrap">
          <IconArrowsMove size={14} /><Text size="xs">Drag the surface to explore</Text>
          {participantMode && <Text size="xs">· {propositions.length} / {perspectiveLimit || propositions.length} visible</Text>}
        </Group>
        <Box
          className="substrate-graph-canvas"
          data-animating={animating || undefined}
          onTransitionEnd={() => setAnimating(false)}
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
            <GraphNode key={proposition.id} proposition={proposition} x={x} y={y} density={density}
              lineage={selectedId === proposition.id ? 'selected' : ancestors.has(proposition.id) ? 'ancestor' : 'unrelated'}
              overlay={overlays.get(proposition.id)} participants={positions.get(proposition.id) || NO_PARTICIPANTS} onSelect={select} />
          ))}
        </Box>
      </Box>
        {hasSelection && <GraphInspectorShell onClose={deselect}
          onPrevious={previousId ? () => navigateLineage(previousId) : undefined}
          onNext={nextId ? () => navigateLineage(nextId) : undefined}>
          {selectedId && substrateSlug
            ? <GraphInspector slug={substrateSlug} propositionId={selectedId} fallback={selectedProposition} />
            : <InspectorContent graph selection={selectedProposition ? { kind: 'graph', proposition: selectedProposition } : null} />}
        </GraphInspectorShell>}
      </Box>
      <GraphWorkspaceRail state={railState} onState={dispatchRail} overlayMode={overlayMode} onOverlay={setOverlay}
        density={density} onDensity={setDensity} hasSelection={hasSelection} isolate={isolate} onIsolate={setIsolate}
        onShowOrigin={showOrigin} />
    </Box>
    </GraphTensionPalette>
  );
}

const GraphNode = memo(function GraphNode({ proposition, x, y, lineage, overlay, participants, onSelect, density }: {
  proposition: Proposition; x: number; y: number; lineage: string; overlay?: GraphOverlay; density: GraphDensity;
  participants: GraphParticipant[]; onSelect: (id: Identifier) => void;
}) {
  const { nodeWidth, nodeHeight } = graphDensities[density];
  return <Box className="substrate-graph-node" data-lineage={lineage}
    style={{ left: x, top: y, width: nodeWidth, '--graph-node-height': `${nodeHeight}px` }}>
    <GraphParticipants participants={participants} compact={density === 'topology'} />
    <PropositionCard proposition={proposition} mini graph density={density} overlay={overlay} selected={lineage === 'selected'} onClick={() => onSelect(proposition.id)} />
    {proposition.is_origin && <Text className="substrate-graph-origin-label" size="10px" c="indigo" fw={700}>ORIGIN</Text>}
  </Box>;
}, (previous, next) => previous.proposition === next.proposition && previous.x === next.x && previous.y === next.y
  && previous.density === next.density && previous.lineage === next.lineage && previous.onSelect === next.onSelect
  && previous.overlay?.color === next.overlay?.color && previous.overlay?.intensity === next.overlay?.intensity
  && previous.overlay?.label === next.overlay?.label
  && previous.participants.length === next.participants.length
  && previous.participants.every((participant, index) => participant.id === next.participants[index].id
    && participant.callsign === next.participants[index].callsign && participant.is_active === next.participants[index].is_active));

/** Fetch only selected detail; shared metric refresh also updates this query cache. */
function GraphInspector({ slug, propositionId, fallback }: {
  slug?: string; propositionId: Identifier; fallback?: Proposition;
}) {
  const { currentData: data, error, isFetching } = useGetPropositionQuery({ slug: slug || '', propositionId }, { skip: !slug, refetchOnMountOrArgChange: true });
  const proposition = data || fallback;
  return <Box aria-busy={isFetching}>
    {error && <Alert color="gray" m="md">Full details could not be refreshed. Showing available graph data.</Alert>}
    <InspectorContent graph substrateSlug={slug} selection={proposition ? { kind: 'graph', proposition } : null} />
  </Box>;
}

