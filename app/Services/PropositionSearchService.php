<?php

namespace App\Services;

use App\Domain\Evidence\PropositionCollection;
use App\Models\Phase;
use App\Models\Proposition;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Deliberate historical memory: recorded backward lineage only, never discovery authority. */
final readonly class PropositionSearchService
{
    public function __construct(
        private BackwardRecoveryLineage $lineage,
        private InputPlanService $plans,
        private ParticipantContextSerializer $serializer,
    ) {}

    public function search(Phase $phase, Proposition $head, array $input): PropositionCollection
    {
        $input = PropositionCollection::normalizeInput($input);
        $phase->loadMissing('cycle.participant');
        $head->loadMissing('phase.cycle.participant', 'phase.cycle.contextHorizon');
        if ($phase->cycle->participant->substrate_id !== $head->phase->cycle->participant->substrate_id) {
            throw new DomainException('Proposition Search cannot cross substrate boundaries.');
        }

        $distances = $this->lineage->distances(
            $head,
            $phase->cycle->participant,
            fn (string $id, string $horizonId): array => DB::table('supersessions')
                ->where('target_proposition_id', $id)
                ->pluck('source_proposition_id')->all(),
        );
        unset($distances[$head->id]);

        $domain = $head->phase->cycle->contextHorizon->domain->value;
        $visible = $this->serializer->visiblePropositionIds(
            $this->plans->forPhase($phase->cycle->participant, $phase),
        )->flip();
        $nodes = Proposition::query()->whereKey(array_keys($distances))
            ->with('phase.cycle.contextHorizon')->get()->keyBy('id');
        $query = $input['query'] ?? null;
        $results = collect(array_keys($distances))->map(function (string $id) use (
            $nodes, $visible, $domain, $query, $distances,
        ): ?array {
            $node = $nodes->get($id);
            if ($node === null || $node->phase->cycle->contextHorizon->domain->value !== $domain
                || $visible->has($id)
                || ($query !== null && mb_stripos($node->statement, $query) === false)) {
                return null;
            }

            return [
                'proposition_id' => $node->id,
                'statement' => $node->statement,
                'register' => $node->register,
                'tier' => $node->tier,
                'domain' => $domain,
                'lineage_distance' => $distances[$id],
            ];
        })->filter()->take($input['limit'])->values()->all();

        return new PropositionCollection(
            query: $query,
            limit: $input['limit'],
            headPropositionId: $head->id,
            domain: $domain,
            results: $results,
        );
    }
}
