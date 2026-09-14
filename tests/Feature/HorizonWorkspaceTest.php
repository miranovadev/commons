<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Models\User;
use App\Services\CycleEntryService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorizonWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_workspaces_serialize_cycle_domain_boundary_and_actual_provenance_independently(): void
    {
        $this->postJson('/api/bootstrap', ['name' => 'Owner', 'email' => 'owner@example.test',
            'password' => 'test-password-1234', 'password_confirmation' => 'test-password-1234'])->assertCreated();
        $user = User::firstOrFail();
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'presentation', 'title' => 'Presentation', 'visibility' => 'public', 'configuration' => []])->save();
        $founder = new Participant;
        $founder->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'Founder'])->save();
        app(CycleEntryService::class)->enter($founder, ['statement' => 'Origin', 'attestation' => ['direction' => 'positive'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin']]);
        $origin = $founder->fresh()->position;
        $h2 = $founder->cycles()->whereNull('completed_at')->sole()->contextHorizon;
        [$nova, $concord] = $this->orderWorkspace($h2, $origin, $user, 'Nova');
        $h3 = $this->laterHorizonFixture($h2, 1);
        [$vela, $canon] = $this->orderWorkspace($h3, $origin, $user, 'Vela');
        $this->actingAs($user);

        foreach ([[$nova, $concord, $h2, 'settlement'], [$vela, $canon, $h3, 'emergence']] as [$participant, $gate, $horizon, $domain]) {
            $response = $this->getJson('/api/substrates/presentation/participants/'.$participant->id.'/workspace')->assertOk()
                ->assertJsonPath('data.plan.phase.number', 7)
                ->assertJsonPath('data.plan.phase.write_register', $gate->register)
                ->assertJsonPath('data.plan.gate.register', $gate->register)
                ->assertJsonPath('data.plan.workspace.domain', $domain)
                ->assertJsonPath('data.plan.workspace.horizon.domain', $domain)
                ->assertJsonPath('data.plan.workspace.horizon.id', $horizon->id)
                ->assertJsonPath('data.plan.workspace.horizon.opened_by_proposition_id', $horizon->opened_by_proposition_id)
                ->assertJsonPath('data.plan.workspace.horizon.opening_proposition.id', $horizon->opened_by_proposition_id)
                ->assertJsonPath('data.plan.workspace.provenance.id', $origin->id)
                ->assertJsonPath('data.plan.workspace.provenance.register', 'canon');
            $plan = $response->json('data.plan');
            $this->assertSame([$origin->id], array_column($plan['lineage'], 'id'));
            $this->assertSame('canon', $plan['lineage'][0]['register']);
            foreach ($plan['workspace']['registers'] as $register => $cards) {
                foreach ($cards as $card) {
                    $this->assertSame($register, $card['register']);
                }
            }
            $this->getJson('/api/substrates/presentation/propositions/'.$gate->id)->assertOk()->assertJsonPath('data.register', $gate->register);
        }
        $this->assertNotSame($origin->id, $h3->opened_by_proposition_id);
        $this->assertSame('concord', Proposition::findOrFail($h3->opened_by_proposition_id)->register);
        $this->getJson('/api/substrates/presentation/participants/'.$vela->id.'/workspace')
            ->assertJsonPath('data.plan.workspace.horizon.opening_proposition.register', 'concord');
        $this->assertNotNull($h2->fresh()->closed_at);
        $this->assertNull($nova->cycles()->sole()->completed_at);
    }

    private function orderWorkspace(ContextHorizon $horizon, Proposition $provenance, User $user, string $callsign): array
    {
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $horizon->substrate_id, 'user_id' => $user->id, 'callsign' => $callsign])->save();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'context_horizon_id' => $horizon->id, 'sequence' => 1])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Order,
            'write_register' => $horizon->domain->registerFor(PhaseKind::Order), 'gate_proposition_id' => $provenance->id, 'started_at' => now()])->save();
        $gate = new Proposition;
        $gate->forceFill(['phase_id' => $phase->id, 'register' => $phase->write_register, 'tier' => 'expressive', 'statement' => $callsign.' Order Gate'])->save();
        app(LedgerService::class)->record($gate);
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $phase->id, 'source_proposition_id' => $provenance->id,
            'target_proposition_id' => $gate->id, 'type' => 'variant'])->save();
        app(LedgerService::class)->record($edge);
        $phase->forceFill(['gate_proposition_id' => $gate->id])->save();
        $participant->forceFill(['current_proposition_id' => $gate->id])->save();

        return [$participant, $gate];
    }
}
