<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Connection;
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
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipationService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionOptionService;
use App\Services\PropositionStateService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersistInstrumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_persist_instrument_receipt_survives_as_constrain_gate_and_unlocks_owner_reclassification(): void
    {
        [$author, $grounder, $signal, $write] = $this->fixture();
        $service = app(ParticipationService::class);
        $before = $service->stateFor($author);
        $this->assertSame('constrain', $before['plan']['phase']['kind']);
        $this->assertSame($write->id, $before['plan']['gate_id']);
        $this->assertArrayNotHasKey('proposition.reclassify', $before['plan']['schema']['$defs']['actions']);
        $this->assertCount(0, app(PropositionStateService::class)->receipts($write));
        try {
            app(ContributionDispatcher::class)->dispatch($author, Phase::findOrFail($before['plan']['phase']['id']), [
                'action' => 'proposition.reclassify',
            ]);
            $this->fail('Constrain must reject reclassification without a Receipt.');
        } catch (DomainException) {
            $this->assertNull(Phase::findOrFail($before['plan']['phase']['id'])->completed_at);
        }

        $state = $service->stateFor($grounder);
        $phase = Phase::findOrFail($state['plan']['phase']['id']);
        $this->assertSame(PhaseKind::Persist, $phase->kind);
        $this->assertSame($phase->write_register, $write->register);
        $this->assertSame([$write->id], array_column($service->workspaceFor($grounder)['plan']['instrument_options'], 'id'));
        $this->assertSame([$write->id], array_column($state['plan']['write_options'], 'id'));
        $this->assertSame([$write->id], $state['plan']['schema']['$defs']['actions']['instrument.execute']['properties']['proposition_id']['enum']);
        $this->assertSame([$write->id], app(PropositionOptionService::class)->instrumentOptionsFor($phase)->pluck('id')->all());
        $packet = app(ParticipantPromptFactory::class)->make(new Connection, $state);
        $this->assertSame($state['plan']['schema'], $packet['schema']);

        $receiptCount = Receipt::count();
        $next = app(ParticipationService::class)->contribute($grounder, $phase->id, $this->instrument($write, $signal));
        $execution = InstrumentExecution::where('phase_id', $phase->id)->sole();
        $receipt = $execution->receipt;
        $this->assertSame($receiptCount + 1, Receipt::count());
        $this->assertSame('completed', $execution->status);
        $this->assertNotNull($execution->completed_at);
        $this->assertSame($write->id, $execution->proposition_id);
        $this->assertSame($write->id, $receipt->receiptable_id);
        $this->assertSame($write->getMorphClass(), $receipt->receiptable_type);
        $this->assertSame('citation', $receipt->type);
        $this->assertSame($signal->id, $receipt->reference);
        $this->assertSame('constrain', $next['plan']['phase']['kind']);
        $this->assertSame($write->id, $next['plan']['gate_id']);
        $this->assertSame($write->id, $grounder->fresh()->current_proposition_id);
        $this->assertNotNull($phase->fresh()->completed_at);
        $this->assertSame([$receipt->id], app(PropositionStateService::class)->receipts($write->fresh())->pluck('id')->all());
        $this->assertContains($receipt->id, array_column($next['plan']['receipts'], 'id'));

        // Grounding does not confer ownership or require loading Reinforcement.
        $this->assertArrayNotHasKey('proposition.reclassify', $next['plan']['schema']['$defs']['actions']);
        $ownerState = $service->stateFor($author);
        $this->assertArrayNotHasKey('proposition.reclassify', $ownerState['plan']['schema']['$defs']['actions']);
        $this->classificationEvidenceFixture($write);
        app(PotentialProjectionService::class)->refresh($author->substrate_id);
        $ownerState = $service->stateFor($author);
        $this->assertArrayHasKey('proposition.reclassify', $service->stateFor($grounder)['plan']['schema']['$defs']['actions']);
        $this->assertArrayHasKey('proposition.reclassify', $ownerState['plan']['schema']['$defs']['actions']);
        $after = app(ParticipationService::class)->contribute($author, $ownerState['plan']['phase']['id'], $this->reclassificationInputFixture(Phase::findOrFail($ownerState['plan']['phase']['id']), $write));
        $this->assertSame('disperse', $after['plan']['phase']['kind']);
        $this->assertSame($write->id, $receipt->fresh()->receiptable_id);
    }

    public static function invalidTargets(): array
    {
        return array_combine(['gate', 'ancestor', 'unrelated-write', 'wrong-register'],
            array_map(fn ($target) => [$target], ['gate', 'ancestor', 'unrelated-write', 'wrong-register']));
    }

    #[DataProvider('invalidTargets')]
    public function test_schema_and_dispatcher_reject_invalid_persist_instrument_targets(string $kind): void
    {
        [, $grounder, $signal, $write] = $this->fixture();
        $target = match ($kind) {
            'gate' => $signal,
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
        [, $grounder, , $write] = $this->fixture();
        // Isolate action exhaustion from other Persist actions, without mocking options.
        config(['phases.2.actions' => ['instrument.execute']]);
        $plan = app(ParticipationService::class)->stateFor($grounder)['plan'];
        $this->assertFalse($plan['rest_available']);
        $this->assertSame(['instrument.execute'], array_keys($plan['schema']['$defs']['actions']));
        // Removing the only Write leaves no valid Instrument target.
        $write->forceFill(['register' => 'concord'])->save();
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
        $this->assertArrayNotHasKey('instrument.execute', $state['plan']['schema']['$defs']['actions']);
        // A foreign Signal permits a new Stance Variant, intentionally without inherited evidence.
        $signalOwner = new Participant;
        $signalOwner->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'signal-owner', 'max_read_count' => 25])->save();
        $ownerCycle = new Cycle;
        $ownerCycle->forceFill(['participant_id' => $signalOwner->id, 'sequence' => 1,
            'context_horizon_id' => $signal->phase->cycle->context_horizon_id,
            'governing_committal_id' => $signal->phase->cycle->governing_committal_id])->save();
        $ownerPhase = $signal->phase->replicate();
        $ownerPhase->cycle_id = $ownerCycle->id;
        $ownerPhase->save();
        $parent = $signal->parents()->sole();
        $signal = $signal->replicate();
        $signal->forceFill(['phase_id' => $ownerPhase->id])->save();
        app(LedgerService::class)->record($signal);
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $ownerPhase->id, 'source_proposition_id' => $parent->id,
            'target_proposition_id' => $signal->id, 'type' => 'variant'])->save();
        app(LedgerService::class)->record($edge);
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

