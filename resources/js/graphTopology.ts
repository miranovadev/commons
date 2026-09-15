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

/** Null means the whole dataset; isolation never changes topology or selection. */
export function lineageVisibility(parents: Map<Identifier, Set<Identifier>>, selectedId: Identifier | null,
  ancestors: Set<Identifier>, isolate: boolean) {
  return isolate && selectedId !== null && parents.has(selectedId) ? new Set([...ancestors, selectedId]) : null;
}

/** Follow the server's primary transition source. Missing primary history is a boundary. */
export function graphNavigation(propositions: Proposition[], parents: Map<Identifier, Set<Identifier>>) {
  const byId = new Map(propositions.map(p => [p.id, p]));
  const previous = new Map<Identifier, Identifier>();
  const children = new Map<Identifier, Identifier[]>();
  for (const p of propositions) {
    const primary = p.transition?.source_id ?? p.parent_ids?.[0] ?? [...(parents.get(p.id) || [])].sort()[0];
    if (!primary || primary === p.id || !parents.get(p.id)?.has(primary)) continue;
    previous.set(p.id, primary);
  }
  // Malformed primary-parent cycles are boundaries, not infinite arrow walks.
  const visited = new Set<Identifier>();
  for (const p of propositions) {
    const path = new Set<Identifier>();
    let current: Identifier | undefined = p.id;
    while (current && !visited.has(current) && !path.has(current)) {
      path.add(current);
      current = previous.get(current);
    }
    if (current && path.has(current)) {
      let cycle: Identifier | undefined = current;
      do {
        const next: Identifier | undefined = previous.get(cycle!);
        previous.delete(cycle!);
        cycle = next;
      } while (cycle && cycle !== current);
    }
    for (const id of path) visited.add(id);
  }
  for (const [child, parent] of previous) {
    if (!children.has(parent)) children.set(parent, []);
    children.get(parent)!.push(child);
  }
  // Oldest proposition, then lexical ID. This is independent of API array order.
  const compare = (a: Identifier, b: Identifier) => {
    const date = (byId.get(a)?.created_at || '').localeCompare(byId.get(b)?.created_at || '');
    return date || (a < b ? -1 : a > b ? 1 : 0);
  };
  for (const ids of children.values()) ids.sort(compare);
  return { previous, children };
}

/** A directly selected branch wins over the default, including after walking back. */
export function rememberLineagePath(previous: Map<Identifier, Identifier>, selected: Identifier,
  remembered: Map<Identifier, Identifier> = new Map()) {
  const next = new Map(remembered);
  const visited = new Set<Identifier>();
  let child = selected;
  while (!visited.has(child)) {
    visited.add(child);
    const parent = previous.get(child);
    if (!parent) break;
    next.set(parent, child);
    child = parent;
  }
  return next;
}

export function nextInLineage(children: Map<Identifier, Identifier[]>, selected: Identifier | null,
  remembered: Map<Identifier, Identifier>) {
  const candidates = selected ? children.get(selected) || [] : [];
  const preferred = selected ? remembered.get(selected) : undefined;
  return preferred && candidates.includes(preferred) ? preferred : candidates[0] ?? null;
}
