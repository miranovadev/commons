<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\ContributionDispatcher;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\PhaseGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class SharedReclassificationTest extends TestCase
{
    use RefreshDatabase;

    public static function phases(): array
    {
        return [[PhaseKind::Constrain, 'frontier', 'canon'], [PhaseKind::Bias, 'frontier', 'canon']];
    }

    #[DataProvider('phases')]
    public function test_receipt_and_attestation_allow_a_foreign_participant_one_shared_classification(PhaseKind $kind, string $from, string $to): void
    {
        $f = new ParticipantContextFixture(1, 0);
        $source = $f->node($f->seed, $from, position: PhaseKind::from($kind->value - 1));
        [$actor, $phase] = $this->encounter($f, $source, $kind, $to);
        $action = $kind === PhaseKind::Bias ? 'proposition.reclassify' : 'proposition.reclassify';
        $input = ['action' => $action] + ($kind === PhaseKind::Bias ? ['proposition_id' => $source->id] : []);
        $generation = app(PhaseGenerationService::class);
        $this->assertFalse($generation->available($phase, $action, position: $source));
        $this->receiptFixture($source);
        $this->assertFalse($generation->available($phase, $action, position: $source));
        $attestation = new Attestation;
        $attestation->forceFill(['phase_id' => $source->phase_id, 'proposition_id' => $source->id, 'direction' => 'support'])->save();
        app(LedgerService::class)->record($attestation);
        $this->assertTrue($generation->available($phase, $action, position: $source));
        $input = $this->reclassificationInputFixture($phase, $source);
        $target = app(ContributionDispatcher::class)->dispatch($actor, $phase, $input);
        $this->assertSame($source->statement, $target->statement);
        $this->assertDatabaseHas('reclassification_results', ['source_proposition_id' => $source->id, 'register' => $to, 'target_proposition_id' => $target->id]);
        $this->assertSame(0, $target->attestations()->count());
        [$other, $return] = $this->encounter($f, $source, $kind, $to);
        $plan = app(InputPlanService::class)->forParticipant($other);
        $this->assertContains($target->id, array_column($plan['write_options'], 'id'));
        if ($kind === PhaseKind::Bias) {
            $this->assertNotContains($source->id, $plan['schema']['$defs']['actions'][$action]['properties']['proposition_id']['enum'] ?? []);
        } else {
            $this->assertArrayNotHasKey($action, $plan['schema']['$defs']['actions']);
        }
        $before = Proposition::count();
        try {
            app(ContributionDispatcher::class)->dispatch($other, $return, $input);
            $this->fail('Another participant created the same classification.');
        } catch (\DomainException) {
            $this->assertSame($before, Proposition::count());
        }
        app(ContributionDispatcher::class)->dispatch($other, $return, ['action' => 'attestation.create', 'proposition_id' => $target->id, 'direction' => 'refute']);
        $plan = app(InputPlanService::class)->forParticipant($other);
        $this->assertArrayNotHasKey('proposition.traverse', $plan['schema']['$defs']['actions'], 'A same-cycle Attestation becomes traversal next cycle');
    }

    private function encounter(ParticipantContextFixture $f, Proposition $source, PhaseKind $kind, string $register): array
    {
        $actor = new Participant;
        $actor->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'classifier-'.str()->ulid(), 'max_read_count' => 25])->save();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $actor->id, 'sequence' => 1, 'context_horizon_id' => $source->phase->cycle->context_horizon_id,
            'governing_committal_id' => $source->phase->cycle->governing_committal_id])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => $kind, 'gate_proposition_id' => $source->id, 'write_register' => $register, 'started_at' => now()])->save();

        return [$actor, $phase];
    }
}

