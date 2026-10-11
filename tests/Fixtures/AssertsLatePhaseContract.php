<?php

namespace Tests\Fixtures;

use App\Enums\PhaseKind;
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
            PhaseKind::Order => ['proposition.commit', 'commitment.closure', ['depth_direction', 'cadence_direction'], 'write_options'],
        };
        $serializer = app(ParticipantContextSerializer::class);
        $visible = $serializer->plan($plan);
        $packet = $serializer->inference(['status' => 'ready', 'plan' => $visible]);
        $prompt = json_decode(app(ParticipantPromptLayout::class)->make($packet)['user'], true)['plan'];
        foreach ([$visible, $packet['plan'], $prompt] as $view) {
            $this->assertContains($sourceId, array_column($view[$role], 'id'));
            $this->assertSame($allowed, isset($view['schema']['$defs']['actions'][$action]));
            $state = data_get($view, $diagnostic);
            $this->assertSame($allowed, $state['admissible']);
            $this->assertSame($first, $state[$components[0]]);
            $this->assertSame($second, $state[$components[1]]);
            if ($phase === PhaseKind::Order) {
                $this->assertSame($state, $view['commitment_options'][$sourceId]['closure']);
            }
        }
    }
}
