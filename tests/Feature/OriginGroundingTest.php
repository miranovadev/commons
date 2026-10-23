<?php

namespace Tests\Feature;

use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\AttestationEvidenceService;
use App\Services\CommitmentAdmissibilityService;
use App\Services\CommitmentSnapshotService;
use App\Services\ContextHorizonProjector;
use App\Services\CycleEntryService;
use App\Services\LedgerService;
use App\Services\ParticipationService;
use App\Services\PropositionMeasurementService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OriginGroundingTest extends TestCase
{
    use RefreshDatabase;

    public static function labels(): array
    {
        return [['support', 'refute'], ['refute', 'support']];
    }

    private function creator(): Participant
    {
        $substrate = (new Substrate)->forceFill(['slug' => 'origin-grounding', 'title' => 'Origin grounding', 'visibility' => 'public', 'configuration' => []]);
        $substrate->save();
        $participant = (new Participant)->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'creator']);
        $participant->save();

        return $participant;
    }

    #[DataProvider('labels')]
    public function test_explicit_label_establishes_positive_frame_before_snapshot_without_receipt(string $label, string $opposite): void
    {
        $participant = $this->creator();
        app(CycleEntryService::class)->enter($participant, ['statement' => 'Origin', 'attestation' => ['direction' => $label]]);
        $origin = $participant->fresh()->position;
        $attestation = $origin->attestations()->sole();
        $this->assertSame($label, $attestation->direction);
        $this->assertSame(1.0, app(AttestationEvidenceService::class)->valueFor(collect([$origin])));
        $this->assertSame(-0.5, $origin->commitmentSnapshot->chirality);
        $this->assertNotNull($origin->commitmentSnapshot);
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseCount('evidence_executions', 0);
        $snapshot = $origin->commitmentSnapshot->getAttributes();

        // The opposite human label is negative in the established immutable frame.
        $replacement = (new Attestation)->forceFill(['phase_id' => $participant->cycles()->latest('sequence')->first()->phases()->sole()->id, 'proposition_id' => $origin->id,
            'direction' => $opposite, 'supersedes_attestation_id' => $attestation->id]);
        $replacement->save();
        app(LedgerService::class)->record($replacement);
        $this->assertSame(-1.0, app(AttestationEvidenceService::class)->valueFor(collect([$origin->fresh()])));
        $this->assertSame($snapshot, $origin->commitmentSnapshot->fresh()->getAttributes());
    }

    public static function invalidChoices(): array
    {
        return [[[]], [['attestation' => []]], [['attestation' => ['direction' => '']]], [['attestation' => ['direction' => 'positive']]]];
    }

    #[DataProvider('invalidChoices')]
    public function test_service_rejects_missing_or_invalid_choice_before_bootstrap_state(array $input): void
    {
        $participant = $this->creator();
        try {
            app(CycleEntryService::class)->enter($participant, ['statement' => 'Origin', ...$input]);
            $this->fail('Origin must require an explicit human-facing choice.');
        } catch (DomainException $error) {
            $this->assertSame('Origin requires an explicit Support or Refute Attestation.', $error->getMessage());
        }
        foreach (['propositions', 'attestations', 'cycles', 'phases', 'commitment_snapshots', 'context_horizons', 'substrate_ledger_entries', 'commit_region_polarities'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertNull($participant->fresh()->current_proposition_id);
    }

    public function test_snapshot_boundary_cannot_bypass_origin_attestation(): void
    {
        $participant = $this->creator();
        $horizon = app(ContextHorizonProjector::class)->currentFor($participant->substrate);
        $cycle = (new Cycle)->forceFill(['participant_id' => $participant->id, 'context_horizon_id' => $horizon->id, 'sequence' => 1]);
        $cycle->save();
        $phase = (new Phase)->forceFill(['cycle_id' => $cycle->id, 'kind' => 7, 'write_register' => 'canon', 'started_at' => now()]);
        $phase->save();
        $origin = (new Proposition)->forceFill(['phase_id' => $phase->id, 'statement' => 'Unattested candidate', 'register' => 'canon', 'tier' => 'committal']);
        $origin->save();
        app(LedgerService::class)->record($origin);
        app(PropositionMeasurementService::class)->recalculate($origin);
        try {
            app(CommitmentSnapshotService::class)->record($origin, 0, 0, 'origin-bootstrap-v1');
            $this->fail('The snapshot writer must independently require Origin evidence.');
        } catch (DomainException $error) {
            $this->assertSame('Origin commitment requires an effective Attestation.', $error->getMessage());
        }
        $this->assertDatabaseCount('commitment_snapshots', 0);
    }

    public function test_ordinary_attested_candidate_still_requires_completed_receipt(): void
    {
        $participant = $this->creator();
        $service = app(ParticipationService::class);
        $state = $service->enter($participant, ['statement' => 'Origin', 'attestation' => ['direction' => 'support']]);
        $service->contribute($participant, $state['plan']['phase']['id'], ['action' => 'proposition.variant', 'statement' => 'Ordinary candidate']);
        $candidate = Proposition::where('tier', 'expressive')->sole();
        $attestation = (new Attestation)->forceFill(['phase_id' => $candidate->phase_id, 'proposition_id' => $candidate->id, 'direction' => 'support']);
        $attestation->save();
        app(LedgerService::class)->record($attestation);
        $grounding = app(CommitmentAdmissibilityService::class)->grounding($candidate);
        $this->assertTrue($grounding['requirements']['attestation']);
        $this->assertFalse($grounding['admissible']);
        $this->assertSame(['missing_completed_receipt'], $grounding['reasons']);
    }
}
