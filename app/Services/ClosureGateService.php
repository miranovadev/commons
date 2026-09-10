<?php

namespace App\Services;

use App\Domain\Observables\ClosureValues;
use App\Enums\ObservableDirection;
use App\Models\CommitmentSnapshot;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Observables\LineageMeasurementService;
use Illuminate\Support\Facades\DB;

class ClosureGateService
{
    public function __construct(
        private readonly LineageMeasurementService $measurements,
    ) {}

    /** @return array<string, mixed> */
    public function preview(Proposition $gate, Phase $phase): array
    {
        return $this->evaluate($this->measurements->prospectiveCommit($gate, $phase)->closure(), $gate);
    }

    /** @return array<string, mixed> */
    public function forCommitment(Proposition $target): array
    {
        return $this->evaluate($this->measurements->calculate($target)->closure(), $target);
    }

    /** Most recent evaluated ancestor, including all parents of a reconciliation. */
    public function historyFor(Proposition $position): ?CommitmentSnapshot
    {
        $ancestors = DB::select(<<<'SQL'
            WITH RECURSIVE ancestors (id) AS (
                SELECT id FROM propositions WHERE id = ?
                UNION
                SELECT s.source_proposition_id FROM supersessions s
                INNER JOIN ancestors a ON a.id = s.target_proposition_id
            ) SELECT id FROM ancestors
            SQL, [$position->getKey()]);

        return CommitmentSnapshot::query()
            ->where('commitment_snapshots.substrate_id', $position->phase->cycle->participant->substrate_id)
            ->whereIn('proposition_id', collect($ancestors)->pluck('id'))
            ->whereNotNull('depth_direction')->whereNotNull('cadence_direction')
            ->join('substrate_ledger_entries as entry', 'entry.id', '=', 'commitment_snapshots.ledger_entry_id')
            ->orderByDesc('entry.sequence')->select('commitment_snapshots.*')->first();
    }

    /** @return array<string, mixed> */
    private function evaluate(ClosureValues $closure, Proposition $position): array
    {
        $history = $this->historyFor($position);
        return [
            ...$closure->resolve(
                $history?->depth_direction ?? ObservableDirection::Negative,
                $history?->cadence_direction ?? ObservableDirection::Negative,
            ),
            'history_proposition_id' => $history?->proposition_id,
            'history_source' => $history === null ? 'negative-default' : 'committed-ancestor',
        ];
    }
}
