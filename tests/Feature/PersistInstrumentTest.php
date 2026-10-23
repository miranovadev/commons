<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\EvidenceExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\ContributionDispatcher;
use App\Services\ParticipationService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionOptionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class PersistInstrumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_gate_observation_stays_in_persist_and_receipt_can_be_consumed_by_later_reclassification(): void
    {
        [$author, $grounder, $signal, $write] = $this->fixture();
        $service = app(ParticipationService::class);
        $state = $service->stateFor($grounder);
        $phase = Phase::findOrFail($state['plan']['phase']['id']);
        $this->assertSame([$signal->id], $state['plan']['schema']['$defs']['actions']['instrument.execute']['properties']['proposition_id']['enum']);
        $next = $service->contribute($grounder, $phase->id, $this->instrument($signal, $write));
        $receipt = EvidenceExecution::where('phase_id', $phase->id)->sole()->receipt;
        $this->assertSame($signal->id, $receipt->receiptable_id);
        $this->assertSame($phase->id, $next['plan']['phase']['id']);
        $this->assertSame($signal->id, $next['plan']['gate_id']);
        $this->assertNull($phase->fresh()->completed_at);
        $this->assertDatabaseMissing('receipt_consumptions', ['receipt_id' => $receipt->id]);
        $this->assertContains($receipt->id, array_column($next['plan']['receipts'], 'id'));
        $this->assertArrayNotHasKey('instrument.execute', $next['plan']['schema']['$defs']['actions']);
        // The author already has a lawful Constrain Write. Its independent
        // Attestation requirement is unchanged, and the shared Receipt is attachable.
        $this->classificationEvidenceFixture($write);
        app(PotentialProjectionService::class)->refresh($author->substrate_id);
        $ownerState = $service->stateFor($author);
        $after = $service->contribute($author, $ownerState['plan']['phase']['id'], [
            'action' => 'proposition.reclassify', 'proposition_id' => $write->id, 'receipt_id' => $receipt->id,
        ]);
        $this->assertSame('disperse', $after['plan']['phase']['kind']);
        $this->assertDatabaseHas('receipt_consumptions', ['receipt_id' => $receipt->id]);
        $this->assertSame($signal->id, $receipt->fresh()->receiptable_id);
        $this->assertCount(0, app(PropositionOptionService::class)->instrumentOptionsFor($phase->fresh()));
    }

    public static function invalidTargets(): array
    {
        return array_combine(['write', 'ancestor', 'unrelated-write', 'wrong-register'],
            array_map(fn ($target) => [$target], ['write', 'ancestor', 'unrelated-write', 'wrong-register']));
    }

    #[DataProvider('invalidTargets')]
    public function test_schema_and_dispatcher_reject_invalid_persist_instrument_targets(string $kind): void
    {
        [, $grounder, $signal, $write] = $this->fixture();
        $target = match ($kind) {
            'write' => $write,
            'ancestor' => $signal->parents()->sole(),
            default => $write->replicate(),
        };
        if (! $target->exists) {
            $target->register = $kind === 'wrong-register' ? $signal->register : $write->register;
            $target->save();
            if ($kind === 'wrong-register') {
                $signal->children()->attach($target->id, [
                    'id' => (string) str()->ulid(), 'phase_id' => $write->phase_id, 'type' => 'variant',
                ]);
            }
        }
        $service = app(ParticipationService::class);
        $state = $service->stateFor($grounder);
        $phase = Phase::findOrFail($state['plan']['phase']['id']);
        $this->assertNotContains($target->id, $state['plan']['schema']['$defs']['actions']['instrument.execute']['properties']['proposition_id']['enum']);
        $counts = [EvidenceExecution::count(), Receipt::count()];
        try {
            app(ParticipationService::class)->contribute($grounder, $phase->id, $this->instrument($target, $signal));
            $this->fail('The input schema must reject invalid Instrument targets.');
        } catch (InvalidArgumentException) {
            $this->assertNull($phase->fresh()->completed_at);
        }
        try {
            app(ContributionDispatcher::class)->dispatch($grounder, $phase, $this->instrument($target, $signal));
            $this->fail('Direct dispatch must also reject invalid Instrument targets.');
        } catch (DomainException|ConflictHttpException) {
            $this->assertSame($counts, [EvidenceExecution::count(), Receipt::count()]);
        }
    }

    public function test_instrument_prevents_tragedy_when_it_is_the_only_remaining_action(): void
    {
        [, $grounder, $signal, $write] = $this->fixture();
        // Isolate action exhaustion from other Persist actions, without mocking options.
        config(['phases.2.actions' => ['instrument.execute']]);
        $plan = app(ParticipationService::class)->stateFor($grounder)['plan'];
        $this->assertFalse($plan['rest_available']);
        $this->assertSame(['instrument.execute'], array_keys($plan['schema']['$defs']['actions']));
        // Completing the observation uses the only Instrument allowance.
        app(ParticipationService::class)->contribute($grounder, $plan['phase']['id'], $this->instrument($signal, $write));
        $plan = app(ParticipationService::class)->stateFor($grounder)['plan'];
        $this->assertTrue($plan['rest_available']);
        $this->assertSame(['participant.rest'], array_keys($plan['schema']['$defs']['actions']));
    }

    private function fixture(): array
    {
        // Structural history supplies an existing Write; acquisition is exercised
        // by a separate Participant in a live Persist occurrence.
        $f = new ParticipantContextFixture(1, 0, false);
        $signal = $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
        $write = $f->node($signal, 'frontier', position: PhaseKind::Persist);
        $f->phase->forceFill(['kind' => PhaseKind::Constrain,
            'gate_proposition_id' => $write->id, 'write_register' => 'canon'])->save();
        $author = $f->viewer;
        $author->forceFill(['current_proposition_id' => $write->id])->save();
        $grounder = (new Participant)->forceFill(['substrate_id' => $f->substrate->id,
            'callsign' => 'grounder', 'current_proposition_id' => $signal->id]);
        $grounder->save();
        $cycle = $f->phase->cycle->replicate();
        $cycle->forceFill(['participant_id' => $grounder->id, 'sequence' => 1])->save();
        $phase = $f->phase->replicate();
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Persist,
            'gate_proposition_id' => $signal->id, 'write_register' => 'frontier'])->save();
        app(PotentialProjectionService::class)->refresh($f->substrate->id);

        return [$author, $grounder, $signal, $write];
    }

    private function instrument(Proposition $target, Proposition $reference): array
    {
        return ['action' => 'instrument.execute', 'proposition_id' => $target->id,
            'instrument' => ['kind' => 'citation', 'reference' => $reference->id]];
    }
}
