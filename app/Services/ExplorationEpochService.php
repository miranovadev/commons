<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** The sole current-spentness predicate. Historical failure rows are never rewritten. */
final readonly class ExplorationEpochService
{
    public function __construct(private ExplorationPositionService $positions) {}

    public function current(array $position, ?ParticipationContext $context = null): int
    {
        if ($context !== null) {
            return (int) $this->epochs(collect([$position]), $context)->get($this->positions->key($position), 0);
        }

        return (int) DB::table('horizon_exploration_states')->where($position)->value('epoch');
    }

    public function forPhase(Phase $phase): int
    {
        return $this->current($this->positions->forPhase($phase));
    }

    public function forProposition(Proposition $node, ?ParticipationContext $context = null): int
    {
        $position = $this->positions->forProposition($node, $context);

        return $position === null ? 0 : $this->current($position, $context);
    }

    /** Called only by the successful Variant write, under the substrate lock. */
    public function advance(Phase $phase): void
    {
        if (DB::transactionLevel() === 0) {
            throw new DomainException('Epoch advancement belongs to the Variant transaction.');
        }
        $position = $this->positions->forPhase($phase);
        DB::table('horizon_exploration_states')->insertOrIgnore([...$position, 'epoch' => 0]);
        DB::table('horizon_exploration_states')->where($position)->increment('epoch');
        app(RealtimePublisher::class)->horizon($position['context_horizon_id'], $phase->cycle->participant->substrate_id,
            ['type' => 'exploration.epoch_advanced', 'structural_position' => $position['structural_position'],
                'structural_source_id' => $position['structural_source_id'], 'exploration_epoch' => $this->current($position)]);
    }

    /** Match the failed proposition's actual position and that position's current epoch. */
    public function currentFailures(string $horizonId, Collection $ids, ?ParticipationContext $context = null): Collection
    {
        return $this->currentFailuresForHorizons(collect([$horizonId]), $ids, $context);
    }

    /** Batch independent Horizons while retaining each position's own epoch identity. */
    public function currentFailuresForHorizons(Collection $horizonIds, Collection $ids, ?ParticipationContext $context = null): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $positions = $this->positions->forIds($ids, $context)->filter()
            ->whereIn('context_horizon_id', $horizonIds);
        if ($positions->isEmpty()) {
            return collect();
        }
        $epochs = $this->epochs($positions, $context);

        return DB::table('continuation_failures')->whereIn('context_horizon_id', $horizonIds)
            ->whereIn('proposition_id', $positions->keys())->whereIn('exploration_epoch', $epochs->values()->unique())->get()
            ->filter(function ($failure) use ($positions, $epochs): bool {
                $position = $positions->get($failure->proposition_id);

                return $position['context_horizon_id'] === $failure->context_horizon_id
                    && $position['structural_position'] === (int) $failure->structural_position
                    && (int) $failure->exploration_epoch === (int) $epochs->get($this->positions->key($position), 0);
            })->keyBy('proposition_id');
    }

    public function failuresFor(Phase $phase, Collection $ids, ?ParticipationContext $context = null): Collection
    {
        $horizon = $phase->cycle->context_horizon_id;

        return $context === null ? $this->currentFailures($horizon, $ids)
            : $context->remember('current-continuation-failures:'.$horizon, fn () => $this->currentFailures(
                $horizon, $context->propositions->keys(), $context,
            ))->only($ids->all());
    }

    public function spentNow(Proposition $node, Phase $phase, ?ParticipationContext $context = null): bool
    {
        return $this->failuresFor($phase, collect([$node->id]), $context)->has($node->id);
    }

    private function epochs(Collection $positions, ?ParticipationContext $context): Collection
    {
        if ($context !== null) {
            $cached = $context->remember('local-exploration-epochs', fn () => $this->epochs(
                $this->positions->forIds($context->propositions->keys(), $context)->filter(), null,
            ));
            $missing = $positions->reject(fn ($position) => $cached->has($this->positions->key($position)));

            return $missing->isEmpty() ? $cached : $cached->union($this->epochs($missing, null));
        }
        if ($positions->isEmpty()) {
            return collect();
        }

        return DB::table('horizon_exploration_states')
            ->whereIn('context_horizon_id', $positions->pluck('context_horizon_id')->unique())
            ->whereIn('structural_position', $positions->pluck('structural_position')->unique())
            ->whereIn('structural_source_id', $positions->pluck('structural_source_id')->unique())
            ->get()->mapWithKeys(fn ($row) => [$this->positions->key((array) $row) => (int) $row->epoch])
            ->union($positions->mapWithKeys(fn ($position) => [$this->positions->key($position) => 0]));
    }
}
