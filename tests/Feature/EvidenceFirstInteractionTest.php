<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\ParticipantReceiptContext;
use App\Services\ContributionDispatcher;
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
    public function test_receipt_then_attestation_unlocks_personal_traversal_without_an_explicit_load_request(PhaseKind $kind, string $register): void
    {
        $f = new ParticipantContextFixture(1, 0);
        $gate = $kind === PhaseKind::Bias ? $f->node($f->gate, 'frontier', position: PhaseKind::Mediate) : $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
        $write = $f->node($gate, $register, position: $kind);
        $other = $f->node($gate, $register, position: $kind);
        $f->evidence($other);
        $f->phase->forceFill(['kind' => $kind, 'gate_proposition_id' => $gate->id, 'write_register' => $register])->save();
        $service = app(ParticipationService::class);
        $targets = function (string $action) use ($f, $service): array {
            $this->publishPotentialFixture($f->viewer);

            return $service->stateFor($f->viewer)['plan']['schema']['$defs']['actions'][$action]['properties']['proposition_id']['enum'] ?? [];
        };
        $this->assertSame([], $targets('instrument.execute'), 'Shared available evidence withholds acquisition');
        $this->assertContains($write->id, $targets('attestation.create'), 'Shared same-Horizon evidence is sufficient');
        $this->assertContains($other->id, $targets('attestation.create'));
        $this->assertNotContains($write->id, $targets('proposition.traverse'));
        $this->assertSame(5, ParticipantReceiptContext::active()->count());
        $this->assertContains($write->id, $targets('attestation.create'));
        $this->assertNotContains($write->id, $targets('proposition.traverse'));
        app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase->fresh(), ['action' => 'attestation.create', 'proposition_id' => $write->id, 'direction' => 'refute']);
        $this->assertNotContains($write->id, $targets('proposition.traverse'));
        $f->phase->forceFill(['completed_at' => now()])->save();
        $f->phase->cycle->forceFill(['completed_at' => now()])->save();
        $cycle = $f->phase->cycle->replicate();
        $cycle->forceFill(['sequence' => (int) $f->viewer->cycles()->max('sequence') + 1, 'completed_at' => null])->save();
        $later = $f->phase->replicate();
        $later->forceFill(['cycle_id' => $cycle->id, 'completed_at' => null])->save();
        $f->phase = $later;
        $this->assertContains($write->id, $targets('proposition.traverse'));
        $next = $service->contribute($f->viewer, $f->phase->id, ['action' => 'proposition.traverse', 'proposition_id' => $write->id]);
        $this->assertSame($kind->value + 1, $next['plan']['phase']['number']);
        $this->assertSame($write->id, $next['plan']['gate_id']);
    }
}
