import type { Proposition } from './types';

/** Walk only this supplied lineage, stopping at its ancestral Committal boundaries. */
export function chronologicalLineage(lineage: Proposition[]): Proposition[] {
  const nodes = new Map(lineage.map(node => [node.id, node]));
  const visited = new Set<string>();
  const ordered: Proposition[] = [];
  function visit(node: Proposition) {
    if (visited.has(node.id)) return;
    visited.add(node.id);
    if (node.tier !== 'committal') for (const id of node.parent_ids || []) {
      const parent = nodes.get(id);
      if (parent) visit(parent);
    }
    ordered.push(node);
  }
  // The backend's endingAt contract places the displayed structural head first.
  if (lineage[0]) {
    if (lineage[0].parent_ids) visit(lineage[0]);
    else { // Older snapshots already supply head-first structural layers.
      const boundary = lineage.findIndex(node => node.tier === 'committal');
      return lineage.slice(0, boundary < 0 ? undefined : boundary + 1).reverse();
    }
  }
  return ordered;
}
