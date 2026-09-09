<?php

namespace App\Services;

use App\Enums\ContextDomain;
use App\Models\ContextHorizon;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use Illuminate\Support\Collection;

final readonly class InputPlanService
{
    public function __construct(
        private PhaseService $phases,
        private GateService $gates,
        private LineageService $lineage,
        private PropositionOptionService $options,
        private InputSchemaService $schemas,
        private PropositionMeasurementService $measurements,
        private CommitmentAdmissibilityService $admissibility,
    ) {}

    /** @return array<string, mixed> */
    public function forParticipant(Participant $participant): array
    {
        $phase = $this->phases->currentFor($participant);
        $plan = $this->phases->plan($phase->kind);
        $gate = $this->gates->forPhase($phase);
        $lineage = $this->lineage->endingAt(
            $gate,
            $participant->max_read_count,
            $participant->max_token_count,
        );
        $writeOptions = $this->options->writeOptionsFor($phase);
        $revisionOptions = $this->options->revisionOptionsFor(
            $phase,
            $participant,
        );
        $gateOptions = $this->options->gateOptionsFor($phase);
        $traversalOptions = $this->options->traversalOptionsFor($phase);
        $instrumentOptions = $this->options->instrumentOptionsFor($phase);
        $attestationOptions = $this->options->attestationOptionsFor($phase);
        $this->loadPropositionState(
            $participant,
            $gate,
            $lineage,
            $writeOptions,
            $revisionOptions,
            $gateOptions,
            $attestationOptions,
            $traversalOptions,
            $instrumentOptions,
        );
        $workspace = $this->workspace(
            $participant,
            $phase,
            $gate,
            $lineage,
        );
        $attestationOptionIds = $attestationOptions->pluck('id')->all();
        $schema = $this->schemas->forParticipant(
            $participant,
            $writeOptions->pluck('id')->all(),
            $gateOptions->pluck('id')->all(),
            $revisionOptions->pluck('id')->all(),
            $attestationOptionIds,
            $traversalOptions->pluck('id')->all(),
            $instrumentOptions->pluck('id')->all(),
        );

        return [
            'phase' => [
                'id' => $phase->getKey(),
                'number' => $phase->kind->value,
                'kind' => $plan['name'],
                'description' => $plan['description'],
                'write_register' => $phase->write_register,
                'started_at' => $phase->started_at,
            ],
            'workspace' => $workspace,
            'commitment' => $phase->kind === \App\Enums\PhaseKind::Order
                ? $this->admissibility->evaluate($gate, $phase)
                : null,
            'gate' => $this->proposition($gate, $participant),
            'lineage' => $this->propositions($lineage, $participant),
            'tragedy' => isset($schema['$defs']['actions']['participant.rest']),
            'traversal_options' => $this->propositions($traversalOptions, $participant),
            'instrument_options' => $this->propositions($instrumentOptions, $participant),
            'write_options' => $this->propositions($writeOptions, $participant),
            'revision_options' => $this->propositions($revisionOptions, $participant),
            'gate_options' => $this->propositions($gateOptions, $participant),
            'attestation_options' => $this->propositions($attestationOptions, $participant),
            'schema' => $schema,
        ];
    }

    /**
     * @param Collection<int, Proposition> $lineage
     * @return array<string, mixed>
     */
    private function workspace(
        Participant $participant,
        Phase $phase,
        Proposition $gate,
        Collection $lineage,
    ): array {
        $horizon = ContextHorizon::query()
            ->where('substrate_id', $participant->substrate_id)
            ->whereNull('closed_at')
            ->first();
        $domain = $horizon?->domain ?? $this->domainFor($phase, $gate);
        $registers = $domain === ContextDomain::Emergence
            ? ['activity', 'frontier', 'canon']
            : ['signal', 'stance', 'concord'];
        $propositions = $horizon === null
            ? collect()
            : $horizon->propositions()
                ->with([
                    'measurement',
                    'parents',
                    'instrumentExecutions.receipt',
                    'effectiveAttestations' => $this->attestationsFor(
                        $participant
                    ),
                    'phase.cycle.participant:id,callsign',
                ])
                ->whereIn('register', $registers)
                ->latest('propositions.created_at')
                ->limit(max(1, (int) ($participant->max_read_count ?? 25)))
                ->get();

        $this->measurements->repairMissing($propositions);

        $provenance = $horizon?->opened_by_proposition_id === null
            ? $lineage->first(
                fn (Proposition $item): bool => $item->tier === 'committal'
            )
            : Proposition::query()->find(
                $horizon->opened_by_proposition_id
            );

        $provenance ??= $gate;
        $provenance->loadMissing([
            'measurement',
            'parents',
            'instrumentExecutions.receipt',
            'effectiveAttestations' => $this->attestationsFor($participant),
            'phase.cycle.participant:id,callsign',
        ]);
        $this->measurements->repairMissing(collect([$provenance]));

        return [
            'domain' => $domain->value,
            'horizon' => $horizon === null ? null : [
                'id' => $horizon->getKey(),
                'sequence' => $horizon->sequence,
                'opened_at' => $horizon->opened_at,
            ],
            'provenance' => $this->proposition($provenance, $participant),
            'registers' => collect($registers)
                ->mapWithKeys(fn (string $register): array => [
                    $register => $this->propositions(
                        $propositions
                            ->where('register', $register)
                            ->values(),
                        $participant,
                    ),
                ])
                ->all(),
        ];
    }

    private function domainFor(
        Phase $phase,
        Proposition $gate,
    ): ContextDomain {
        $register = $phase->write_register ?? $gate->register;

        return in_array(
            $register,
            ['signal', 'stance', 'concord'],
            true,
        ) ? ContextDomain::Settlement : ContextDomain::Emergence;
    }

    private function loadPropositionState(
        Participant $participant,
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
            'phase.cycle.participant:id,callsign',
        ]);
        $this->measurements->repairMissing($collection);
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
     * @param Collection<int, Proposition> $propositions
     * @return list<array<string, mixed>>
     */
    private function propositions(
        Collection $propositions,
        Participant $participant,
    ): array
    {
        return $propositions
            ->map(fn (Proposition $proposition): array => $this->proposition(
                $proposition,
                $participant,
            ))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function proposition(
        Proposition $proposition,
        Participant $viewer,
    ): array
    {
        $measurement = $proposition->relationLoaded('measurement')
            ? $proposition->measurement
            : null;
        $parent = $proposition->relationLoaded('parents')
            ? $proposition->parents->first()
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
            'title' => str($proposition->statement)
                ->before("\n")
                ->trim()
                ->limit(72)
                ->toString(),
            'participant' => $author?->only(['id', 'callsign']),
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
                ])->all()
                : [],
            'transition' => $parent === null ? null : [
                'type' => $parent->pivot->type,
                'from' => $parent->register,
                'to' => $proposition->register,
                'source_id' => $parent->getKey(),
            ],
            'observables' => [
                'correspondence' => $measurement?->correspondence,
                'chirality' => $measurement?->chirality,
                'closure' => $measurement?->closure,
                'calculated_at' => $measurement?->calculated_at,
            ],
        ];
    }
}
