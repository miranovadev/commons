<?php

namespace App\Services;

use App\Models\Participant;

final readonly class InputPlanService
{
    public function __construct(
        private PhaseService $phases,
        private GateService $gates,
        private LineageService $lineage,
        private InputSchemaService $schemas,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forParticipant(Participant $participant): array
    {
        $phase = $this->phases->currentFor($participant);
        $gate = $this->gates->forPhase($phase);
        $lineage = $this->lineage->endingAt(
            $gate,
            $participant->max_read_count,
            $participant->max_token_count,
        );

        return [
            'phase' => [
                'id' => $phase->getKey(),
                'position' => $phase->position->value,
                'started_at' => $phase->started_at,
            ],
            'gate' => $gate->only([
                'id',
                'register',
                'tier',
                'statement',
                'created_at',
            ]),
            'lineage' => $lineage
                ->map
                ->only([
                    'id',
                    'register',
                    'tier',
                    'statement',
                    'created_at',
                ])
                ->values()
                ->all(),
            'schema' => $this->schemas->forParticipant($participant),
        ];
    }
}
