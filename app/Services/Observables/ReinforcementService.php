<?php

namespace App\Services\Observables;

use App\Models\Proposition;
use App\Models\Reinforcement;
use App\Models\Substrate;
use App\Services\Participation\ParticipationContext;
use App\Services\PropositionStateService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class ReinforcementService
{
    public function __construct(private readonly PropositionStateService $state) {}

    /** Count state formation, never Receipt volume or attenuated evidence. */
    public function count(Proposition $proposition, ?ParticipationContext $context = null): int
    {
        $ids = $this->state->sourceIds($proposition, $context);
        $rows = $context !== null ? $context->reinforcements->whereIn('proposition_id', $ids)
            : Reinforcement::whereIn('proposition_id', $ids)->get();

        return $rows->unique('participant_id')->count();
    }

    /** Preserve existing structural feature weighting, now by counted participants. */
    public function weights(Collection $contributions, Substrate $substrate, ?ParticipationContext $context = null): Collection
    {
        return $contributions->mapWithKeys(fn (Model $item) => [
            $item->getKey() => 1 + ($item instanceof Proposition ? $this->count($item, $context) : 0) * $this->receiptIncrement($substrate),
        ]);
    }

    /** Existing contribution/receipt ratio remains the scale of one persistence observation. */
    public function receiptIncrement(Substrate $substrate): float
    {
        $ratio = (float) data_get($substrate->configuration, 'observables.contribution_receipt_ratio', 3);

        return 1 / (is_finite($ratio) && $ratio > 0 ? $ratio : 3);
    }
}
