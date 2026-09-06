<?php

namespace Tests\Feature\Api;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_participation_reports_an_expected_waiting_state(): void
    {
        [$substrate, $participant] = $this->participant();

        $this->getJson($this->endpoint($substrate, $participant))
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.reason.code', 'cycle_not_started')
            ->assertJsonPath('data.plan', null);
    }

    public function test_ready_state_includes_the_plan_and_attenuation_seam(): void
    {
        [$substrate, $participant] = $this->participant();
        $this->activePhase($participant);

        $this->getJson($this->endpoint($substrate, $participant))
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.cycle.sequence', 1)
            ->assertJsonPath('data.plan.phase.kind', 'distinguish')
            ->assertJsonPath(
                'data.perspective.attenuation.status',
                'pending',
            )
            ->assertJsonPath(
                'data.perspective.attenuation.value',
                null,
            );
    }

    public function test_a_contribution_atomically_advances_the_phase(): void
    {
        [$substrate, $participant] = $this->participant();
        [$phase, $origin] = $this->activePhase($participant);
        $endpoint = $this->endpoint($substrate, $participant);
        $payload = [
            'phase_id' => $phase->getKey(),
            'contribution' => [
                'action' => 'proposition.variant',
                'statement' => 'A first expressive distinction.',
            ],
        ];

        $response = $this->postJson($endpoint, $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.plan.phase.kind', 'persist')
            ->assertJsonPath(
                'data.plan.gate.statement',
                'A first expressive distinction.',
            );

        $gateId = $response->json('data.plan.gate.id');

        $this->assertDatabaseHas('propositions', [
            'id' => $gateId,
            'phase_id' => $phase->getKey(),
            'register' => 'activity',
            'tier' => 'expressive',
        ]);
        $this->assertDatabaseHas('supersessions', [
            'source_proposition_id' => $origin->getKey(),
            'target_proposition_id' => $gateId,
            'type' => 'variant',
        ]);
        $this->assertNotNull($phase->fresh()->completed_at);
        $this->assertDatabaseHas('phases', [
            'cycle_id' => $phase->cycle_id,
            'kind' => PhaseKind::Persist->value,
            'gate_proposition_id' => $gateId,
            'write_register' => 'frontier',
        ]);

        $this->postJson($endpoint, $payload)
            ->assertConflict()
            ->assertJsonPath(
                'message',
                'The participation state changed. Refresh before contributing.',
            );

        $this->assertDatabaseCount('propositions', 2);
    }

    public function test_an_invalid_contribution_does_not_advance(): void
    {
        [$substrate, $participant] = $this->participant();
        [$phase] = $this->activePhase($participant);

        $this->postJson(
            $this->endpoint($substrate, $participant),
            [
                'phase_id' => $phase->getKey(),
                'contribution' => [
                    'action' => 'proposition.variant',
                ],
            ],
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The contribution is not admissible.',
            );

        $this->assertNull($phase->fresh()->completed_at);
        $this->assertDatabaseCount('propositions', 1);
        $this->assertDatabaseCount('phases', 1);
    }

    /**
     * @return array{Substrate, Participant}
     */
    private function participant(): array
    {
        $this->postJson('/api/bootstrap', [
            'name' => 'System',
            'email' => 'system@example.test',
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
        ])->assertCreated();

        $user = User::query()->firstOrFail();
        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => 'participation',
            'title' => 'Participation',
            'visibility' => 'private',
            'configuration' => [],
        ]);
        $substrate->save();

        $participant = new Participant;
        $participant->forceFill([
            'substrate_id' => $substrate->getKey(),
            'user_id' => $user->getKey(),
            'callsign' => 'observer',
        ]);
        $participant->save();

        return [$substrate, $participant];
    }

    /**
     * @return array{Phase, Proposition}
     */
    private function activePhase(Participant $participant): array
    {
        $cycle = new Cycle;
        $cycle->forceFill([
            'participant_id' => $participant->getKey(),
            'sequence' => 1,
        ]);
        $cycle->save();

        $phase = new Phase;
        $phase->forceFill([
            'cycle_id' => $cycle->getKey(),
            'kind' => PhaseKind::Distinguish,
            'write_register' => 'activity',
            'started_at' => now(),
        ]);
        $phase->save();

        $origin = new Proposition;
        $origin->forceFill([
            'phase_id' => $phase->getKey(),
            'register' => 'canon',
            'tier' => 'committal',
            'statement' => 'The Origin.',
        ]);
        $origin->save();

        $phase->gate_proposition_id = $origin->getKey();
        $phase->save();
        app(LedgerService::class)->record($origin);

        return [$phase, $origin];
    }

    private function endpoint(
        Substrate $substrate,
        Participant $participant,
    ): string {
        return "/api/substrates/{$substrate->slug}"
            ."/participants/{$participant->getKey()}/participate";
    }
}
