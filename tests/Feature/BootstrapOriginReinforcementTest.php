<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\LedgerService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\Observables\ReinforcementService;
use App\Services\OperationalInstrumentService;
use App\Services\ReceiptContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapOriginReinforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_loaded_reinforcement_does_not_rewrite_bootstrap_committal_closure(): void
    {
        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => 'bootstrap-origin-reinforcement',
            'title' => 'Bootstrap Origin Reinforcement',
            'visibility' => 'private',
            'configuration' => [],
        ])->save();

        $participant = new Participant;
        $participant->forceFill([
            'substrate_id' => $substrate->id,
            'callsign' => 'Origin observer',
        ])->save();

        $originPhase = $this->phase($participant, 1);
        $origin = new Proposition;
        $origin->forceFill([
            'phase_id' => $originPhase->id,
            'register' => 'canon',
            'tier' => 'committal',
            'statement' => 'Origin.',
        ])->save();
        app(LedgerService::class)->record($origin);

        $local = app(LocalMeasurementService::class);
        $this->assertSame(-1.0, $local->calculate($origin)->closure()->average());

        $readPhase = $this->phase($participant, 2, $origin);
        $receipt = app(OperationalInstrumentService::class)->execute(
            $originPhase,
            $origin,
            ['kind' => 'hyperlink', 'reference' => 'https://example.test/page'], bootstrap: true,
        );
        $this->assertNotNull($receipt->signature);

        app(ReceiptContextService::class)->read(
            $participant,
            $readPhase,
            $receipt->id,
        );

        $this->assertSame(1, app(ReinforcementService::class)->count($origin));
        $this->assertSame(-1.0, $local->calculate($origin)->closure()->average());
    }

    private function phase(
        Participant $participant,
        int $sequence,
        ?Proposition $gate = null,
    ): Phase {
        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $participant->id,
            'sequence' => $sequence,
        ])->save();

        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->id,
            'kind' => PhaseKind::Order,
            'write_register' => 'canon',
            'gate_proposition_id' => $gate?->id,
            'started_at' => now(),
        ])->save();

        return $phase;
    }
}
