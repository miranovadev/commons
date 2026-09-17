<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Services\ParticipationService;
use App\Services\PropositionOptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class PreOrderInstrumentPathTest extends TestCase
{
    use RefreshDatabase;

    public static function phases(): array
    {
        return [[PhaseKind::Distinguish, 'activity'], [PhaseKind::Mediate, 'frontier']];
    }

    #[DataProvider('phases')]
    public function test_fresh_writes_can_be_grounded_before_attestation(PhaseKind $kind, string $register): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $gate = $kind === PhaseKind::Distinguish ? $f->seed : $f->gate;
        $write = $f->node($gate, $register, position: $kind);
        $f->phase->forceFill(['kind' => $kind, 'gate_proposition_id' => $gate->id, 'write_register' => $register])->save();
        $service = app(ParticipationService::class);
        $this->publishPotentialFixture($f->viewer);
        $before = $service->stateFor($f->viewer)['plan'];
        $this->assertContains($write->id, $before['schema']['$defs']['actions']['instrument.execute']['properties']['proposition_id']['enum']);
        $this->assertNotContains($gate->id, $before['schema']['$defs']['actions']['instrument.execute']['properties']['proposition_id']['enum']);
        $this->publishPotentialFixture($f->viewer);
        $next = $service->contribute($f->viewer, $f->phase->id, ['action' => 'instrument.execute', 'proposition_id' => $write->id,
            'instrument' => ['kind' => 'citation', 'reference' => $gate->id]]);
        $this->assertSame($kind->value + 1, $next['plan']['phase']['number']);
        $this->assertSame($write->id, $next['plan']['gate_id']);
        $this->assertSame('completed', $write->instrumentExecutions()->sole()->status);
    }

    public function test_variant_children_do_not_prevent_pre_order_grounding(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $write = $f->node($f->gate, 'canon', position: PhaseKind::Bias);
        $f->node($write, 'activity', 'variant');
        $f->phase->forceFill(['kind' => PhaseKind::Bias, 'gate_proposition_id' => $f->gate->id, 'write_register' => 'canon'])->save();
        $this->assertContains($write->id, app(PropositionOptionService::class)->instrumentOptionsFor($f->phase->fresh())->pluck('id')->all());
    }
}
