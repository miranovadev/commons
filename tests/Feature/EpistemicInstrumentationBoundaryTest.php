<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Participant;
use App\Services\HorizonReceiptService;
use App\Services\OperationalInstrumentService;
use App\Services\PropositionOptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

/** Shared evidence authorizes Attestation independently of exact-Write Instrument history. */
class EpistemicInstrumentationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public static function phases(): array
    {
        return [[PhaseKind::Distinguish, true], [PhaseKind::Disperse, true], [PhaseKind::Distinguish, false], [PhaseKind::Disperse, false]];
    }

    #[DataProvider('phases')]
    public function test_shared_persist_evidence_allows_attesting_an_uninstrumented_write(PhaseKind $kind, bool $withEvidence): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $persistGate = $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
        $f->phase->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $persistGate->id, 'write_register' => 'frontier'])->save();
        if ($withEvidence) {
            app(OperationalInstrumentService::class)->execute($f->phase, $persistGate,
                ['kind' => 'citation', 'reference' => $f->seed->id]);
        }
        $disperseGate = $kind === PhaseKind::Distinguish ? $f->seed : $f->node($persistGate, 'canon', position: PhaseKind::Constrain);
        $write = $f->node($disperseGate, 'activity', position: $kind);
        $phase = $f->phase->replicate();
        $phase->forceFill(['kind' => $kind, 'gate_proposition_id' => $disperseGate->id, 'write_register' => 'activity'])->save();
        $phase = $phase->fresh();
        $options = app(PropositionOptionService::class);
        $this->assertSame($withEvidence, app(HorizonReceiptService::class)->ordinaryAvailable($phase)->isNotEmpty());
        $this->assertContains($write->id, $options->writeOptionsFor($phase)->pluck('id')->all());
        $this->assertSame($withEvidence, $options->attestationOptionsFor($phase)->contains('id', $write->id));
        $this->assertEmpty($options->instrumentOptionsFor($phase));
        $this->assertNotContains('instrument.execute', config('phases.4.actions'));
        $this->assertEmpty(app(HorizonReceiptService::class)->instrumentedIds($phase, collect([$write->id])));
        // Another Participant can use the shared observation without originating it.
        $other = (new Participant)->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'other']);
        $other->save();
        $phase->cycle->forceFill(['participant_id' => $other->id])->save();
        $this->assertSame($withEvidence, $options->attestationOptionsFor($phase->fresh())->contains('id', $write->id));
    }
}
