<?php

namespace Tests\Feature;

use App\Domain\Observables\LocalMeasurements;
use App\Enums\PhaseKind;
use App\Models\ContextHorizon;
use App\Models\Participant;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContributionService;
use App\Services\CycleEntryService;
use App\Services\LedgerService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\ParticipationService;
use App\Services\PotentialChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ParticipantContinuityTest extends TestCase
{
    use RefreshDatabase;

    public static function directions(): array
    {
        return ['boundary' => [.6, 'emergence', 'activity'], 'same Domain' => [-.6, 'settlement', 'signal']];
    }

    #[DataProvider('directions')]
    public function test_real_commit_retains_local_continuity_with_or_without_a_domain_boundary(float $value, string $domain, string $register): void
    {
        [$participant, $other, $cycle, $origin, $candidate] = $this->prepareCommit($value);
        $otherPosition = $other->fresh()->current_proposition_id;
        $otherCycle = $other->cycles()->whereNull('completed_at')->sole();
        $service = app(ParticipationService::class);
        $completed = $service->contribute($participant, $cycle->currentPhase->id,
            ['action' => 'proposition.commit', 'proposition_id' => $candidate->id]);
        $commit = $participant->fresh()->position;

        $this->assertSame('committal', $commit->tier);
        $this->assertSame([$candidate->id], $commit->commitSources()->pluck('propositions.id')->all());
        $this->assertSame($commit->id, $completed['current_proposition_id']);
        $this->assertSame('ready', $completed['status']);
        $this->assertSame('distinguish', $completed['plan']['phase']['kind']);
        $this->assertSame($commit->id, $completed['plan']['gate_id']);
        $this->assertNotNull($cycle->fresh()->completed_at);
        $this->assertFalse($commit->children()->exists()); // No descendants, hence no Potential bonus.

        // Even observer-attenuated Potential prefers Origin's developed region.
        // The next Cycle must still start at the new, zero-Potential Commit.
        $this->assertSame($origin->id, app(PotentialChargeService::class)->selectFrom($commit)['proposition']->id);
        $this->assertSame($commit->id, $service->workspaceFor($participant)['plan']['gate']['id']);
        $next = $service->stateFor($participant);
        $this->assertSame($commit->id, $next['current_proposition_id']);
        $this->assertSame($commit->id, $next['plan']['gate_id']);
        $this->assertSame($commit->id, $next['plan']['provenance_id']);
        $this->assertSame($domain, $next['domain']);
        $this->assertSame($register, $next['plan']['phase']['write_register']);
        $owner = $value > 0 ? ContextHorizon::where('opened_by_proposition_id', $commit->id)->sole() : $cycle->contextHorizon;
        $this->assertSame($owner->id, $next['cycle']['context_horizon_id']);
        $workspace = $service->workspaceFor($participant)['plan'];
        $this->assertSame($commit->id, $workspace['workspace']['provenance']['id']);
        $this->assertSame($commit->id, $workspace['gate']['id']);
        $this->assertSame($domain, $workspace['workspace']['horizon']['domain']);
        // A real boundary continuation makes these references coincide naturally.
        if ($value > 0) {
            $this->assertSame($commit->id, $workspace['workspace']['horizon']['opening_proposition']['id']);
            $this->assertSame('concord', $workspace['workspace']['horizon']['opening_proposition']['register']);
        }

        $this->assertSame($otherPosition, $other->fresh()->current_proposition_id);
        $this->assertNull($otherCycle->fresh()->completed_at);
        $this->assertSame($cycle->context_horizon_id, $otherCycle->fresh()->context_horizon_id);

        // The continuation uses the owning Domain's full register sequence.
        $first = $service->contribute($participant, $next['plan']['phase']['id'],
            ['action' => 'proposition.variant', 'statement' => 'Continue locally']);
        $this->assertSame($value > 0 ? 'frontier' : 'stance', $first['plan']['phase']['write_register']);
        $this->classificationEvidenceFixture(Proposition::findOrFail($first['plan']['gate_id']));
        app(\App\Services\PotentialProjectionService::class)->refresh($participant->substrate_id);
        $second = $service->contribute($participant, $first['plan']['phase']['id'],
            ['action' => 'proposition.reclassify']);
        $this->assertSame($value > 0 ? 'canon' : 'concord', $second['plan']['phase']['write_register']);

        // A participant still in the previous Horizon can choose the Committal
        // result, end Order there, and continue under its applicable entry rules.
        $otherPhase = $otherCycle->currentPhase;
        $otherPhase->forceFill(['kind' => PhaseKind::Order, 'write_register' => 'concord', 'gate_proposition_id' => $candidate->id])->save();
        $other->forceFill(['current_proposition_id' => $candidate->id])->save();
        app(\App\Services\PotentialProjectionService::class)->refresh($other->substrate_id);
        $order = $service->stateFor($other)['plan'];
        $this->assertNotContains($candidate->id, array_column($order['gate_options'], 'id'));
        $this->assertSame([$commit->id], array_column($order['write_options'], 'id'));
        $this->assertSame(['proposition.traverse'], array_keys($order['schema']['$defs']['actions']), json_encode($order));
        $this->assertSame($order['schema'], app(ContributionService::class)->planFor($other->fresh())['schema']);
        $traversed = $service->contribute($other, $otherPhase->id,
            ['action' => 'proposition.traverse', 'proposition_id' => $commit->id]);
        $this->assertSame($commit->id, $traversed['current_proposition_id']);
        $this->assertNotNull($otherCycle->fresh()->completed_at);
        $this->assertNull($otherCycle->fresh()->rested_at);
        $continued = $traversed;
        $this->assertSame($commit->id, $continued['plan']['gate_id']);
        $this->assertSame($owner->id, $continued['cycle']['context_horizon_id']);
        $this->assertSame($domain, $continued['domain']);
        $this->assertSame($register, $continued['plan']['phase']['write_register']);

    }

    public function test_tragedy_can_relocate_back_to_origin_without_reopening_a_historical_horizon(): void
    {
        [$participant, , $cycle, $origin, $candidate] = $this->prepareCommit(.6);
        $service = app(ParticipationService::class);
        $service->contribute($participant, $cycle->currentPhase->id,
            ['action' => 'proposition.commit', 'proposition_id' => $candidate->id]);
        $commit = $participant->fresh()->position;
        $next = $participant->cycles()->whereNull('completed_at')->sole();
        // An exhausted Order has no expressive candidate and exposes Rest alone.
        $phase = $next->currentPhase;
        $phase->forceFill(['kind' => PhaseKind::Order, 'write_register' => 'canon'])->save();
        $horizons = ContextHorizon::orderBy('sequence')->get()->map->getAttributes()->all();
        $state = $service->stateFor($participant);
        foreach ($state['plan']['schema']['$defs']['actions']['receipt.read']['properties']['receipt_id']['enum'] ?? [] as $receiptId) {
            $state = $service->contribute($participant, $phase->id, ['action' => 'receipt.read', 'receipt_id' => $receiptId]);
        }
        $this->assertSame(['participant.rest'], array_keys($state['plan']['schema']['$defs']['actions']));
        $rested = app(ParticipationService::class)->contribute($participant, $phase->id, ['action' => 'participant.rest']);
        $this->assertSame($origin->id, $participant->fresh()->current_proposition_id);
        $this->assertSame('ready', $rested['status']);
        $this->assertSame('distinguish', $rested['plan']['phase']['kind']);
        $selection = app(PotentialChargeService::class)->recoverFrom($commit);
        $this->assertSame($origin->id, $selection['proposition']->id);
        $this->assertGreaterThan(0, $selection['potential_charge']);
        $this->assertSame($origin->id, $rested['plan']['gate_id']);
        $relocated = $rested;
        $this->assertSame($origin->id, $relocated['current_proposition_id']);
        $this->assertSame($origin->id, $relocated['plan']['gate_id']);
        $this->assertNotSame($origin->phase->cycle->context_horizon_id, $relocated['cycle']['context_horizon_id']);
        $selectedHorizon = ContextHorizon::where('opened_by_proposition_id', $origin->id)->sole();
        $this->assertSame($selectedHorizon->id, $relocated['cycle']['context_horizon_id']);
        $this->assertSame('settlement', $relocated['domain']);
        $this->assertSame('signal', $relocated['plan']['phase']['write_register']);
        $this->assertSame($horizons, ContextHorizon::orderBy('sequence')->get()->map->getAttributes()->all());
    }

    private function prepareCommit(float $value): array
    {
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'continuity', 'title' => 'Continuity', 'visibility' => 'public', 'configuration' => []])->save();
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'committer'])->save();
        $entry = app(CycleEntryService::class);
        $entry->enter($participant, ['statement' => 'Origin', 'attestation' => ['direction' => 'support'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin']]);
        $origin = $participant->fresh()->position;
        $other = new Participant;
        $other->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'finishing independently'])->save();
        $entry->enter($other);
        $cycle = $participant->cycles()->whereNull('completed_at')->sole();
        // Hold numeric thresholds fixed; Commit validation, snapshots, ownership,
        // completion, entry and subsequent contributions use their real services.
        $this->mock(LocalMeasurementService::class, function ($mock) use ($value): void {
            $mock->shouldReceive('calculate')->andReturn(new LocalMeasurements($value, $value, $value, $value));
        });
        $phase = $cycle->currentPhase;
        $phase->forceFill(['kind' => PhaseKind::Order, 'write_register' => 'concord'])->save();
        $candidate = new Proposition;
        $candidate->forceFill(['phase_id' => $phase->id, 'register' => 'concord', 'tier' => 'expressive', 'statement' => 'Prepared local continuation'])->save();
        app(LedgerService::class)->record($candidate);
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $origin->id,
            'target_proposition_id' => $candidate->id, 'type' => 'variant'])->save();
        app(LedgerService::class)->record($edge);
        $this->groundFixture($candidate);
        $phase->forceFill(['gate_proposition_id' => $candidate->id])->save();
        $participant->forceFill(['current_proposition_id' => $candidate->id])->save();

        app(\App\Services\PotentialProjectionService::class)->refresh($participant->substrate_id);
        return [$participant, $other, $cycle->fresh(), $origin, $candidate];
    }
}
