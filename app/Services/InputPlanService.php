<?php

namespace App\Services;

use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Observables\ReinforcementService;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

final readonly class InputPlanService
{
    public function __construct(
        private PhaseService $phases,
        private GateService $gates,
        private ActiveParticipantLineage $lineage,
        private PropositionOptionService $options,
        private InputSchemaService $schemas,
        private PropositionMeasurementService $measurements,
        private CommitmentAdmissibilityService $admissibility,
        private PropositionStateService $state,
        private MediationReadService $mediation,
        private PropositionPresentationService $presentation,
        private ReceiptContextService $receipts,
        private ReinforcementService $reinforcement,
    ) {}

    /** @return array<string, mixed> */
    public function forParticipant(Participant $participant): array
    {
        return $this->calculate($participant, false);
    }

    public function forWorkspace(Participant $participant): array
    {
        return $this->calculate($participant, true);
    }

    /** Keep the response's Cycle, Phase and schema on the same observed occurrence. */
    public function forPhase(Participant $participant, Phase $phase, bool $workspace = false): array
    {
        return $this->retiredPlan($phase, $workspace) ?? $this->build($participant, $phase, new ParticipationContext($phase), $workspace);
    }

    /** Contribution validation needs admissibility, never workspace/presentation serialization. */
    public function forValidation(Participant $participant): array
    {
        return $this->calculate($participant, false, true);
    }

    private function calculate(Participant $participant, bool $workspace, bool $validation = false): array
    {
        $phase = $this->phases->currentFor($participant);

        // Observation is optimistic. Only contribution validation owns write locks.
        return $this->retiredPlan($phase, $workspace) ?? $this->build($participant, $phase, new ParticipationContext($phase), $workspace, $validation);
    }

    private function retiredPlan(Phase $phase, bool $workspace): ?array
    {
        if ($phase->gate_proposition_id === null || ! app(OriginBranchService::class)->pruned($phase->gate_proposition_id)) {
            return null;
        }
        $plan = $this->phases->plan($phase->kind);

        return [
            'phase' => ['id' => $phase->id, 'number' => $phase->kind->value, 'kind' => $plan['name'],
                'description' => 'This branch is historical. Rest to begin recovery.',
                'write_register' => $phase->write_register, 'started_at' => $phase->started_at],
            ...($workspace ? ['workspace' => null] : []),
            'gate' => [...$phase->gate()->firstOrFail(['id', 'register', 'tier'])->only(['id', 'register', 'tier']),
                'statement' => 'Historical position', 'parent_ids' => []],
            'provenance_id' => null, 'lineage' => [], 'read_options' => [], 'write_options' => [],
            'gate_options' => [], 'revision_options' => [], 'traversal_options' => [],
            'attestation_options' => [], 'instrument_options' => [], 'receipts' => [],
            'commitment' => null, 'commitment_options' => [], 'tragedy' => true, 'branch_pruned' => true,
            'schema' => $this->schemas->forPhase($phase->kind, ['participant.rest']),
        ];
    }

    /** All repeated evaluation shares this calculation-local context. */
    private function build(Participant $participant, Phase $phase, ?ParticipationContext $context, bool $includeWorkspace = false, bool $validation = false): array
    {
        $plan = $this->phases->plan($phase->kind);
        $gate = $context?->propositions->get($phase->gate_proposition_id) ?? $this->gates->forPhase($phase);
        $lineage = $this->lineage->endingAt(
            $gate,
            $context,
        );
        $lineage = $lineage->filter(fn ($node) => $context === null || $context->activeIds->has($node->id))->values();
        $writeOptions = $this->options->writeOptionsFor($phase, $context);
        $revisionOptions = $this->options->revisionOptionsFor(
            $phase,
            $participant,
            $context,
        );
        $readOptions = $phase->kind === PhaseKind::Order ? $this->options->orderReadsFor($phase, $context) : $this->mediation->readsFor($phase, $context);
        $gateOptions = $this->options->gateOptionsFor($phase, $context);
        $traversalOptions = $this->options->traversalOptionsFor($phase, $context);
        $instrumentOptions = $this->options->instrumentOptionsFor($phase, $context);
        $attestationOptions = $this->options->attestationOptionsFor($phase, $context);
        if ($context !== null) {
            foreach ([$lineage, $writeOptions, $revisionOptions, $readOptions, $gateOptions, $traversalOptions, $instrumentOptions, $attestationOptions] as $collection) {
                $collection->transform(fn (Proposition $node) => $context->proposition($node));
            }
        }
        $attestationOptionIds = $attestationOptions->pluck('id')->all();
        $schema = $this->schemas->forParticipant(
            $participant,
            $writeOptions->pluck('id')->all(),
            $gateOptions->pluck('id')->all(),
            $revisionOptions->pluck('id')->all(),
            $attestationOptionIds,
            $traversalOptions->pluck('id')->all(),
            $instrumentOptions->pluck('id')->all(),
            $context,
        );

        if ($validation) {
            return ['schema' => $schema];
        }
        $this->loadPropositionState(
            $participant,
            $context,
            $gate,
            $lineage,
            $readOptions,
            $writeOptions,
            $revisionOptions,
            $gateOptions,
            $attestationOptions,
            $traversalOptions,
            $instrumentOptions,
        );
        $workspace = $includeWorkspace ? $this->workspace(
            $participant,
            $phase,
            $gate,
            $lineage,
            $context,
        ) : null;
        $targetIds = collect($schema['$defs']['actions'] ?? [])->flatMap(fn ($action) => array_merge(
            $action['properties']['proposition_id']['enum'] ?? [],
            $action['properties']['other_proposition_id']['enum'] ?? [],
        ));
        $operationalIds = $readOptions->concat($gateOptions)->concat([$gate])->pluck('id')
            ->merge($targetIds);
        $historical = $lineage->whereNotIn('id', $operationalIds)->values();

        return [
            'phase' => [
                'id' => $phase->getKey(),
                'number' => $phase->kind->value,
                'kind' => $plan['name'],
                'description' => $plan['description'],
                'write_register' => $phase->write_register,
                'started_at' => $phase->started_at,
            ],
            ...($includeWorkspace ? ['workspace' => $workspace] : []),
            'commitment' => $phase->kind === PhaseKind::Order
                ? $this->admissibility->evaluate($gate, $phase, $context)
                : null,
            'commitment_options' => $phase->kind === PhaseKind::Order
                ? $gateOptions->mapWithKeys(fn (Proposition $candidate): array => [
                    $candidate->id => $this->admissibility->evaluate($candidate, $phase, $context),
                ])->all() : [],
            'gate' => $this->proposition($gate, $participant, $context),
            'provenance_id' => $lineage->firstWhere('tier', 'committal')?->id,
            'lineage' => $this->propositions($historical, $participant, $context),
            'read_options' => $this->propositions($readOptions, $participant, $context),
            'receipts' => $this->receipts->forPhase($phase, $context)->all(),
            'tragedy' => isset($schema['$defs']['actions']['participant.rest']),
            'traversal_options' => $this->propositions($traversalOptions, $participant, $context),
            'instrument_options' => $this->propositions($instrumentOptions, $participant, $context),
            'write_options' => $this->propositions($writeOptions, $participant, $context),
            'revision_options' => $this->propositions($revisionOptions, $participant, $context),
            'gate_options' => $this->propositions($gateOptions, $participant, $context),
            'attestation_options' => $this->propositions($attestationOptions, $participant, $context),
            'schema' => $schema,
        ];
    }

    /**
     * @param  Collection<int, Proposition>  $lineage
     * @return array<string, mixed>
     */
    private function workspace(
        Participant $participant,
        Phase $phase,
        Proposition $gate,
        Collection $lineage,
        ?ParticipationContext $context,
    ): array {
        $horizon = $context !== null ? $context->horizons->get($phase->cycle->context_horizon_id) : $phase->cycle->contextHorizon;
        $domain = $horizon->domain;
        $registers = $domain === ContextDomain::Emergence
            ? ['activity', 'frontier', 'canon']
            : ['signal', 'stance', 'concord'];
        $propositions = $horizon === null
            ? collect()
            : ($context !== null ? $context->nodes($horizon->propositions()->active()->whereIn('register', $registers)
                ->latest('propositions.created_at')->limit(max(1, (int) ($participant->max_read_count ?? 25)))->pluck('propositions.id'))
            : $horizon->propositions()->active()
                ->with([
                    'measurement',
                    'parents',
                    'instrumentExecutions.receipt',
                    'effectiveAttestations' => $this->attestationsFor(
                        $participant
                    ),
                    'phase.cycle.participant:id,callsign,substrate_id',
                ])
                ->whereIn('register', $registers)
                ->latest('propositions.created_at')
                ->limit(max(1, (int) ($participant->max_read_count ?? 25)))
                ->get());

        $this->measurements->observeMissing($propositions, $context);

        $provenance = $lineage->firstWhere('tier', 'committal');

        $provenance ??= $gate;
        if ($context !== null) {
            $this->loadPropositionState($participant, $context, $provenance, $propositions);
        }
        $provenance->loadMissing([
            'measurement',
            'parents',
            'instrumentExecutions.receipt',
            'effectiveAttestations' => $this->attestationsFor($participant),
            'phase.cycle.participant:id,callsign,substrate_id',
        ]);
        $this->measurements->observeMissing(collect([$provenance]), $context);
        $this->state->loadAttestationCounts($propositions->concat([$provenance]), $context);

        // A causal boundary is context, not an invented ancestor of this Gate.
        $opening = $horizon->opened_by_proposition_id === null ? null
            : ($context !== null ? $context->propositions->get($horizon->opened_by_proposition_id)
                : Proposition::findOrFail($horizon->opened_by_proposition_id));
        if ($opening !== null) {
            $this->loadPropositionState($participant, $context, $opening);
        }

        return [
            'domain' => $domain->value,
            'horizon' => $horizon === null ? null : [
                'id' => $horizon->getKey(),
                'sequence' => $horizon->sequence,
                'domain' => $domain->value,
                'parent_context_horizon_id' => $horizon->parent_context_horizon_id,
                'opened_by_proposition_id' => $horizon->opened_by_proposition_id,
                'opening_proposition' => $opening === null ? null : $this->proposition($opening, $participant, $context),
                'opened_at' => $horizon->opened_at,
            ],
            'provenance' => $this->proposition($provenance, $participant, $context),
            'registers' => collect($registers)
                ->mapWithKeys(fn (string $register): array => [
                    $register => $this->propositions(
                        $propositions
                            ->where('register', $register)
                            ->values(),
                        $participant,
                        $context,
                    ),
                ])
                ->all(),
        ];
    }

    private function loadPropositionState(
        Participant $participant,
        ?ParticipationContext $context,
        Proposition $gate,
        Collection ...$collections,
    ): void {
        $propositions = collect([$gate]);

        foreach ($collections as $collection) {
            $propositions = $propositions->concat($collection);
        }

        $collection = $gate->newCollection(
            $propositions->values()->all()
        );
        $collection->loadMissing([
            'measurement',
            'parents',
            'instrumentExecutions.receipt',
            'effectiveAttestations' => $this->attestationsFor($participant),
            'phase.cycle.participant:id,callsign,substrate_id',
        ]);
        $this->measurements->observeMissing($collection, $context);
        $this->state->loadAttestationCounts($collection, $context);
    }

    private function attestationsFor(Participant $participant): \Closure
    {
        return fn ($query) => $query
            ->whereHas(
                'phase.cycle',
                fn ($query) => $query->whereBelongsTo($participant),
            )
            ->with('phase.cycle');
    }

    /**
     * @param  Collection<int, Proposition>  $propositions
     * @return list<array<string, mixed>>
     */
    private function propositions(
        Collection $propositions,
        Participant $participant,
        ?ParticipationContext $context,
    ): array {
        return $propositions
            ->map(fn (Proposition $proposition): array => $this->proposition(
                $proposition,
                $participant,
                $context,
            ))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function proposition(
        Proposition $proposition,
        Participant $viewer,
        ?ParticipationContext $context,
    ): array {
        $measurement = $proposition->relationLoaded('measurement')
            ? $proposition->measurement
            : null;
        $author = $proposition->relationLoaded('phase')
            && $proposition->phase->relationLoaded('cycle')
            && $proposition->phase->cycle->relationLoaded('participant')
                ? $proposition->phase->cycle->participant
                : null;
        $attestation = $proposition->relationLoaded('effectiveAttestations')
            ? $proposition->effectiveAttestations->first()
            : null;

        return [
            ...$proposition->only([
                'id',
                'register',
                'tier',
                'statement',
                'created_at',
            ]),
            'parent_ids' => $proposition->parents->pluck('id')->all(),
            'title' => str($proposition->statement)
                ->before("\n")
                ->trim()
                ->limit(72)
                ->toString(),
            'participant' => $author?->only(['id', 'callsign']),
            'reinforcement_count' => $this->reinforcement->count($proposition, $context),
            'attestation_counts' => $proposition->attestationCounts->all(),
            'attestation_summary' => $this->presentation->attestations($proposition, $context),
            'attestation' => $attestation?->only([
                'id',
                'direction',
                'rationale',
                'created_at',
            ]),
            'instruments' => $proposition->relationLoaded('instrumentExecutions')
                ? $proposition->instrumentExecutions->map(fn ($execution): array => [
                    'id' => $execution->getKey(),
                    'kind' => $execution->kind,
                    'reference' => $execution->reference,
                    'status' => $execution->status,
                    'failure_reason' => $execution->failure_reason,
                    'receipt_id' => $execution->receipt?->getKey(),
                    'reinforcement_status' => $execution->receipt === null ? null : ($this->receipts->forPhase($context?->phase ?? $this->phases->currentFor($viewer), $context)->firstWhere('id', $execution->receipt->id)['status'] ?? 'not-loaded'),
                    'content_verified' => $execution->receipt?->metadata['content_verified'] ?? false,
                ])->all()
                : [],
            'transition' => $this->presentation->transition($proposition, $context),
            'observables' => [
                'correspondence' => $measurement?->correspondence,
                'chirality' => $measurement?->chirality,
                'closure' => $measurement?->closure,
                'calculated_at' => $measurement?->calculated_at,
            ],
        ];
    }
}
