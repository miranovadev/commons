<?php

namespace App\Services;

use App\Models\Proposition;
use App\Models\Tragedy;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Facades\DB;

/** Owner-initialized catalogue projection; archived relocated rows remain readable, never escape authority. */
final class TragedyReceiptScopeService
{
    public function seeds(ParticipationContext $context): array
    {
        return $context->remember('tragedy-receipt-scopes', fn (): array => DB::table('tragedy_receipt_scopes as scope')
            ->join('tragedies as tragedy', 'tragedy.id', '=', 'scope.tragedy_id')
            ->join('context_horizons as horizon', 'horizon.id', '=', 'tragedy.context_horizon_id')
            ->where('horizon.substrate_id', $context->substrate->id)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('receipt_consumptions as consumed')
                ->whereColumn('consumed.receipt_id', 'tragedy.receipt_id'))
            ->pluck('scope.current_committal_id', 'tragedy.receipt_id')->all());
    }

    /** Called in the Tragedy creation transaction; ordinary Receipts have no projection. */
    public function initialize(Tragedy $tragedy): void
    {
        $terminal = Proposition::findOrFail($tragedy->terminal_proposition_id);
        DB::table('tragedy_receipt_scopes')->insertOrIgnore([
            'tragedy_id' => $tragedy->id,
            'current_committal_id' => app(CommitRegionService::class)->seedFor($terminal)?->id,
        ]);
    }
}
