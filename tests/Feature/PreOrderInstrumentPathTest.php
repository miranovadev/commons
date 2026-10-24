<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Services\ContributionDispatcher;
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
        return [[PhaseKind::Distinguish, 'activity'], [PhaseKind::Constrain, 'canon'], [PhaseKind::Disperse, 'activity'], [PhaseKind::Mediate, 'frontier'], [PhaseKind::Bias, 'canon'], [PhaseKind::Order, 'canon']];
    }

    #[DataProvider('phases')]
    public function test_only_acquisition_phases_offer_instrumentation_and_never_on_a_write(PhaseKind $kind, string $register): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $gate = $kind === PhaseKind::Distinguish ? $f->seed : $f->gate;
        $write = $f->node($gate, $register, position: $kind);
        $f->phase->forceFill(['kind' => $kind, 'gate_proposition_id' => $gate->id, 'write_register' => $register])->save();
        $service = app(ParticipationService::class);
        $this->publishPotentialFixture($f->viewer);
        $before = $service->stateFor($f->viewer)['plan'];
        $this->assertSame($kind === PhaseKind::Disperse, isset($before['schema']['$defs']['actions']['instrument.execute']));
        $this->assertSame($kind === PhaseKind::Disperse ? [$gate->id] : [], app(PropositionOptionService::class)->instrumentOptionsFor($f->phase)->pluck('id')->all());
        $this->expectException(\DomainException::class);
        app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase,
            ['action' => 'instrument.execute', 'proposition_id' => $write->id,
                'instrument' => ['kind' => 'citation', 'reference' => $gate->id]]);

    }

    public function test_variant_children_do_not_grant_bias_instrument_authority(): void
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $write = $f->node($f->gate, 'canon', position: PhaseKind::Bias);
        $f->node($write, 'activity', 'variant');
        $f->phase->forceFill(['kind' => PhaseKind::Bias, 'gate_proposition_id' => $f->gate->id, 'write_register' => 'canon'])->save();
        $this->assertSame([], app(PropositionOptionService::class)->instrumentOptionsFor($f->phase->fresh())->all());
    }
}
