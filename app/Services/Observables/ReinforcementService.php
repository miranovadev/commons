<?php

namespace App\Services\Observables;

use App\Models\Receipt;
use App\Models\Substrate;
use App\Models\Proposition;
use App\Services\PropositionStateService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class ReinforcementService
{
    public function __construct(private readonly PropositionStateService $state) {}

    /**
     * @param  Collection<int, Model>  $contributions
     * @return Collection<string, float>
     */
    public function weights(
        Collection $contributions,
        Substrate $substrate,
    ): Collection {
        if ($contributions->isEmpty()) {
            return collect();
        }

        $first = $contributions->first();
        if ($first instanceof Proposition) {
            $sources = $contributions->mapWithKeys(fn (Proposition $item) => [
                $item->id => $this->state->sourceIds($item),
            ]);
        }
        $counts = Receipt::query()
            ->where('receiptable_type', $first->getMorphClass())
            ->whereIn('receiptable_id', isset($sources) ? $sources->flatten()->unique() : $contributions->pluck('id'))
            ->where('type', '!=', 'citation')
            ->whereNotNull('signature')
            ->with('instrumentExecution')->get();
        $ratio = (float) data_get(
            $substrate->configuration,
            'observables.contribution_receipt_ratio',
            3,
        );
        $ratio = $ratio > 0 ? $ratio : 3;

        return $contributions->mapWithKeys(
            fn (Model $contribution) => [
                (string) $contribution->getKey() => 1 + (
                    $counts->filter(fn (Receipt $receipt) =>
                        $receipt->receiptable_id === $contribution->getKey()
                        || (isset($sources) && $sources[$contribution->getKey()]->contains($receipt->receiptable_id)
                            && $receipt->instrumentExecution?->status === 'completed')
                    )->count() / $ratio
                ),
            ],
        );
    }
}
