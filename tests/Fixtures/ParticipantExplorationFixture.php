<?php

namespace Tests\Fixtures;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\EvidenceExecutionService;
use App\Services\LedgerService;
use App\Services\PhaseProgressionService;
use App\Services\PhaseService;

trait ParticipantExplorationFixture
{
    use ExhaustionFixture;

    /** One actor's actual Phase arrivals through a tested expressive excursion. */
    private function personalJourney(string $headDirection = 'support', ?int $omitJudgment = null, ?int $omitAcquisition = null): array
    {
        $this->resolveExpressiveFieldFixture(.8);
        $this->initializeExhaustionFixture();
        $phase = $this->phase($this->origin, PhaseKind::Distinguish);
        $nodes = [];
        for ($kind = 1; $kind <= 4; $kind++) {
            if (in_array($kind, [1, 4], true) && $kind !== $omitAcquisition) {
                $this->instrumentGateFixture($phase);
            }
            $node = $this->node($phase, $phase->gate);
            $nodes[] = $node;
            if ($kind !== $omitJudgment) {
                $this->personalVote($phase, $node, $kind === 4 ? $headDirection : 'support');
            }
            $phase = app(PhaseProgressionService::class)->advance($phase, $node);
            if ($kind === 1 && $omitAcquisition !== 2) {
                $instruments = app(EvidenceExecutionService::class);
                $instruments->complete($instruments->start($phase, $node, 'citation', $this->origin->id), $node);
            }
        }
        $this->measure($nodes[3], 0);

        return [...$nodes, $phase];
    }

    private function personalVote(Phase $phase, Proposition $node, string $direction = 'refute'): Attestation
    {
        $previous = Attestation::effective()->where('proposition_id', $node->id)
            ->whereHas('phase.cycle', fn ($query) => $query->where('participant_id', $phase->cycle->participant_id))->first();
        $vote = (new Attestation)->forceFill(['phase_id' => $phase->id, 'proposition_id' => $node->id,
            'direction' => $direction, 'supersedes_attestation_id' => $previous?->id]);
        $vote->save();
        app(LedgerService::class)->record($vote);

        return $vote;
    }

    private function returnAfterRest(Phase $terminal): Phase
    {
        $this->rest($terminal);

        return app(PhaseService::class)->currentFor($terminal->cycle->participant->fresh());
    }
}
