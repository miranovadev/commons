<?php

namespace Tests\Feature;

use App\Domain\Observables\ChiralityValues;
use App\Domain\Observables\CorrespondenceValues;
use App\Domain\Observables\LocalMeasurements;
use App\Enums\PhaseKind;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Models\Tragedy;
use App\Services\ContributionService;
use App\Services\CrossoverMarginService;
use App\Services\CycleEntryService;
use App\Services\ExhaustionService;
use App\Services\LedgerService;
use App\Services\Observables\LocalMeasurementService;
use App\Services\ParticipationService;
use App\Services\PotentialChargeService;
use App\Services\PotentialProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
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
        app(PotentialProjectionService::class)->refresh($participant->substrate_id);
        $second = $service->contribute($participant, $first['plan']['phase']['id'],
            ['action' => 'proposition.variant', 'statement' => 'Develop the current structural position']);
        $this->assertSame($value > 0 ? 'canon' : 'concord', $second['plan']['phase']['write_register']);

        // A participant still in the previous Horizon can choose the Committal
        // result, end Order there, and continue under its applicable entry rules.
        $otherPhase = $otherCycle->currentPhase;
        $otherPhase->forceFill(['kind' => PhaseKind::Order, 'write_register' => 'concord', 'gate_proposition_id' => $candidate->id])->save();
        $other->forceFill(['current_proposition_id' => $candidate->id])->save();
        app(PotentialProjectionService::class)->refresh($other->substrate_id);
        $order = $service->stateFor($other)['plan'];
        $this->assertNotContains($candidate->id, array_column($order['gate_options'], 'id'));
        $this->assertSame([$candidate->id, $commit->id], array_column($order['write_options'], 'id'));
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

    public function test_rest_skips_closed_crossover_without_reopening_or_exhausting_tragedy_history(): void
    {
        [$participant, , $cycle, $origin, $candidate] = $this->prepareCommit(.6);
        $service = app(ParticipationService::class);
        $service->contribute($participant, $cycle->currentPhase->id,
            ['action' => 'proposition.commit', 'proposition_id' => $candidate->id]);
        $commit = $participant->fresh()->position;
        $next = $participant->cycles()->whereNull('completed_at')->sole();
        // An actually explored terminal supplies the witness; an empty fresh
        // Commit alone is never sufficient to close the new child Horizon.
        $phase = $next->currentPhase;
        $creation = $this->phaseAtPositionFixture($phase, PhaseKind::Constrain);
        $head = (new Proposition)->forceFill(['phase_id' => $creation->id, 'register' => $creation->write_register,
            'tier' => 'expressive', 'statement' => 'Explored terminal in the new Horizon']);
        $head->save();
        app(LedgerService::class)->record($head);
        $edge = (new Supersession)->forceFill(['phase_id' => $creation->id, 'source_proposition_id' => $commit->id,
            'target_proposition_id' => $head->id, 'type' => 'variant']);
        $edge->save();
        app(LedgerService::class)->record($edge);
        $this->classificationEvidenceFixture($head);
        $phase->forceFill(['kind' => PhaseKind::Order, 'write_register' => 'canon', 'gate_proposition_id' => $head->id])->save();
        $participant->forceFill(['current_proposition_id' => $head->id])->save();
        app(PotentialProjectionService::class)->refresh($participant->substrate_id);
        $this->boundaryTragedyFixture($head);
        $horizons = ContextHorizon::orderBy('sequence')->get()->map(fn ($h) => Arr::except($h->getAttributes(), ['updated_at']))->all();
        $state = $service->stateFor($participant);

        $this->assertSame(['participant.rest'], array_keys($state['plan']['schema']['$defs']['actions']));
        $this->assertTrue(app(CrossoverMarginService::class)->inspect($next)['closed']);
        $rested = app(ParticipationService::class)->contribute($participant, $phase->id, ['action' => 'participant.rest']);
        $this->assertSame($origin->id, $participant->fresh()->current_proposition_id);
        $this->assertSame('ready', $rested['status']);
        $this->assertSame('distinguish', $rested['plan']['phase']['kind']);
        $this->assertSame($origin->id, $rested['plan']['gate_id']);
        $this->assertSame($commit->id, Tragedy::sole()->opening_proposition_id);
        $this->assertSame($cycle->context_horizon_id, $rested['cycle']['context_horizon_id']);
        $this->assertFalse(app(ExhaustionService::class)->boundaryExhausted($next->contextHorizon));
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertSame('settlement', $rested['domain']);
        $this->assertSame('signal', $rested['plan']['phase']['write_register']);
        $this->assertSame($horizons, ContextHorizon::orderBy('sequence')->get()->map(fn ($h) => Arr::except($h->getAttributes(), ['updated_at']))->all());
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
            $mock->shouldReceive('chiralityComponents')->andReturnUsing(fn (Proposition $node) => $node->statement === 'Independent continuing opportunity'
                ? new ChiralityValues(0, 0) : new ChiralityValues($value, $value));
            $mock->shouldReceive('calculate')->andReturnUsing(fn (Proposition $node) => $node->statement === 'Independent continuing opportunity'
                    ? new LocalMeasurements(0, 0, 0, 0, chiralityComponents: new ChiralityValues(0, 0), correspondenceComponents: new CorrespondenceValues(0, 0))
                    : new LocalMeasurements($value, $value, $value, $value, chiralityComponents: new ChiralityValues($value, $value), correspondenceComponents: new CorrespondenceValues($value, $value)));
        });
        // Keep a genuine uncommitted branch in Origin's Region. Its neutral
        // field supplies Potential even with the corrected |Origin Tension|=1/3.
        $branchAuthor = new Participant;
        $branchAuthor->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'continuing branch'])->save();
        $branchCycle = new Cycle;
        $branchCycle->forceFill(['participant_id' => $branchAuthor->id, 'sequence' => 1,
            'entry_committal_id' => $origin->id, 'completed_at' => now()])->save();
        $opportunityPhase = new Phase;
        $opportunityPhase->forceFill(['cycle_id' => $branchCycle->id, 'kind' => PhaseKind::Distinguish,
            'write_register' => 'signal', 'gate_proposition_id' => $origin->id,
            'started_at' => now(), 'completed_at' => now()])->save();
        $opportunity = new Proposition;
        $opportunity->forceFill(['phase_id' => $opportunityPhase->id, 'register' => 'signal',
            'tier' => 'expressive', 'statement' => 'Independent continuing opportunity'])->save();
        app(LedgerService::class)->record($opportunity);
        $opportunityEdge = new Supersession;
        $opportunityEdge->forceFill(['phase_id' => $opportunityPhase->id, 'source_proposition_id' => $origin->id,
            'target_proposition_id' => $opportunity->id, 'type' => 'variant'])->save();
        app(LedgerService::class)->record($opportunityEdge);
        $phase = $cycle->currentPhase;
        $phase->forceFill(['kind' => PhaseKind::Order, 'write_register' => 'concord'])->save();
        $candidate = new Proposition;
        $candidate->forceFill(['phase_id' => $this->phaseAtPositionFixture($phase, PhaseKind::Bias)->id, 'register' => 'concord', 'tier' => 'expressive', 'statement' => 'Prepared local continuation'])->save();
        app(LedgerService::class)->record($candidate);
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $origin->id,
            'target_proposition_id' => $candidate->id, 'type' => 'variant'])->save();
        app(LedgerService::class)->record($edge);
        $this->groundFixture($candidate);
        $phase->forceFill(['gate_proposition_id' => $candidate->id])->save();
        $participant->forceFill(['current_proposition_id' => $candidate->id])->save();

        app(PotentialProjectionService::class)->refresh($participant->substrate_id);

        return [$participant, $other, $cycle->fresh(), $origin, $candidate];
    }
}
