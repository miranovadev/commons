export type Identifier = string | number;

export interface Observables {
  correspondence?: number | string | null;
  chirality?: number | string | null;
  closure?: number | string | null;
  [key: string]: unknown;
}

export interface Proposition {
  id?: Identifier;
  register?: string;
  tier?: string;
  title?: string;
  statement?: string;
  is_origin?: boolean;
  participant?: { callsign?: string };
  participant_callsign?: string;
  callsign?: string;
  observables?: Observables;
  transition?: { type?: string; from?: string; to?: string };
  parent_ids?: Identifier[];
  children?: Proposition[];
  attestation?: { direction?: string };
  [key: string]: any;
}
