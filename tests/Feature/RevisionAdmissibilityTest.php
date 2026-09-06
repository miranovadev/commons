<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\ContributionDispatcher;
use App\Services\InputPlanService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevisionAdmissibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_revision_targets_owned_heads_in_the_write_register_from_a_prior_cycle(): void
    {
        [$participant, $phase, $gate] = $this->currentPhase();
        $owned = $this->priorHead(
            $participant,
            'frontier',
            'An owned Frontier head.',
        );
        $wrongRegister = $this->priorHead(
            $participant,
            'activity',
            'An owned Activity head.',
        );
        $other = new Participant;
        $other->forceFill([
            'substrate_id' => $participant->substrate_id,
            'callsign' => 'other',
        ]);
        $other->save();
        $foreign = $this->priorHead(
            $other,
            'frontier',
            'Someone else owns this Frontier head.',
        );

        $plan = app(InputPlanService::class)->forParticipant($participant);

        $this->assertEqualsCanonicalizing(
            [$owned->getKey(), $foreign->getKey()],
            array_column($plan['write_options'], 'id'),
        );
        $this->assertSame(
            [$owned->getKey()],
            array_column($plan['revision_options'], 'id'),
        );
        $this->assertSame(
            [$owned->getKey()],
            $plan['schema']['$defs']['actions']['proposition.revise']
                ['properties']['proposition_id']['enum'],
        );
        $this->assertNotContains(
            $gate->getKey(),
            array_column($plan['revision_options'], 'id'),
        );
        $this->assertNotContains(
            $wrongRegister->getKey(),
            array_column($plan['revision_options'], 'id'),
        );

        $target = app(ContributionDispatcher::class)->dispatch(
            $participant,
            $phase,
            [
                'action' => 'proposition.revise',
                'proposition_id' => $owned->getKey(),
                'statement' => 'The revised Frontier statement.',
            ],
        );

        $this->assertSame('frontier', $target->register);
        $this->assertDatabaseHas('supersessions', [
            'source_proposition_id' => $owned->getKey(),
            'target_proposition_id' => $target->getKey(),
            'type' => 'revise',
        ]);
    }

    public function test_the_gate_cannot_be_revised_even_when_it_matches_the_write_register(): void
    {
        [$participant, $phase, $gate] = $this->currentPhase('frontier');
        $plan = app(InputPlanService::class)->forParticipant($participant);

        $this->assertArrayNotHasKey(
            'proposition.revise',
            $plan['schema']['$defs']['actions'],
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Revision requires an owned active head in the current write register.'
        );

        app(ContributionDispatcher::class)->dispatch(
            $participant,
            $phase,
            [
                'action' => 'proposition.revise',
                'proposition_id' => $gate->getKey(),
                'statement' => 'This must never be written.',
            ],
        );
    }

    /**
     * @return array{Participant, Phase, Proposition}
     */
    private function currentPhase(
        string $gateRegister = 'canon',
    ): array {
        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => 'revision-admissibility',
            'title' => 'Revision admissibility',
            'visibility' => 'private',
            'configuration' => [],
        ]);
        $substrate->save();

        $participant = new Participant;
        $participant->forceFill([
            'substrate_id' => $substrate->getKey(),
            'callsign' => 'owner',
            'max_read_count' => 25,
            'max_token_count' => null,
        ]);
        $participant->save();

        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $participant->getKey(),
            'sequence' => 2,
        ]);
        $cycle->save();

        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->getKey(),
            'kind' => PhaseKind::Persist,
            'write_register' => 'frontier',
            'started_at' => now(),
        ]);
        $phase->save();

        $gate = new Proposition;
        $gate->forceFill([
            'phase_id' => $phase->getKey(),
            'register' => $gateRegister,
            'tier' => 'expressive',
            'statement' => 'The current Gate.',
        ]);
        $gate->save();
        $phase->gate_proposition_id = $gate->getKey();
        $phase->save();

        return [$participant, $phase, $gate];
    }

    private function priorHead(
        Participant $participant,
        string $register,
        string $statement,
    ): Proposition {
        $cycle = Cycle::query()
            ->whereBelongsTo($participant)
            ->whereNotNull('completed_at')
            ->first();

        if ($cycle === null) {
            $cycle = new Cycle;
            $cycle->forceFill([
                'participant_id' => $participant->getKey(),
                'sequence' => 1,
                'completed_at' => now(),
            ]);
            $cycle->save();
        }

        $phase = Phase::query()->whereBelongsTo($cycle)->first();

        if ($phase === null) {
            $phase = new Phase;
            $phase->forceFill([
                'cycle_id' => $cycle->getKey(),
                'kind' => PhaseKind::Distinguish,
                'write_register' => $register,
                'started_at' => now()->subMinute(),
                'completed_at' => now(),
            ]);
            $phase->save();
        }

        $proposition = new Proposition;
        $proposition->forceFill([
            'phase_id' => $phase->getKey(),
            'register' => $register,
            'tier' => 'expressive',
            'statement' => $statement,
        ]);
        $proposition->save();

        return $proposition;
    }
}
