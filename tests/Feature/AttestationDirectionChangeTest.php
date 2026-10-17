<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContributionDispatcher;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\PropositionMeasurementService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\PropositionBirth;
use Tests\TestCase;

class AttestationDirectionChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_direction_can_change_once_in_a_later_write_and_remains_auditable(): void
    {
        [$participant, $cycle, $phase, $proposition] = $this->expressiveHead();
        $dispatcher = app(ContributionDispatcher::class);

        $dispatcher->dispatch($participant, $phase, [
            'action' => 'attestation.create',
            'proposition_id' => $proposition->getKey(),
            'direction' => 'support',
        ]);

        $phase->completed_at = now();
        $phase->save();
        $next = $this->phase($cycle, PhaseKind::Distinguish, $proposition->parents()->firstOrFail());

        $dispatcher->dispatch($participant, $next, [
            'action' => 'attestation.create',
            'proposition_id' => $proposition->getKey(),
            'direction' => 'refute',
            'rationale' => 'The later Write changes my effective direction.',
        ]);

        $attestations = Attestation::query()->oldest()->get();
        $this->assertCount(2, $attestations);
        $this->assertSame($attestations[0]->getKey(), $attestations[1]->supersedes_attestation_id);
        $this->assertSame('refute', Attestation::query()->effective()->sole()->direction);
        $this->assertDatabaseCount('substrate_ledger_entries', 7);

        app(PropositionMeasurementService::class)->project(
            $proposition,
            0.8,
            0.6,
            0.4,
        );
        $this->publishPotentialFixture($participant);
        $plan = app(InputPlanService::class)->forWorkspace($participant);
        $write = collect($plan['write_options'])->firstWhere('id', $proposition->id);
        $this->assertSame('refute', $write['attestation']['direction']);
        $this->assertArrayNotHasKey('impact', $plan['gate']);
        $this->assertSame(
            -1.0,
            (float) $plan['workspace']['provenance']['observables']['correspondence'],
        );
        $this->assertSame(
            0.8,
            (float) $write['observables']['correspondence'],
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('current Write');
        $dispatcher->dispatch($participant, $next, [
            'action' => 'attestation.create',
            'proposition_id' => $proposition->getKey(),
            'direction' => 'support',
        ]);
    }

    /** @return array{Participant, Cycle, Phase, Proposition} */
    private function expressiveHead(): array
    {
        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => 'attestation-change',
            'title' => 'Attestation change',
            'visibility' => 'private',
            'configuration' => [],
        ]);
        $substrate->save();

        $participant = new Participant;
        $participant->forceFill([
            'substrate_id' => $substrate->getKey(),
            'callsign' => 'witness',
            'max_read_count' => 25,
        ]);
        $participant->save();

        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->getKey(), 'sequence' => 1]);
        $cycle->save();
        $phase = $this->phase($cycle, PhaseKind::Distinguish);

        $origin = new Proposition;
        $origin->forceFill([
            'phase_id' => $phase->getKey(),
            'register' => 'canon',
            'tier' => 'committal',
            'statement' => 'An inherited origin.',
        ]);
        $origin->save();
        app(LedgerService::class)->record($origin);
        $this->freezeRegionFixture($origin, -1);
        $phase->forceFill(['completed_at' => now()])->save();
        $cycle->forceFill(['completed_at' => now()])->save();
        $phase = $this->governedPhaseFixture($phase, $origin);
        $phase->forceFill(['completed_at' => null])->save();
        $cycle = $phase->cycle;

        $proposition = new Proposition;
        $proposition->forceFill([
            'phase_id' => $phase->getKey(),
            'register' => 'activity',
            'tier' => 'expressive',
            'statement' => 'An expressive impact.',
        ]);
        $proposition->save();
        app(LedgerService::class)->record($proposition, $origin);

        $supersession = new Supersession;
        $supersession->forceFill([
            'phase_id' => $phase->getKey(),
            'source_proposition_id' => $origin->getKey(),
            'target_proposition_id' => $proposition->getKey(),
            'type' => 'variant',
        ]);
        $supersession->save();
        app(LedgerService::class)->record($supersession);

        $phase->gate_proposition_id = $origin->getKey();
        $phase->save();
        PropositionBirth::complete($proposition);
        app(PropositionMeasurementService::class)->project($origin, -1, -1, -1);
        app(PropositionMeasurementService::class)->project($proposition, 0.8, 0.6, 0.4);

        $this->receiptFixture($proposition);

        return [$participant, $cycle, $phase, $proposition];
    }

    private function phase(
        Cycle $cycle,
        PhaseKind $kind,
        ?Proposition $gate = null,
    ): Phase {
        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->getKey(),
            'gate_proposition_id' => $gate?->getKey(),
            'kind' => $kind,
            'write_register' => 'activity',
            'started_at' => now(),
        ]);
        $phase->save();

        return $phase;
    }
}
