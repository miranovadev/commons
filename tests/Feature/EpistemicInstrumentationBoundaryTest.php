<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Services\HorizonReceiptService;
use App\Services\OperationalInstrumentService;
use App\Services\PropositionOptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

/** Diagnostic of the unresolved Stage 2A/Disperse Attestation boundary, not a desired new rule. */
class EpistemicInstrumentationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_persist_evidence_cannot_satisfy_existing_exact_disperse_write_instrument_requirement(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $persistGate = $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
        $f->phase->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $persistGate->id, 'write_register' => 'frontier'])->save();
        app(OperationalInstrumentService::class)->execute($f->phase, $persistGate,
            ['kind' => 'citation', 'reference' => $f->seed->id]);
        $disperseGate = $f->node($persistGate, 'canon', position: PhaseKind::Constrain);
        $write = $f->node($disperseGate, 'activity', position: PhaseKind::Disperse);
        $phase = $f->phase->replicate();
        $phase->forceFill(['kind' => PhaseKind::Disperse, 'gate_proposition_id' => $disperseGate->id, 'write_register' => 'activity'])->save();
        $phase = $phase->fresh();
        $options = app(PropositionOptionService::class);
        $this->assertNotEmpty(app(HorizonReceiptService::class)->ordinaryAvailable($phase));
        $this->assertContains($write->id, $options->writeOptionsFor($phase)->pluck('id')->all());
        $this->assertNotContains($write->id, $options->attestationOptionsFor($phase)->pluck('id')->all());
        $this->assertEmpty($options->instrumentOptionsFor($phase));
        $this->assertNotContains('instrument.execute', config('phases.4.actions'));
        $this->assertEmpty(app(HorizonReceiptService::class)->instrumentedIds($phase, collect([$write->id])));
    }
}
