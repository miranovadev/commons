<?php

namespace App\Services\Observables;

use App\Models\Receipt;
use App\Models\Substrate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class ReinforcementService
{
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
        $counts = Receipt::query()
            ->where('receiptable_type', $first->getMorphClass())
            ->whereIn('receiptable_id', $contributions->pluck('id'))
            ->where('type', '!=', 'citation')
            ->whereNotNull('signature')
            ->selectRaw('receiptable_id, count(*) as aggregate')
            ->groupBy('receiptable_id')
            ->pluck('aggregate', 'receiptable_id');
        $ratio = (float) data_get(
            $substrate->configuration,
            'observables.contribution_receipt_ratio',
            3,
        );
        $ratio = $ratio > 0 ? $ratio : 3;

        return $contributions->mapWithKeys(
            fn (Model $contribution) => [
                (string) $contribution->getKey() => 1 + (
                    (int) $counts->get($contribution->getKey(), 0) / $ratio
                ),
            ],
        );
    }
}
