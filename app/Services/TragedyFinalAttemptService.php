<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Tragedy;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Facades\DB;

/** One shared final exploration attempt; receipt visibility never resets spentness. */
final class TragedyFinalAttemptService
{
    public function available(Phase $phase): bool
    {
        return $this->tragedyFor($phase) !== null;
    }

    private function tragedyFor(Phase $phase): ?Tragedy
    {
        if (! in_array($phase->kind, [PhaseKind::Distinguish, PhaseKind::Disperse], true)
            || app(ExhaustionService::class)->unavailable($phase->gate)) {
            return null;
        }

        $tragedy = Tragedy::where('governing_committal_id', app(ExhaustionService::class)->regionFor($phase)?->id)
            ->whereNotNull('governing_committal_id')
            ->whereNotIn('id', DB::table('tragedy_final_attempts')->select('tragedy_id'))
            ->whereNotIn('receipt_id', DB::table('receipt_consumptions')->select('receipt_id'))->first();
        if ($tragedy === null) {
            return null;
        }
        $context = new ParticipationContext($phase);
        $seed = app(ReceiptRegionService::class)->current($phase, $context);

        return $seed !== null && (app(TragedyReceiptScopeService::class)->seeds($context)[$tragedy->receipt_id] ?? null) === $seed
            ? $tragedy : null;
    }

    /** Called with the successful Variant under the same Substrate transaction. */
    public function record(Phase $phase, Proposition $variant): void
    {
        if (DB::transactionLevel() === 0 || $variant->phase_id !== $phase->id) {
            throw new \DomainException('Final attempts belong to the Variant transaction.');
        }
        $tragedy = $this->tragedyFor($phase);
        if ($tragedy === null) {
            throw new \DomainException('The shared final attempt has already been exercised.');
        }
        DB::table('tragedy_final_attempts')->insert([
            'tragedy_id' => $tragedy->id, 'phase_id' => $phase->id,
            'proposition_id' => $variant->id, 'started_at' => now(),
        ]);
    }
}
