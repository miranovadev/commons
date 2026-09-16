<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\ParticipantReceiptContext;
use App\Services\ContributionDispatcher;
use App\Services\OperationalInstrumentService;
use App\Services\ParticipationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class EvidenceFirstInteractionTest extends TestCase
{
    use RefreshDatabase;

    public static function phases(): array
    {
        return [[PhaseKind::Persist, 'frontier'], [PhaseKind::Bias, 'canon']];
    }

    #[DataProvider('phases')]
    public function test_receipt_then_attestation_unlocks_personal_traversal_without_loading(PhaseKind $kind, string $register): void
    {
        $f = new ParticipantContextFixture(1, 0);
        $gate = $kind === PhaseKind::Bias ? $f->node($f->gate, 'frontier') : $f->gate;
        $write = $f->node($gate, $register);
        $other = $f->node($gate, $register);
        $f->evidence($other);
        $f->phase->forceFill(['kind' => $kind, 'gate_proposition_id' => $gate->id, 'write_register' => $register])->save();
        $service = app(ParticipationService::class);
        $targets = function (string $action) use ($f, $service): array {
            $this->publishPotentialFixture($f->viewer);
            return $service->stateFor($f->viewer)['plan']['schema']['$defs']['actions'][$action]['properties']['proposition_id']['enum'] ?? [];
        };
        $this->assertContains($write->id, $targets('instrument.execute'));
        $this->assertNotContains($write->id, $targets('attestation.create'));
        $this->assertContains($other->id, $targets('attestation.create'));
        $this->assertNotContains($write->id, $targets('proposition.traverse'));
        try {
            app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase->fresh(), ['action' => 'attestation.create', 'proposition_id' => $write->id, 'direction' => 'support']);
            $this->fail('Ungrounded attestation passed.');
        } catch (\DomainException) {
            $this->assertSame(0, $write->attestations()->count());
        }
        app(OperationalInstrumentService::class)->execute($f->phase->fresh(), $write, ['kind' => 'citation', 'reference' => $gate->id]);
        $this->assertSame(0, ParticipantReceiptContext::count());
        $this->assertContains($write->id, $targets('attestation.create'));
        $this->assertNotContains($write->id, $targets('proposition.traverse'));
        app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase->fresh(), ['action' => 'attestation.create', 'proposition_id' => $write->id, 'direction' => 'refute']);
        $this->assertContains($write->id, $targets('proposition.traverse'));
        $next = $service->contribute($f->viewer, $f->phase->id, ['action' => 'proposition.traverse', 'proposition_id' => $write->id]);
        $this->assertSame($kind->value + 1, $next['plan']['phase']['number']);
        $this->assertSame($write->id, $next['plan']['gate_id']);
    }
}
