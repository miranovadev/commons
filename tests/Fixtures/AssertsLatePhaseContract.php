<?php

namespace Tests\Fixtures;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantPromptLayout;

/** Exercise the same participant-facing boundary with each phase's native diagnostics. */
trait AssertsLatePhaseContract
{
    protected function assertLatePhaseContract(array $plan, PhaseKind $phase, string $sourceId, bool $allowed, string $first, string $second): void
    {
        [$action, $diagnostic, $components, $role] = match ($phase) {
            PhaseKind::Mediate => ['proposition.reconcile', 'correspondence_admissibility', ['shape_topology_direction', 'phase_profile_direction'], 'gate_options'],
            PhaseKind::Bias => ['proposition.reclassify', 'chirality_admissibility', ['orientation_direction', 'attestation_cadence_direction'], 'gate_options'],
            PhaseKind::Order => ['proposition.commit', 'commitment.closure', ['historical_extent_direction', 'historical_rhythm_direction'], 'write_options'],
        };
        $serializer = app(ParticipantContextSerializer::class);
        $visible = $serializer->plan($plan);
        $participant = Phase::findOrFail($plan['phase']['id'])->cycle->participant;
        $packet = $serializer->inference(['participant' => $participant->only(['id', 'callsign', 'designation']), 'plan' => $visible]);
        $prompt = json_decode(app(ParticipantPromptLayout::class)->make($packet)['user'], true);
        // Detailed measurement diagnostics remain in the operational/API plan.
        $this->assertContains($sourceId, array_column($visible[$role], 'id'));
        $state = data_get($visible, $diagnostic);
        $this->assertSame($allowed, $state['admissible']);
        $this->assertSame($first, $state[$components[0]]);
        $this->assertSame($second, $state[$components[1]]);
        foreach ([$packet, $prompt] as $view) {
            $register = $phase === PhaseKind::Order ? 'write' : 'gate';
            $this->assertContains($sourceId, array_column($view[$register]['propositions'], 'id'));
            $this->assertSame($allowed, isset($view['actions']['$defs']['actions'][$action]));
            if ($phase === PhaseKind::Order) {
                $node = collect($view['write']['propositions'])->firstWhere('id', $sourceId);
                $this->assertArrayNotHasKey('commitment', $node);
                $this->assertArrayNotHasKey('observables', $node);
            }
        }
    }
}
