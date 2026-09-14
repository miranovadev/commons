export type Identifier = string;
export type RegisterName = 'activity' | 'frontier' | 'canon' | 'signal' | 'stance' | 'concord';
export type DomainName = 'emergence' | 'settlement';
export type PhaseKind = 'distinguish' | 'persist' | 'constrain' | 'disperse' | 'mediate' | 'bias' | 'order';
export type PropositionTier = 'expressive' | 'committal';
export type AttestationDirection = 'support' | 'refute';
export type TriggerMode = 'manual' | 'interval' | 'scheduled';

export interface Observables {
  correspondence?: number | string | null;
  chirality?: number | string | null;
  closure?: number | string | null;
}

export interface Proposition {
  id: Identifier;
  register: RegisterName;
  tier: PropositionTier;
  title?: string | null;
  statement: string;
  is_origin?: boolean;
  participant?: Pick<Participant, 'id' | 'callsign'>;
  participant_callsign?: string;
  callsign?: string;
  observables?: Observables & { status?: 'missing' | 'stale' | 'current' };
  transition?: { type?: string; from?: RegisterName; to?: RegisterName };
  parent_ids?: Identifier[];
  children?: Proposition[];
  attestation?: { direction: AttestationDirection };
  reinforcement_count?: number;
  attestation_counts?: { support: number; refute: number };
  attestation_summary?: { support_direction: 'positive' | 'negative' | null; retained_direction?: 'positive' | 'negative'; positive: number; negative: number };
  instruments?: Array<{
    id: Identifier;
    kind: string;
    reference: string;
    status: 'pending' | 'completed' | 'failed';
    failure_reason: string | null;
    receipt_id: Identifier | null;
    reinforcement_status?: string | null;
    content_verified?: boolean;
  }>;
}

export interface User {
  id: Identifier;
  name: string;
  email: string;
  has_system_access?: boolean;
}

export interface Phase {
  id: Identifier;
  kind: PhaseKind;
  name?: string;
  number: number;
  write_register: RegisterName;
}

export interface ParticipantPosition {
  cycle_sequence: number;
  cycle_completed?: boolean;
  phase?: Phase;
  gate?: Proposition;
}

export interface Participant {
  id: Identifier;
  callsign: string;
  designation?: string | null;
  max_read_count: number;
  max_token_count?: number | null;
  owned_by_user?: boolean;
  position?: ParticipantPosition | null;
}

export interface Substrate {
  id: Identifier;
  slug: string;
  title: string;
  description?: string | null;
  visibility: 'public' | 'private';
  participants_count?: number;
  potential_charge?: number | null;
  tension?: number | null;
}

export interface ConnectionConfiguration {
  model?: string;
  instructions?: string | null;
}

export interface Connection {
  id: Identifier;
  provider: string;
  name: string;
  configuration?: ConnectionConfiguration;
  participants_count?: number;
}

export interface ConnectionTrigger {
  mode: TriggerMode;
  interval_minutes?: number;
}

export interface ParticipantConnection {
  id: Identifier;
  connection_id: Identifier;
  connection?: Connection;
  is_enabled: boolean;
  configuration?: { trigger?: ConnectionTrigger };
  next_trigger_at?: string | null;
  last_error?: string | null;
}

export interface ConnectionOptions {
  providers?: Array<{ value: string; label: string }>;
  trigger_modes: Array<{ value: TriggerMode; label: string }>;
}

export type ActionName =
  | 'attestation.create'
  | 'instrument.execute'
  | 'receipt.read'
  | 'receipt.replay'
  | 'proposition.commit'
  | 'proposition.reclassify'
  | 'proposition.reconcile'
  | 'proposition.bias'
  | 'proposition.revise'
  | 'participant.rest'
  | 'proposition.traverse'
  | 'proposition.variant';

export type InstrumentInput =
  | { kind: 'citation' | 'hyperlink'; reference: string }
  | { kind: 'web_search'; query: string };

export interface ContributionInput {
  action: ActionName;
  proposition_id?: Identifier;
  other_proposition_id?: Identifier;
  receipt_id?: Identifier;
  statement?: string;
  direction?: AttestationDirection;
  rationale?: string;
  instrument?: InstrumentInput;
}

export interface OriginInput {
  statement: string;
  attestation: { direction: AttestationDirection; rationale?: string };
  instrument: InstrumentInput;
}

export type ContributionInitialValues = Partial<Omit<ContributionInput, 'action'>>;

export interface ActionSchemaProperty {
  const?: ActionName;
  enum?: string[];
}

export interface ActionSchema {
  title: string;
  properties: Partial<Record<keyof ContributionInput, ActionSchemaProperty>>;
  required?: Array<keyof ContributionInput>;
  anyOf?: Array<{ properties: ActionSchema['properties'] }>;
}

export interface InputSchema {
  oneOf?: Array<{ $ref?: string }>;
  $defs?: { actions?: Partial<Record<ActionName, ActionSchema>> };
}

