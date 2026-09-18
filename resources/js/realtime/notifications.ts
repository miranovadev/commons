import type { Identifier, Proposition } from '../types';

export type Invalidation =
  | { type: 'participant.state_changed'; participant_id: Identifier }
  | { type: 'graph.participant_changed'; participant_id: Identifier; substrate_id: Identifier }
  | { type: 'graph.metrics_changed'; substrate_id: Identifier }
  | { type: 'proposition.created' | 'proposition.updated' | 'proposition.spent' | 'attestation.created'; proposition_id: Identifier; context_horizon_id: Identifier }
  | { type: 'exploration.epoch_advanced'; structural_position: number; exploration_epoch: number; context_horizon_id: Identifier }
  | { type: 'receipt.created' | 'receipt.consumed'; receipt_id: Identifier; context_horizon_id: Identifier }
  | { type: 'horizon.tragedy'; tragedy_id: Identifier; receipt_id: Identifier; context_horizon_id: Identifier }
  | { type: 'horizon.exhausted'; opening_proposition_id: Identifier | null; context_horizon_id: Identifier }
  | { type: 'context-horizons.changed'; substrate_id: Identifier };

export interface GraphSubscriptions {
  substrate_id: Identifier;
  public: boolean;
  context_horizon_ids: Identifier[];
}

/** Upsert into RTK Query's draft; this is not a separate resource cache. */
export function mergeProposition(items: Proposition[], proposition: Proposition) {
  const index = items.findIndex(item => item.id === proposition.id);
  if (proposition.active === false) {
    if (index >= 0) items.splice(index, 1);
    return;
  }
  if (index < 0) items.push(proposition);
  else items[index] = proposition;
}

/** Coalesce a mutation's burst; an event arriving during a fetch runs again after it. */
export function notificationQueue(run: (keys: string[]) => Promise<void>, delay = 75) {
  const pending = new Set<string>();
  let timer: ReturnType<typeof setTimeout> | undefined;
  let running = false;
  let disposed = false;
  function schedule() {
    if (!disposed && !running && !timer && pending.size) timer = setTimeout(flush, delay);
  }
  async function flush() {
    timer = undefined;
    if (disposed) return;
    running = true;
    const keys = [...pending];
    pending.clear();
    try { await run(keys); }
    catch { /* HTTP failures leave the existing authoritative cache intact. */ }
    finally { running = false; schedule(); }
  }
  return {
    add(key: string) { if (!disposed) { pending.add(key); schedule(); } },
    dispose() { disposed = true; pending.clear(); clearTimeout(timer); },
  };
}
