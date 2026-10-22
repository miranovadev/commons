<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\InstrumentExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ContributionDispatcher;
use App\Services\LedgerService;
use App\Services\ParticipationService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionOptionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\PropositionBirth;
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
        $receipt = InstrumentExecution::where('phase_id', $phase->id)->sole()->receipt;
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
        $counts = [InstrumentExecution::count(), Receipt::count()];
        try {
            app(ParticipationService::class)->contribute($grounder, $phase->id, $this->instrument($target, $signal));
            $this->fail('The input schema must reject invalid Instrument targets.');
        } catch (InvalidArgumentException) {
            $this->assertNull($phase->fresh()->completed_at);
        }
        try {
            app(ContributionDispatcher::class)->dispatch($grounder, $phase, $this->instrument($target, $signal));
            $this->fail('Direct dispatch must also reject invalid Instrument targets.');
        } catch (DomainException) {
            $this->assertSame($counts, [InstrumentExecution::count(), Receipt::count()]);
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
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'persist-instrument', 'title' => 'Persist grounding',
            'visibility' => 'public', 'configuration' => []])->save();
        $author = new Participant;
        $author->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'author', 'max_read_count' => 25])->save();
        $service = app(ParticipationService::class);
        $state = $service->enter($author, [
            'statement' => 'Origin', 'attestation' => ['direction' => 'support'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin'],
        ]);
        $state = app(ParticipationService::class)->contribute($author, $state['plan']['phase']['id'], [
            'action' => 'proposition.variant', 'statement' => 'Signal',
        ]);
        $signal = Proposition::findOrFail($state['plan']['gate_id']);
        $this->assertArrayHasKey('instrument.execute', $state['plan']['schema']['$defs']['actions']);
        // A foreign Signal permits a new Stance Variant, intentionally without inherited evidence.
        $signalOwner = new Participant;
        $signalOwner->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'signal-owner', 'max_read_count' => 25])->save();
        $ownerCycle = new Cycle;
        $ownerCycle->forceFill(['participant_id' => $signalOwner->id, 'sequence' => 1,
            'context_horizon_id' => $signal->phase->cycle->context_horizon_id,
            'entry_committal_id' => $signal->phase->cycle->entry_committal_id])->save();
        $ownerPhase = $signal->phase->replicate();
        $ownerPhase->cycle_id = $ownerCycle->id;
        $ownerPhase->save();
        $parent = $signal->parents()->sole();
        $signal = $signal->replicate();
        $signal->forceFill(['phase_id' => $ownerPhase->id])->save();
        app(LedgerService::class)->record($signal, $parent);
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $ownerPhase->id, 'source_proposition_id' => $parent->id,
            'target_proposition_id' => $signal->id, 'type' => 'variant'])->save();
        app(LedgerService::class)->record($edge);
        PropositionBirth::complete($signal);
        Phase::findOrFail($state['plan']['phase']['id'])->forceFill(['gate_proposition_id' => $signal->id])->save();
        $author->forceFill(['current_proposition_id' => $signal->id])->save();
        app(PotentialProjectionService::class)->refresh($author->substrate_id);
        $state = $service->contribute($author, $state['plan']['phase']['id'], [
            'action' => 'proposition.variant', 'statement' => 'Stance awaiting evidence',
        ]);
        $write = Proposition::findOrFail($state['plan']['gate_id']);
        $this->receiptFixture($signal);
        $grounder = new Participant;
        $grounder->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'grounder', 'max_read_count' => 25])->save();
        app(PotentialProjectionService::class)->refresh($author->substrate_id);
        $state = $service->enter($grounder);
        $this->assertSame($signal->parents()->sole()->id, $state['plan']['gate_id']);
        $this->assertSame($signal->register, $state['plan']['phase']['write_register']);
        $this->assertArrayHasKey('attestation.create', $state['plan']['schema']['$defs']['actions'], json_encode($state['plan']));
        app(ParticipationService::class)->contribute($grounder, $state['plan']['phase']['id'], [
            'action' => 'attestation.create', 'proposition_id' => $signal->id, 'direction' => 'support',
        ]);

        return [$author, $grounder, $signal, $write];
    }

    private function instrument(Proposition $target, Proposition $reference): array
    {
        return ['action' => 'instrument.execute', 'proposition_id' => $target->id,
            'instrument' => ['kind' => 'citation', 'reference' => $reference->id]];
    }
}
