<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Tragedy;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Facades\DB;

/** Mutable basin projection only. Receipt and Tragedy provenance remain immutable. */
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

    /** CycleEntry owns the Substrate lock and has already selected this exact destination. */
    public function followRecovery(Phase $entry): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \DomainException('Tragedy scope moves with the recovery transaction.');
        }
        if ($entry->kind !== PhaseKind::Distinguish) {
            return;
        }
        $previous = Cycle::where('participant_id', $entry->cycle->participant_id)
            ->where('sequence', $entry->cycle->sequence - 1)->whereNotNull('rested_at')
            ->whereNotNull('completed_at')->first();
        $terminal = $previous?->phases()->whereNotNull('completed_at')->latest('kind')->first();
        if ($terminal?->gate === null || $entry->gate?->tier !== 'committal') {
            return; // Initial entry and ordinary completion/Commit never transport it.
        }
        $source = app(CommitRegionService::class)->seedFor($terminal->gate);
        if ($source === null || $source->id === $entry->gate_proposition_id) {
            return;
        }
        $scopes = DB::table('tragedy_receipt_scopes')->where('current_committal_id', $source->id)
            ->whereIn('tragedy_id', DB::table('tragedies')->whereNotIn('receipt_id',
                DB::table('receipt_consumptions')->select('receipt_id'))->select('id'));
        if (! (clone $scopes)->exists()) {
            return;
        }
        $path = app(BackwardRecoveryLineage::class)->distances($terminal->gate, $entry->cycle->participant,
            fn (string $id) => DB::table('supersessions')->where('target_proposition_id', $id)->pluck('source_proposition_id')->all());
        if (! isset($path[$entry->gate_proposition_id])) {
            return;
        }
        $scopes->update(['current_committal_id' => $entry->gate_proposition_id, 'recovery_phase_id' => $entry->id]);
    }
}
