<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Participant;
use App\Services\ContributionDispatcher;
use App\Services\HorizonReceiptService;
use App\Services\OperationalInstrumentService;
use App\Services\Participation\ParticipationContext;
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
        foreach ([null, new ParticipationContext($phase)] as $context) {
            $this->assertSame($withEvidence, $options->attestationOptionsFor($phase, $context)->contains('id', $write->id));
        }
        $this->assertSame($kind === PhaseKind::Disperse && ! $withEvidence, $options->instrumentOptionsFor($phase)->contains('id', $disperseGate->id));
        $this->assertNotContains($write->id, $options->instrumentOptionsFor($phase)->pluck('id')->all());
        $this->assertEmpty(app(HorizonReceiptService::class)->instrumentedIds($phase, collect([$write->id])));
        // Another Participant can use the shared observation without originating it.
        $other = (new Participant)->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'other']);
        $other->save();
        $cycle = (new Cycle)->forceFill(['participant_id' => $other->id, 'sequence' => 1,
            'context_horizon_id' => $phase->cycle->context_horizon_id, 'entry_committal_id' => $f->seed->id]);
        $cycle->save();
        $otherPhase = $phase->replicate();
        $otherPhase->forceFill(['cycle_id' => $cycle->id])->save();
        $otherPhase = $otherPhase->fresh();
        foreach ([null, new ParticipationContext($otherPhase)] as $context) {
            $this->assertSame($withEvidence, $options->attestationOptionsFor($otherPhase, $context)->contains('id', $write->id));
        }
        $this->assertSame($f->viewer->id, $f->phase->cycle->fresh()->participant_id, 'Originating provenance stays unchanged');
        if ($withEvidence) {
            app(ContributionDispatcher::class)->dispatch($other, $otherPhase,
                ['action' => 'attestation.create', 'proposition_id' => $write->id, 'direction' => 'support']);
            $this->assertDatabaseHas('attestations', ['phase_id' => $otherPhase->id, 'proposition_id' => $write->id]);
            $this->assertDatabaseMissing('evidence_executions', ['phase_id' => $otherPhase->id]);
        }
    }
}
