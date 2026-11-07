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
  // A producing Phase fixes the row within its seven-Phase excursion. The
  // hard edge of a Revision can connect peers, including historical unary births.
  const pending: Identifier[][] = origin ? [[origin.id]] : [];
  for (let row = 0; row < pending.length; row++) {
    const level: Identifier[] = [];
    for (const id of pending[row] || []) {
      const parent = byId.get(id);
      if (!parent || visited.has(id)) continue;
      visited.add(id);
      level.push(id);
      for (const childId of children.get(id) || []) {
        const child = byId.get(childId);
        if (!child || visited.has(childId)) continue;
        // A foreign operand records causality; the producing Gate fixes the excursion.
        if (child.structural_source_id && child.structural_source_id !== id
          && parents.get(childId)?.has(child.structural_source_id)) continue;
        const from = parent.is_origin ? 0 : parent.structural_position;
        const to = child.structural_position;
        const distance = from === undefined || to === undefined ? 1
          : from === 0 ? to : (to - from + 7) % 7;
        (pending[row + distance] ??= []).push(childId);
      }
    }
    levels.push(level);
  }
  while (levels.length && !levels.at(-1)!.length) levels.pop();
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
    if (parent.y === child.y) {
      const forward = parent.x < child.x;
      const start = parent.x + (forward ? nodeWidth : 0);
      const end = child.x + (forward ? 0 : nodeWidth);
      const y = parent.y + nodeHeight / 2;
      return { ...edge, path: `M ${start} ${y} L ${end} ${y}` };
    }
    const x1 = parent.x + nodeWidth / 2;
    const y1 = parent.y + nodeHeight;
    const x2 = child.x + nodeWidth / 2;
    const y2 = child.y;
    const bend = (y1 + y2) / 2;
    return { ...edge, path: `M ${x1} ${y1} C ${x1} ${bend}, ${x2} ${bend}, ${x2} ${y2}` };
  });
  return { origin, nodes, edges, width, height };
}
