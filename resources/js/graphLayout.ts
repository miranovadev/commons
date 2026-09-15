import { graphParents } from './graphTopology';
import type { Identifier, Proposition } from './types';

export const graphDensities = {
  detailed: { nodeWidth: 220, nodeHeight: 160, columnGap: 54, rowGap: 96, padding: 115, minWidth: 960, minHeight: 560 },
  topology: { nodeWidth: 84, nodeHeight: 40, columnGap: 32, rowGap: 52, padding: 64, minWidth: 360, minHeight: 320 },
} as const;
export type GraphDensity = keyof typeof graphDensities;
export function isGraphDensity(value: unknown): value is GraphDensity {
  return typeof value === 'string' && Object.hasOwn(graphDensities, value);
}

/** Semantic structure is independent of presentation density and analytical lens. */
export function buildGraphStructure(propositions: Proposition[], parents = graphParents(propositions)) {
  const origin = propositions.find(p => p.is_origin) || propositions[0] || null;
  const byId = new Map(propositions.map(p => [p.id, p]));
  const children = new Map<Identifier, Identifier[]>();
  const edges: Array<{ from: Identifier; to: Identifier }> = [];
  for (const [child, ids] of parents) for (const parent of ids) {
    if (!children.has(parent)) children.set(parent, []);
    children.get(parent)!.push(child);
    edges.push({ from: parent, to: child });
  }
  const levels: Identifier[][] = [];
  const visited = new Set<Identifier>();
  let frontier = origin ? [origin.id] : [];
  while (frontier.length) {
    const current = frontier.filter(id => byId.has(id) && !visited.has(id));
    if (!current.length) break;
    levels.push(current);
    for (const id of current) visited.add(id);
    frontier = [...new Set(current.flatMap(id => children.get(id) || []))];
  }
  const unvisited = propositions.filter(p => !visited.has(p.id)).map(p => p.id);
  if (unvisited.length) levels.push(unvisited);
  return { origin, byId, levels, edges };
}

/** Geometry alone changes with density; a future semantic zoom can choose the same presets. */
export function buildGraphLayout(structure: ReturnType<typeof buildGraphStructure>, density: GraphDensity = 'detailed') {
  const { nodeWidth, nodeHeight, columnGap, rowGap, padding, minWidth, minHeight } = graphDensities[density];
  const { origin, byId, levels } = structure;
  const maxColumns = Math.max(...levels.map(level => level.length), 1);
  const width = Math.max(minWidth, maxColumns * nodeWidth + (maxColumns - 1) * columnGap + padding * 2);
  const height = Math.max(minHeight, levels.length * nodeHeight + Math.max(0, levels.length - 1) * rowGap + padding * 2);
  const nodes = levels.flatMap((level, levelIndex) => {
    const rowWidth = level.length * nodeWidth + Math.max(0, level.length - 1) * columnGap;
    return level.map((id, index) => ({ proposition: byId.get(id)!,
      x: (width - rowWidth) / 2 + index * (nodeWidth + columnGap), y: padding + levelIndex * (nodeHeight + rowGap) }));
  });
  const positions = new Map(nodes.map(node => [node.proposition.id, node]));
  const edges = structure.edges.map(edge => {
    const parent = positions.get(edge.from)!;
    const child = positions.get(edge.to)!;
    const x1 = parent.x + nodeWidth / 2;
    const y1 = parent.y + nodeHeight;
    const x2 = child.x + nodeWidth / 2;
    const y2 = child.y;
    const bend = (y1 + y2) / 2;
    return { ...edge, path: `M ${x1} ${y1} C ${x1} ${bend}, ${x2} ${bend}, ${x2} ${y2}` };
  });
  return { origin, nodes, edges, width, height };
}
