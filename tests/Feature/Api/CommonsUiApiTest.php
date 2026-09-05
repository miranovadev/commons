<?php

namespace Tests\Feature\Api;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\PropositionMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CommonsUiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_is_told_when_system_bootstrap_is_required(): void
    {
        $this->getJson('/api/substrates')
            ->assertStatus(Response::HTTP_PRECONDITION_REQUIRED)
            ->assertJsonPath('code', 'bootstrap_required');

        User::factory()->create();

        $this->getJson('/api/substrates')->assertOk();
    }

    public function test_guest_can_list_and_view_only_public_substrates(): void
    {
        User::factory()->create();

        $public = $this->substrate('open-space', 'public');
        $private = $this->substrate('quiet-space', 'private');

        $this->getJson('/api/substrates')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $public->slug);

        $this->getJson("/api/substrates/{$public->slug}")->assertOk();
        $this->getJson("/api/substrates/{$private->slug}")->assertForbidden();
    }

    public function test_graph_endpoint_exposes_navigable_proposition_edges(): void
    {
        User::factory()->create();

        $substrate = $this->substrate('growing-space', 'public');
        $participant = new Participant;
        $participant->forceFill([
            'substrate_id' => $substrate->getKey(),
            'callsign' => 'originator',
        ]);
        $participant->save();

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

        $origin = $this->proposition(
            $phase,
            'canon',
            'committal',
            'The Origin establishes a first distinction.',
        );
        $child = $this->proposition(
            $phase,
            'activity',
            'expressive',
            'A new expressive branch begins here.',
        );

        app(PropositionMeasurementService::class)->project(
            $child,
            correspondence: 0.75,
            chirality: -0.25,
            closure: null,
        );

        $supersession = new Supersession;
        $supersession->forceFill([
            'phase_id' => $phase->getKey(),
            'source_proposition_id' => $origin->getKey(),
            'target_proposition_id' => $child->getKey(),
            'type' => 'variant',
        ]);
        $supersession->save();

        $ledger = app(LedgerService::class);
        $ledger->record($origin);
        $ledger->record($child);
        $ledger->record($supersession);

        $response = $this->getJson(
            "/api/substrates/{$substrate->slug}/propositions",
        )->assertOk()->assertJsonCount(2, 'data');

        $response
            ->assertJsonPath('data.0.is_origin', true)
            ->assertJsonPath('data.0.children.0.id', $child->getKey())
            ->assertJsonPath('data.0.children.0.type', 'variant')
            ->assertJsonPath('data.1.parent_ids.0', $origin->getKey())
            ->assertJsonPath('data.1.observables.correspondence', 0.75)
            ->assertJsonPath('data.1.observables.chirality', -0.25)
            ->assertJsonPath('data.1.observables.closure', null)
            ->assertJsonPath('data.1.context_horizon.sequence', 1)
            ->assertJsonPath(
                'data.1.context_horizon.domain',
                'emergence',
            )
            ->assertJsonPath(
                'data.1.observables.calculated_at',
                fn ($value): bool => is_string($value),
            );
    }

    private function substrate(string $slug, string $visibility): Substrate
    {
        $substrate = new Substrate;
        $substrate->forceFill([
            'slug' => $slug,
            'title' => (string) str($slug)->headline(),
            'visibility' => $visibility,
            'configuration' => ['maximum_participants' => 100],
        ]);
        $substrate->save();

        return $substrate;
    }

    private function proposition(
        Phase $phase,
        string $register,
        string $tier,
        string $statement,
    ): Proposition {
        $proposition = new Proposition;
        $proposition->forceFill([
            'phase_id' => $phase->getKey(),
            'register' => $register,
            'tier' => $tier,
            'statement' => $statement,
        ]);
        $proposition->save();

        return $proposition;
    }
}
