import type { Identifier, Proposition } from './types';

/** Both API forms describe the same causal edges, including reconciliation. */
export function graphParents(propositions: Proposition[]) {
  const parents = new Map<Identifier, Set<Identifier>>(propositions.map(p => [p.id, new Set()]));
  for (const proposition of propositions) {
    for (const parentId of proposition.parent_ids || []) {
      if (parents.has(parentId)) parents.get(proposition.id)!.add(parentId);
    }
    for (const child of proposition.children || []) {
      parents.get(child.id)?.add(proposition.id);
    }
  }
  return parents;
}

/** All loaded ancestral paths, with shared ancestors deduplicated. O(V + E). */
export function causalAncestors(parents: Map<Identifier, Set<Identifier>>, selectedId: Identifier | null) {
  const ancestors = new Set<Identifier>();
  const visited = new Set<Identifier>(selectedId ? [selectedId] : []);
  const pending = selectedId ? [...(parents.get(selectedId) || [])] : [];
  while (pending.length) {
    const id = pending.pop()!;
    if (visited.has(id)) continue;
    visited.add(id);
    ancestors.add(id);
    for (const parent of parents.get(id) || []) pending.push(parent);
  }
  return ancestors;
}
