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
        $this->loadPropositionState(
            $gate,
            $lineage,
            $writeOptions,
            $revisionOptions,
            $gateOptions,
        );
        $workspace = $this->workspace(
            $participant,
            $phase,
            $gate,
            $lineage,
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
            'gate' => $this->proposition($gate, $participant),
            'lineage' => $this->propositions($lineage, $participant),
            'write_options' => $this->propositions($writeOptions, $participant),
            'revision_options' => $this->propositions($revisionOptions, $participant),
            'gate_options' => $this->propositions($gateOptions, $participant),
            'schema' => $this->schemas->forParticipant(
                $participant,
                $writeOptions->pluck('id')->all(),
                $gateOptions->pluck('id')->all(),
                $revisionOptions->pluck('id')->all(),
            ),
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
                    'parents.measurement',
                    'effectiveAttestations.phase.cycle',
                    'phase.cycle.participant:id,callsign',
                ])
                ->whereIn('register', $registers)
                ->latest('propositions.created_at')
                ->limit(max(1, (int) ($participant->max_read_count ?? 25)))
                ->get();

        $this->repairMeasurements($propositions);

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
            'parents.measurement',
            'effectiveAttestations.phase.cycle',
            'phase.cycle.participant:id,callsign',
        ]);
        $this->repairMeasurements(collect([$provenance]));

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
        Proposition $gate,
        Collection ...$collections,
    ): void {
        $propositions = collect([$gate]);

        foreach ($collections as $collection) {
            $propositions = $propositions->concat($collection);
        }

        $collection = $gate->newCollection(
            $propositions->unique(
                fn (Proposition $proposition): string => $proposition->getKey()
            )->values()->all()
        );
        $collection->loadMissing([
            'measurement',
            'parents.measurement',
            'effectiveAttestations.phase.cycle',
            'phase.cycle.participant:id,callsign',
        ]);
        $this->repairMeasurements($collection);
    }

    /** @param Collection<int, Proposition> $propositions */
    private function repairMeasurements(Collection $propositions): void
    {
        $parents = $propositions
            ->flatMap(fn (Proposition $proposition) => $proposition->parents)
            ->unique(fn (Proposition $proposition): string => $proposition->getKey())
            ->values();

        $this->measurements->repairMissing($parents);
        $this->measurements->repairMissing($propositions);
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
            ? $proposition->effectiveAttestations->first(
                fn ($item): bool => $item->phase->cycle->participant_id
                    === $viewer->getKey()
            )
            : null;
        $fromMeasurement = $parent?->relationLoaded('measurement')
            ? $parent->measurement
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
            'impact' => $proposition->tier !== 'expressive'
                || $parent === null
                ? null
                : [
                    'from' => [
                        'correspondence' => $fromMeasurement?->correspondence,
                        'chirality' => $fromMeasurement?->chirality,
                        'closure' => $fromMeasurement?->closure,
                    ],
                    'to' => [
                        'correspondence' => $measurement?->correspondence,
                        'chirality' => $measurement?->chirality,
                        'closure' => $measurement?->closure,
                    ],
                ],
        ];
    }
}
