import type { Proposition } from './types';

/** The API resolves hard replacements and supplies provenance-to-head order. */
export function chronologicalLineage(lineage: Proposition[]): Proposition[] {
  return [...lineage];
}
