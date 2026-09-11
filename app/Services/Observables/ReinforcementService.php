<?php

namespace App\Services\Observables;

use App\Services\Participation\ParticipationContext;
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
        ?ParticipationContext $context = null,
    ): Collection {
        if ($contributions->isEmpty()) {
            return collect();
        }

        $first = $contributions->first();
        $sources = $contributions->mapWithKeys(fn (Model $item) => [
            $item->getKey() => $item instanceof Proposition
                ? $this->state->sourceIds($item, $context) : collect([$item->getKey()]),
        ]);
        $receipts = $context !== null
            ? $context->reinforcementReceipts($first->getMorphClass(), $sources->flatten()->unique())
            : Receipt::query()
            ->where('receiptable_type', $first->getMorphClass())
            ->whereIn('receiptable_id', $sources->flatten()->unique())
            ->where('type', '!=', 'citation')
            ->whereNotNull('signature')
            ->with('instrumentExecution')->get();
        return $contributions->mapWithKeys(
            fn (Model $contribution) => [
                (string) $contribution->getKey() => 1 + (
                    $receipts->filter(fn (Receipt $receipt) =>
                        $receipt->receiptable_id === $contribution->getKey()
                        || ($sources[$contribution->getKey()]->contains($receipt->receiptable_id)
                            && $receipt->instrumentExecution?->status === 'completed')
                    )->count() * $this->receiptIncrement($substrate)
                ),
            ],
        );
    }
    /** Shared receipt increment for current calculations and ledger-prefix replay. */
    public function receiptIncrement(Substrate $substrate): float
    {
        $ratio = (float) data_get($substrate->configuration, 'observables.contribution_receipt_ratio', 3);
        return 1 / ($ratio > 0 ? $ratio : 3);
    }
}
