<?php

namespace Tests\Fixtures;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Proposition;
use App\Models\Tragedy;
use App\Services\ContributionDispatcher;
use App\Services\LedgerService;
use App\Services\RegionalExhaustionMembership;
use Illuminate\Support\Facades\DB;

/** Controlled regional lifecycle components; committed coordinates are not end-to-end Commit evidence. */
trait HorizonProofFixture
{
    use ExhaustionFixture;

    private array $continuingHeads = [];

    protected function firstFailure(): array
    {
        $root = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $opening = $this->commitFrom($root, boundary: true);
        $head = $this->node($this->phase($opening, PhaseKind::Constrain), $opening);
        $this->explore($head);
        $phase = $this->phase($head);
        $this->measure($root, 1);
        $this->measure($head, 1);
        // Resolution requires a real distinct continuation already present at
        // the first event. Do not create a prohibited Variant afterwards.
        $this->continuingHeads[$opening->id] = $this->node($this->phase($opening, PhaseKind::Constrain), $opening);
        $this->boundaryTragedyFixture($head);
        $this->rest($phase);

        return [$opening, $phase->cycle->contextHorizon, Tragedy::sole(), $this->continuingHeads[$opening->id]];
    }

    protected function exercise($opening, $tragedy): array
    {
        $target = $this->continuingHeads[$opening->id];
        $phase = $this->phase($target, PhaseKind::Persist);
        $this->resolveExpressiveFieldFixture(1);
        app(ContributionDispatcher::class)->dispatch($phase->cycle->participant, $phase,
            ['action' => 'instrument.execute', 'proposition_id' => $target->id,
                'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/terminal/'.$target->id]]);
        $terminal = $this->phase($target);
        $this->publishPotentialFixture($this->substrate);

        return [$terminal, $target];
    }

    /** Archive-only construction of the retired exhaustion proof for migration/audit coverage. */
    protected function archiveExhaustion($phase, $tragedy): void
    {
        $proof = DB::table('horizon_failure_witnesses')->where('phase_id', $phase->id)->sole();
        DB::table('commit_region_exhaustions')->insert([
            'governing_committal_id' => $tragedy->governing_committal_id,
            'context_horizon_id' => $tragedy->context_horizon_id, 'tragedy_id' => $tragedy->id,
            'receipt_id' => $tragedy->receipt_id, 'phase_id' => $phase->id,
            'terminal_proposition_id' => $phase->gate_proposition_id,
            'observed_field_version' => $proof->observed_field_version, 'exhausted_at' => now(),
        ]);
        app(RegionalExhaustionMembership::class)->publish($tragedy->governing_committal_id,
            app(RegionalExhaustionMembership::class)->capture($tragedy->context_horizon_id, $tragedy->governing_committal_id));
    }

    protected function legacyExercise($opening, $tragedy): array
    {
        $source = $this->node($this->phase($opening, PhaseKind::Persist), $opening);
        $vote = (new Attestation)->forceFill(['phase_id' => $source->phase_id, 'proposition_id' => $source->id, 'direction' => 'support']);
        $vote->save();
        app(LedgerService::class)->record($vote);
        $phase = $this->phase($source, PhaseKind::Constrain);
        $result = $this->legacyTragedyClassificationFixture($phase, $source, $tragedy->receipt_id);
        $this->explore($result);
        $terminal = $this->phase($result);
        // Spent history stays in the measured field. This fixture asserts a
        // genuinely zero-Potential final attempt, including the original probe.
        foreach (Proposition::whereHas('phase.cycle', fn ($query) => $query
            ->where('context_horizon_id', $terminal->cycle->context_horizon_id))->where('tier', 'expressive')->get() as $node) {
            $this->measure($node, 1);
        }
        $this->measure($source, 1);
        $this->measure($result, 1);

        return [$terminal, $result];
    }
}
