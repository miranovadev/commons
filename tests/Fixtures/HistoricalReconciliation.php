<?php

namespace Tests\Fixtures;

use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Supersession;
use App\Services\LedgerService;

/** Materialise a pre-experiment birth whose operands could be used more than once. */
final class HistoricalReconciliation
{
    public static function record(Phase $phase, Proposition $gate, Proposition $other, string $statement): Proposition
    {
        $legacy = (new Proposition)->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register,
            'tier' => 'expressive', 'statement' => $statement]);
        $legacy->save();
        app(LedgerService::class)->record($legacy, $gate);
        foreach ([$gate, $other] as $source) {
            $edge = (new Supersession)->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $source->id,
                'target_proposition_id' => $legacy->id, 'type' => 'reconcile']);
            $edge->save();
            app(LedgerService::class)->record($edge);
        }
        PropositionBirth::complete($legacy);

        return $legacy;
    }
}
