<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\PropositionMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InputPlanVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reclassified_proposition_remains_visible_in_its_original_register(): void
    {
        $substrate = $this->substrate();
        $author = $this->participant($substrate, 'author');
        $authorPhase = $this->phase($author, PhaseKind::Distinguish, 'activity');

        $origin = $this->proposition(
            $authorPhase,
            'canon',
            'committal',
            'The Origin.',
            -1.0,
        );
        $source = $this->proposition(
            $authorPhase,
            'activity',
            'expressive',
            'An initial distinction.',
            -0.5,
        );
        $this->supersession($authorPhase, $origin, $source, 'variant');

        $reclassified = $this->proposition(
            $authorPhase,
            'frontier',
            'expressive',
            'The distinction, reclassified.',
            0.0,
        );
        $this->supersession(
            $authorPhase,
            $source,
            $reclassified,
            'reclassify',
        );

        $viewer = $this->participant($substrate, 'viewer');
        $this->phase(
            $viewer,
            PhaseKind::Distinguish,
            'activity',
            $origin,
        );

        $plan = app(InputPlanService::class)->forParticipant($viewer);
        $visibleActivityIds = collect(
            $plan['workspace']['registers']['activity'],
        )->pluck('id');
        $writeOptionIds = collect($plan['write_options'])->pluck('id');

        $this->assertTrue($visibleActivityIds->contains($source->getKey()));
        $this->assertFalse($writeOptionIds->contains($source->getKey()));
    }

    private function substrate(): Substrate
    {
        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => 'visible-reclassified-propositions',
            'title' => 'Visible reclassified propositions',
            'visibility' => 'private',
            'configuration' => [],
        ]);
        $substrate->save();

        return $substrate;
    }

    private function participant(
        Substrate $substrate,
        string $callsign,
    ): Participant {
        $participant = new Participant;
        $participant->forceFill([
            'substrate_id' => $substrate->getKey(),
            'callsign' => $callsign,
            'max_read_count' => 25,
        ]);
        $participant->save();

        return $participant;
    }

    private function phase(
        Participant $participant,
        PhaseKind $kind,
        string $writeRegister,
        ?Proposition $gate = null,
    ): Phase {
        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $participant->getKey(),
            'sequence' => 1,
        ]);
        $cycle->save();

        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->getKey(),
            'gate_proposition_id' => $gate?->getKey(),
            'kind' => $kind,
            'write_register' => $writeRegister,
            'started_at' => now(),
        ]);
        $phase->save();

        return $phase;
    }

    private function proposition(
        Phase $phase,
        string $register,
        string $tier,
        string $statement,
        float $measurement,
    ): Proposition {
        $proposition = new Proposition;
        $proposition->forceFill([
            'phase_id' => $phase->getKey(),
            'register' => $register,
            'tier' => $tier,
            'statement' => $statement,
        ]);
        $proposition->save();

        app(LedgerService::class)->record($proposition);
        app(PropositionMeasurementService::class)->project(
            $proposition,
            $measurement,
            $measurement,
            $measurement,
        );

        return $proposition;
    }

    private function supersession(
        Phase $phase,
        Proposition $source,
        Proposition $target,
        string $type,
    ): void {
        $supersession = new Supersession;
        $supersession->forceFill([
            'phase_id' => $phase->getKey(),
            'source_proposition_id' => $source->getKey(),
            'target_proposition_id' => $target->getKey(),
            'type' => $type,
        ]);
        $supersession->save();
        app(LedgerService::class)->record($supersession);
    }
}
