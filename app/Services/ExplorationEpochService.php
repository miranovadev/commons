<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Failure is historical; only a successful Variant changes its local epoch. */
final class ExplorationEpochService
{
    public function current(string $horizonId, PhaseKind $position, ?ParticipationContext $context = null): int
    {
        if ($context !== null) {
            return (int) $context->remember('exploration-epochs:'.$horizonId, fn () => DB::table('horizon_exploration_states')
                ->where('context_horizon_id', $horizonId)->pluck('epoch', 'structural_position'))->get($position->value, 0);
        }

        return (int) DB::table('horizon_exploration_states')->where('context_horizon_id', $horizonId)
            ->where('structural_position', $position->value)->value('epoch');
    }

    /** Called only by the successful Variant write, under the substrate lock. */
    public function advance(Phase $phase): void
    {
        if (DB::transactionLevel() === 0) {
            throw new DomainException('Epoch advancement belongs to the Variant transaction.');
        }
        $key = ['context_horizon_id' => $phase->cycle->context_horizon_id, 'structural_position' => $phase->kind->value];
        DB::table('horizon_exploration_states')->insertOrIgnore([...$key, 'epoch' => 0]);
        DB::table('horizon_exploration_states')->where($key)->increment('epoch');
        app(RealtimePublisher::class)->horizon($key['context_horizon_id'], $phase->cycle->participant->substrate_id,
            ['type' => 'exploration.epoch_advanced', 'structural_position' => $phase->kind->value,
                'exploration_epoch' => $this->current($key['context_horizon_id'], $phase->kind)]);
    }

    /** One indexed lookup for a bounded candidate set; old epochs never match. */
    public function currentFailures(string $horizonId, Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('continuation_failures as failure')
            ->leftJoin('horizon_exploration_states as exploration', fn ($join) => $join
                ->on('exploration.context_horizon_id', '=', 'failure.context_horizon_id')
                ->on('exploration.structural_position', '=', 'failure.structural_position'))
            ->where('failure.context_horizon_id', $horizonId)->whereIn('failure.proposition_id', $ids)
            ->whereRaw('failure.exploration_epoch = COALESCE(exploration.epoch, 0)')
            ->get(['failure.proposition_id', 'failure.structural_position', 'failure.exploration_epoch', 'failure.failed_at'])
            ->keyBy('proposition_id');
    }

    public function failuresFor(Phase $phase, Collection $ids, ?ParticipationContext $context = null): Collection
    {
        return $context === null ? $this->currentFailures($phase->cycle->context_horizon_id, $ids)
            : $context->remember('current-continuation-failures', fn () => $this->currentFailures(
                $phase->cycle->context_horizon_id, $context->propositions->keys(),
            ))->only($ids->all());
    }

    public function spentNow(Proposition $node, Phase $phase, ?ParticipationContext $context = null): bool
    {
        if ($node->tier !== 'expressive' || $node->phase->cycle->context_horizon_id !== $phase->cycle->context_horizon_id) {
            return false;
        }
        $failure = $this->failuresFor($phase, collect([$node->id]), $context)->get($node->id);

        return $failure !== null && (int) $failure->structural_position === $node->phase->kind->value;
    }
}