export interface WorkspaceHorizon {
  id: Identifier;
  sequence: number;
  domain: DomainName;
  parent_context_horizon_id: Identifier | null;
  opened_by_proposition_id: Identifier | null;
  opening_proposition: Proposition | null;
  opened_at: string;
}

export interface ReceiptContext {
  id: Identifier;
  proposition_id: Identifier;
  type: string;
  reference: string;
  signature: string | null;
  status: 'available' | 'loaded' | 'already-in-context' | 'unqualified';
  loaded_at: string | null;
  capabilities: { readable: boolean; replayable: boolean };
  can_replay: boolean;
  replayed_from_receipt_id: Identifier | null;
  observation_error?: string | null;
  evidence?: { query?: string; executed_at?: string; results?: Array<{ title: string; url: string; excerpt: string; excerpt_truncated: boolean; source: string; rank: number; relevance: number | null; published_at: string | null }>; proposition_id?: Identifier; statement?: string; direction?: string; rationale?: string | null; resource?: string; fingerprint?: string; observed_at?: string; content_excerpt?: string | null; excerpt_truncated?: boolean };
}

export interface ParticipantPlan {
  receipts?: ReceiptContext[];
  commitment_options?: Record<Identifier, NonNullable<ParticipantPlan['commitment']>>;
  commitment?: {
    admissible: boolean;
    requirements: { attestation: boolean; receipt: boolean; closure: boolean | null; write_opportunity?: boolean };
    reinforcement_count: number;
    closure_enforced: boolean;
    closure?: {
      depth: number;
      cadence: number;
      closure: number;
      depth_direction: 'positive' | 'negative';
      cadence_direction: 'positive' | 'negative';
      history_proposition_id: Identifier | null;
      history_source: string;
    };
    reasons: string[];
  } | null;
  phase: Phase;
  gate: Proposition;
  lineage?: Proposition[];
  tragedy?: boolean;
  traversal_options?: Proposition[];
  instrument_options?: Proposition[];
  write_options?: Proposition[];
  revision_options?: Proposition[];
  gate_options?: Proposition[];
  read_options?: Proposition[];
  attestation_options?: Proposition[];
  workspace?: {
    domain?: DomainName;
    horizon?: WorkspaceHorizon | null;
    provenance?: Proposition;
    registers?: Partial<Record<RegisterName, Proposition[]>>;
  };
  schema?: InputSchema;
  perspectiveLimit?: number;
}

export interface PropositionSelection {
  kind?: string;
  title?: string;
  proposition: Proposition;
  projectedObservables?: Observables;
}

export interface Participation {
  completion?: { cycle_id: Identifier; phase_id: Identifier; completed_at: string; proposition_id: Identifier } | null;
  current_proposition_id?: Identifier | null;
  domain?: DomainName | null;
  horizon_sequence?: number | null;
  context_horizon_id?: Identifier | null;
  last_commitment?: {
    proposition_id: Identifier;
    depth: number | null;
    cadence: number | null;
    depth_direction: 'positive' | 'negative' | null;
    cadence_direction: 'positive' | 'negative' | null;
    domain_before: DomainName;
    domain_after: DomainName;
    tension_before: number | null;
    tension_after: number;
    closure_boundary: boolean;
  } | null;
  status: 'waiting' | 'ready';
  plan?: ParticipantPlan;
  entry?: { mode?: 'origin' | 'continuity' | 'potential'; selection?: 'position' | 'recovery'; available?: boolean; message?: string; proposition_id?: Identifier | null; distance?: number | null; potential_charge?: number };
  reason?: { message?: string };
}

export interface BootstrapInput {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  substrate_title?: string;
  substrate_slug?: string;
  substrate_description?: string | null;
}

export interface LoginInput {
  email: string;
  password: string;
}

export interface SubstrateInput {
  title: string;
  slug: string;
  description?: string | null;
  visibility: Substrate['visibility'];
  configuration: { maximum_participants: number };
}

export interface ConnectionInput {
  provider: string;
  name: string;
  configuration: ConnectionConfiguration;
  credentials?: { api_key: string };
}

export interface JoinParticipantInput {
  slug: string;
  callsign: string;
  designation?: string | null;
  max_read_count: number;
  max_token_count?: number | null;
  connection?: { id: Identifier };
}

export interface ParticipantParameters {
  slug: string;
  participantId: Identifier;
}

export interface ApiEnvelope<T> {
  data: T;
}

/** Protected autonomous context; human display uses ParticipantPlan via /workspace. */
export interface ParticipantContext {
  tragedy: boolean;
  commitment?: ParticipantPlan['commitment'];
  commitment_options?: ParticipantPlan['commitment_options'];
  phase: Pick<Phase, 'id' | 'number' | 'kind' | 'write_register'>;
  provenance_id: Identifier | null;
  gate_id: Identifier;
  lineage: Proposition[];
  read_options: Proposition[];
  gate_options: Proposition[];
  write_options: Proposition[];
  schema: InputSchema;
}

export type AutonomousParticipation = Omit<Participation, 'plan'> & { plan?: ParticipantContext | null };
