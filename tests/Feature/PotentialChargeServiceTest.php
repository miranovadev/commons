<?php

namespace Tests\Feature;

use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\LedgerService;
use App\Services\PotentialChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PotentialChargeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prefers_the_committal_state_with_the_greatest_tension_reducing_continuation(): void
    {
        [$substrate, $phase] = $this->substratePhase();
        $first = $this->committal($substrate, $phase, 'First', 0.8);
        $this->committal($substrate, $phase, 'Second', 0.4);
        $this->continuation($phase, $first, 'A lower-tension continuation', 0.1);

        $selection = app(PotentialChargeService::class)
            ->selectFor($substrate);

        $this->assertTrue($selection['proposition']->is($first));
        $this->assertSame(0.7, $selection['potential_charge']);
        $this->assertSame(0.2, $selection['charge']);
    }

    public function test_it_falls_back_to_highest_charge_when_potential_is_zero(): void
    {
        [$substrate, $phase] = $this->substratePhase();
        $this->committal($substrate, $phase, 'High tension', -0.9);
        $higherCharge = $this->committal(
            $substrate,
            $phase,
            'Lower tension',
            -0.25,
        );

        $selection = app(PotentialChargeService::class)
            ->selectFor($substrate);

        $this->assertTrue($selection['proposition']->is($higherCharge));
        $this->assertSame(0.0, $selection['potential_charge']);
        $this->assertSame(0.75, $selection['charge']);
    }

    /** @return array{Substrate, Phase} */
    private function substratePhase(): array
    {
        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => 'potential-'.str()->lower(str()->random(8)),
            'title' => 'Potential selection',
            'visibility' => 'public',
            'configuration' => [],
        ]);
        $substrate->save();
        $participant = new Participant;
        $participant->forceFill([
            'substrate_id' => $substrate->getKey(),
            'callsign' => 'potential-observer',
        ]);
        $participant->save();
        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $participant->getKey(),
            'sequence' => 1,
        ]);
        $cycle->save();
        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->getKey(),
            'kind' => PhaseKind::Order,
            'write_register' => 'canon',
            'started_at' => now(),
        ]);
        $phase->save();

        return [$substrate, $phase];
    }

    private function committal(
        Substrate $substrate,
        Phase $phase,
        string $statement,
        float $tension,
    ): Proposition {
        $proposition = $this->proposition(
            $phase,
            $statement,
            'committal',
        );
        $entry = app(LedgerService::class)->record($proposition);
        $snapshot = new CommitmentSnapshot;
        $snapshot->forceFill([
            'proposition_id' => $proposition->getKey(),
            'substrate_id' => $substrate->getKey(),
            'ledger_entry_id' => $entry->getKey(),
            'correspondence' => $tension,
            'chirality' => $tension,
            'closure' => $tension,
            'prominence' => ($tension + 1) / 2,
            'turbulence' => (1 - $tension) / 2,
            'tension' => $tension,
            'direction_before' => ContextDomain::Emergence,
            'direction_after' => ContextDomain::Emergence,
            'calculator_version' => 'potential-test-v1',
            'evaluated_at' => now(),
        ]);
        $snapshot->save();

        return $proposition;
    }

    private function continuation(
        Phase $phase,
        Proposition $source,
        string $statement,
        float $tension,
    ): void {
        $target = $this->proposition(
            $phase,
            $statement,
            'expressive',
        );
        app(LedgerService::class)->record($target);
        $measurement = new PropositionMeasurement;
        $measurement->forceFill([
            'proposition_id' => $target->getKey(),
            'correspondence' => $tension,
            'chirality' => $tension,
            'closure' => $tension,
            'calculated_at' => now(),
        ]);
        $measurement->save();
        $supersession = new Supersession;
        $supersession->forceFill([
            'phase_id' => $phase->getKey(),
            'source_proposition_id' => $source->getKey(),
            'target_proposition_id' => $target->getKey(),
            'type' => 'variant',
        ]);
        $supersession->save();
    }

    private function proposition(
        Phase $phase,
        string $statement,
        string $tier,
    ): Proposition {
        $proposition = new Proposition;
        $proposition->forceFill([
            'phase_id' => $phase->getKey(),
            'register' => 'canon',
            'tier' => $tier,
            'statement' => $statement,
        ]);
        $proposition->save();

        return $proposition;
    }
}
