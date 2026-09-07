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
            ->assertJsonPath('data.entry.mode', 'origin')
            ->assertJsonPath('data.entry.available', true)
            ->assertJsonPath('data.plan', null);
    }

    public function test_a_participant_can_establish_the_origin_and_enter_the_first_live_cycle(): void
    {
        [$substrate, $participant] = $this->participant();
        $payload = $this->originPayload();

        $response = $this->postJson(
            $this->endpoint($substrate, $participant).'/enter',
            $payload,
        )
            ->assertCreated()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.cycle.sequence', 2)
            ->assertJsonPath('data.plan.phase.kind', 'distinguish')
            ->assertJsonPath('data.plan.phase.write_register', 'signal')
            ->assertJsonPath(
                'data.plan.gate.statement',
                $payload['origin']['statement'],
            )
            ->assertJsonPath('data.plan.gate.register', 'canon')
            ->assertJsonPath('data.plan.gate.tier', 'committal')
            ->assertJsonPath('data.plan.workspace.domain', 'settlement')
            ->assertJsonPath(
                'data.plan.workspace.provenance.statement',
                $payload['origin']['statement'],
            )
            ->assertJsonStructure([
                'data' => [
                    'plan' => [
                        'workspace' => [
                            'horizon' => ['id', 'sequence', 'opened_at'],
                            'registers' => ['signal', 'stance', 'concord'],
                        ],
                    ],
                ],
            ]);

        $originId = $response->json('data.plan.gate.id');

        $this->assertDatabaseCount('cycles', 2);
        $this->assertDatabaseCount('phases', 2);
        $this->assertDatabaseCount('propositions', 1);
        $this->assertDatabaseCount('attestations', 1);
        $this->assertDatabaseCount('instrument_executions', 1);
        $this->assertDatabaseCount('receipts', 1);
        $this->assertDatabaseCount('substrate_ledger_entries', 3);
        $this->assertDatabaseCount('commitment_snapshots', 1);
        $this->assertDatabaseCount('context_horizons', 2);
        $this->assertDatabaseHas('proposition_measurements', [
            'proposition_id' => $originId,
            'correspondence' => -1,
            'chirality' => -1,
            'closure' => -1,
        ]);
        $this->assertDatabaseHas('commitment_snapshots', [
            'proposition_id' => $originId,
            'prominence' => 0,
            'turbulence' => 1,
            'tension' => -1,
            'direction_before' => 'emergence',
            'direction_after' => 'settlement',
            'calculator_version' => 'origin-bootstrap-v1',
        ]);
        $this->assertDatabaseHas('context_horizons', [
            'substrate_id' => $substrate->getKey(),
            'sequence' => 1,
            'domain' => 'emergence',
            'closed_by_proposition_id' => $originId,
        ]);
        $this->assertDatabaseHas('context_horizons', [
            'substrate_id' => $substrate->getKey(),
            'sequence' => 2,
            'domain' => 'settlement',
            'opened_by_proposition_id' => $originId,
        ]);
        $this->assertDatabaseHas('instrument_executions', [
            'proposition_id' => $originId,
            'status' => 'completed',
        ]);
        $this->assertNotNull(
            Cycle::query()->where('sequence', 1)->firstOrFail()->completed_at,
        );
        $this->assertNull(
            Cycle::query()->where('sequence', 2)->firstOrFail()->completed_at,
        );

        $this->postJson(
            $this->endpoint($substrate, $participant).'/enter',
            $payload,
        )
            ->assertConflict()
            ->assertJsonPath(
                'message',
                'The Participant already has an active Cycle.',
            );

        $this->assertDatabaseCount('propositions', 1);
        $this->assertDatabaseCount('cycles', 2);
    }

    public function test_invalid_origin_entry_is_rejected_without_partial_state(): void
    {
        [$substrate, $participant] = $this->participant();

        $this->postJson(
            $this->endpoint($substrate, $participant).'/enter',
            ['origin' => ['statement' => 'Missing its grounding.']],
        )->assertUnprocessable();

        $this->assertDatabaseCount('cycles', 0);
        $this->assertDatabaseCount('phases', 0);
        $this->assertDatabaseCount('propositions', 0);
        $this->assertDatabaseCount('substrate_ledger_entries', 0);
    }

    public function test_a_second_participant_enters_an_established_substrate_via_potential(): void
    {
        [$substrate, $originParticipant] = $this->participant();

        $originResponse = $this->postJson(
            $this->endpoint($substrate, $originParticipant).'/enter',
            $this->originPayload(),
        )
            ->assertCreated();
        $originId = $originResponse->json('data.plan.gate.id');

        $participant = new Participant;
        $participant->forceFill([
            'substrate_id' => $substrate->getKey(),
            'callsign' => 'second-observer',
        ]);
        $participant->save();
        $endpoint = $this->endpoint($substrate, $participant);

        $this->getJson($endpoint)
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.entry.mode', 'potential')
            ->assertJsonPath('data.entry.available', true);

        $this->postJson($endpoint.'/enter', [])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.cycle.sequence', 1)
            ->assertJsonPath('data.plan.phase.kind', 'distinguish')
            ->assertJsonPath('data.plan.phase.write_register', 'signal')
            ->assertJsonPath('data.plan.gate.id', $originId);

        $this->assertDatabaseCount('propositions', 1);
        $this->assertDatabaseCount('cycles', 3);
        $this->assertDatabaseHas('phases', [
            'gate_proposition_id' => $originId,
            'kind' => PhaseKind::Distinguish->value,
        ]);

        $this->postJson($endpoint.'/enter', [])->assertConflict();
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
            )
            ->assertJsonPath('data.plan.workspace.domain', 'emergence')
            ->assertJsonPath(
                'data.plan.workspace.provenance.statement',
                'The Origin.',
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
            )
            ->assertJsonPath('data.plan.workspace.domain', 'emergence')
            ->assertJsonPath(
                'data.plan.workspace.registers.activity.0.statement',
                'A first expressive distinction.',
            )
            ->assertJsonMissingPath('data.plan.gate.impact')
            ->assertJsonMissingPath(
                'data.plan.workspace.registers.activity.0.impact',
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

    /**
     * @return array<string, mixed>
     */
    private function originPayload(): array
    {
        return [
            'origin' => [
                'statement' => 'The Commons begins with a grounded commitment.',
                'attestation' => [
                    'direction' => 'support',
                    'rationale' => 'This is a worthy Origin.',
                ],
                'instrument' => [
                    'kind' => 'hyperlink',
                    'reference' => 'https://example.test/origin',
                ],
            ],
        ];
    }
}
