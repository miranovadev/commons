<?php

namespace App\Services;

use App\Enums\PhaseKind;
use App\Models\Proposition;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Read persisted Bias provenance for geometry; never authorize an option or a Receipt. */
final class RecordedBiasSourceService
{
    /** Recognize an already recorded action, without reapplying today's population direction. */
    public function recordedSourceFor(Proposition $target, Collection $edges, ?ParticipationContext $context = null): ?Proposition
    {
        $phase = $target->phase;
        if ($target->tier !== 'expressive' || $phase->kind !== PhaseKind::Bias || $edges->count() !== 1) {
            return null;
        }
        $edge = $edges->first();
        if ($edge->type !== 'reclassify' || $edge->phase_id !== $phase->id
            || $edge->target_proposition_id !== $target->id || $edge->source_proposition_id === $phase->gate_proposition_id) {
            return null;
        }
        $records = $context?->classifications ?? DB::table('reclassification_results')->where('target_proposition_id', $target->id)->get();
        if (! $records->contains(fn ($record) => $record->source_proposition_id === $edge->source_proposition_id
            && $record->target_proposition_id === $target->id && $record->register === $target->register)) {
            return null;
        }
        $source = $context !== null ? $context->propositions->get($edge->source_proposition_id)
            : Proposition::find($edge->source_proposition_id);

        return $source?->tier === 'expressive' && $source->phase->kind === PhaseKind::Mediate
            && $source->phase->cycle->participant->substrate_id === $phase->cycle->participant->substrate_id ? $source : null;
    }
}
