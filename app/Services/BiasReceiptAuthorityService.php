<?php

namespace App\Services;

use App\Models\Phase;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;

/** Source authority precedes Receipt selection. Foreign ownership alone is not escape authority. */
final class BiasReceiptAuthorityService
{
    public function authority(Phase $phase, Proposition $source, ParticipationContext $context): ?string
    {
        if ($source->id === $phase->gate_proposition_id) {
            return app(BiasGateService::class)->forPhase($phase, $context)->contains('id', $source->id) ? 'ordinary' : null;
        }
        if (app(MediatedIncomingBiasService::class)->forPhase($phase, $context)->contains('id', $source->id)) {
            return 'ordinary';
        }
        if (! app(HomologousBiasService::class)->forPhase($phase, $context)->contains('id', $source->id)) {
            return null;
        }

        // Another expressive path in this same basin does not cross a Commit boundary.
        return ($context->seedIds()[$source->id] ?? null) === $phase->cycle->entry_committal_id
            ? 'ordinary' : 'tragedy';
    }

    public function receiptsFor(Phase $phase, Proposition $source, ?ParticipationContext $context = null): Collection
    {
        $context ??= new ParticipationContext($phase);
        $authority = $this->authority($phase, $source, $context);
        if ($authority === null) {
            return collect();
        }
        $receipts = app(HorizonReceiptService::class);

        return $authority === 'ordinary' ? $receipts->ordinaryAvailable($phase, $context)
            : collect([$receipts->tragedyWarrant($phase, $context)])->filter()->values();
    }
}
