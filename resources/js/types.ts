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
  observables?: Observables;
  transition?: { type?: string; from?: RegisterName; to?: RegisterName };
  parent_ids?: Identifier[];
  children?: Proposition[];
  attestation?: { direction: AttestationDirection };
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
  | 'proposition.commit'
  | 'proposition.reclassify'
  | 'proposition.reclassify_with_instrument'
  | 'proposition.reconcile'
  | 'proposition.revise'
  | 'proposition.select'
  | 'proposition.variant';

export interface InstrumentInput {
  kind: 'citation' | 'artefact' | 'hyperlink';
  reference: string;
}

export interface ContributionInput {
  action: ActionName;
  proposition_id?: Identifier;
  other_proposition_id?: Identifier;
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
}

export interface InputSchema {
  oneOf?: Array<{ $ref?: string }>;
  $defs?: { actions?: Partial<Record<ActionName, ActionSchema>> };
}

export interface ParticipantPlan {
  phase: Phase;
  gate: Proposition;
  lineage?: Proposition[];
  write_options?: Proposition[];
  revision_options?: Proposition[];
  gate_options?: Proposition[];
  workspace?: {
    domain?: DomainName;
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
  status: 'waiting' | 'active';
  plan?: ParticipantPlan;
  entry?: { mode?: 'origin' | 'potential'; available?: boolean; message?: string };
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
