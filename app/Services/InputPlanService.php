<?php

namespace App\Services;

use App\Models\Participant;
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
    ) {}

    /**
     * @return array<string, mixed>
     */
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
        $gateOptions = $this->options->gateOptionsFor($phase);

        return [
            'phase' => [
                'id' => $phase->getKey(),
                'kind' => $plan['name'],
                'description' => $plan['description'],
                'write_register' => $phase->write_register,
                'started_at' => $phase->started_at,
            ],
            'gate' => $this->proposition($gate),
            'lineage' => $this->propositions($lineage),
            'write_options' => $this->propositions($writeOptions),
            'gate_options' => $this->propositions($gateOptions),
            'schema' => $this->schemas->forParticipant(
                $participant,
                $writeOptions->modelKeys(),
                $gateOptions->modelKeys(),
            ),
        ];
    }

    /**
     * @param  Collection<int, Proposition>  $propositions
     * @return list<array<string, mixed>>
     */
    private function propositions(Collection $propositions): array
    {
        return $propositions
            ->map(fn (Proposition $proposition): array => $this->proposition(
                $proposition
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function proposition(Proposition $proposition): array
    {
        return $proposition->only([
            'id',
            'register',
            'tier',
            'statement',
            'created_at',
        ]);
    }
}
