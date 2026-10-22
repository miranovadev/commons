<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Exceptions\ArtifactStorageException;
use App\Models\InstrumentExecution;
use App\Models\Participant;
use App\Models\Receipt;
use App\Services\ContributionDispatcher;
use App\Services\Evidence\HyperlinkVerifier;
use App\Services\ParticipationService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionMeasurementService;
use App\Services\PropositionOptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

class EpistemicInstrumentationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): ParticipantContextFixture
    {
        $f = new ParticipantContextFixture(1, 0, false);
        $gate = $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
        $f->phase->forceFill(['kind' => PhaseKind::Persist, 'gate_proposition_id' => $gate->id, 'write_register' => 'frontier'])->save();
        $f->viewer->forceFill(['current_proposition_id' => $gate->id])->save();
        $f->gate = $gate;
        app(PropositionMeasurementService::class)->observeChirality($gate);
        app(PotentialProjectionService::class)->refresh($f->viewer->substrate_id);

        return $f;
    }

    private function input(ParticipantContextFixture $f): array
    {
        return ['action' => 'instrument.execute', 'proposition_id' => $f->gate->id,
            'instrument' => ['kind' => 'citation', 'reference' => $f->seed->id]];
    }

    private function structuralSnapshot(): array
    {
        $snapshot = [];
        foreach (['propositions', 'supersessions', 'phases', 'cycles', 'attestations', 'phase_profile_observations', 'chirality_observations'] as $table) {
            $snapshot[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }
        $snapshot['measurements'] = DB::table('proposition_measurements')->get()->map(function ($row) {
            $values = (array) $row;
            unset($values['calculated_at'], $values['updated_at']);

            return $values;
        })->all();

        return $snapshot;
    }

    public function test_execution_refreshes_same_phase_without_structural_or_measurement_changes(): void
    {
        $f = $this->fixture();
        $service = app(ParticipationService::class);
        $before = $service->stateFor($f->viewer);
        $snapshot = $this->structuralSnapshot();
        $after = $service->contribute($f->viewer, $f->phase->id, $this->input($f));
        $this->assertSame($snapshot, $this->structuralSnapshot());
        $this->assertSame($before['plan']['phase']['id'], $after['plan']['phase']['id']);
        $this->assertSame($f->gate->id, $after['plan']['gate_id']);
        $this->assertSame($f->gate->id, $f->viewer->fresh()->current_proposition_id);
        $this->assertArrayHasKey('instrument.execute', $before['plan']['schema']['$defs']['actions']);
        $this->assertArrayNotHasKey('instrument.execute', $after['plan']['schema']['$defs']['actions']);
        $this->assertSame(array_values(array_diff(array_keys($before['plan']['schema']['$defs']['actions']), ['instrument.execute'])), array_keys($after['plan']['schema']['$defs']['actions']));
        $receipt = InstrumentExecution::where('phase_id', $f->phase->id)->sole()->receipt;
        $this->assertSame($f->gate->id, $receipt->receiptable_id);
        $this->assertContains($receipt->id, array_column($after['plan']['receipts'], 'id'));
        $this->assertDatabaseHas('participant_receipt_contexts', ['receipt_id' => $receipt->id, 'participant_id' => $f->viewer->id, 'cleared_at' => null]);
    }

    public function test_only_persist_offers_exact_gate(): void
    {
        $f = $this->fixture();
        foreach (PhaseKind::cases() as $kind) {
            $f->phase->kind = $kind;
            $ids = app(PropositionOptionService::class)->instrumentOptionsFor($f->phase)->pluck('id')->all();
            $this->assertSame($kind === PhaseKind::Persist ? [$f->gate->id] : [], $ids);
            $this->assertSame($kind === PhaseKind::Persist, in_array('instrument.execute', config("phases.{$kind->value}.actions"), true));
        }
    }

    public function test_second_completed_request_is_rejected_under_dispatch_lock(): void
    {
        $f = $this->fixture();
        $dispatcher = app(ContributionDispatcher::class);
        $input = $this->input($f);
        // Both requests observed the same unused allowance before either persisted.
        $first = $dispatcher->observeInstrument($f->viewer, $f->phase, $input);
        $second = $dispatcher->observeInstrument($f->viewer, $f->phase, $input);
        $dispatcher->dispatch($f->viewer, $f->phase, $input, $first);
        $count = Receipt::count();
        try {
            $dispatcher->dispatch($f->viewer, $f->phase, $input, $second);
            $this->fail('The stale prepared request must conflict.');
        } catch (ConflictHttpException) {
            $this->assertSame($count, Receipt::count());
            $this->assertSame(1, InstrumentExecution::where('phase_id', $f->phase->id)->where('status', 'completed')->count());
        }
    }

    public function test_failed_verification_consumes_allowance_but_infrastructure_failure_does_not(): void
    {
        $f = $this->fixture();
        $input = [...$this->input($f), 'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/failure']];
        $this->mock(HyperlinkVerifier::class, fn ($mock) => $mock->shouldReceive('verify')->once()
            ->andThrow(new ArtifactStorageException('Storage unavailable')));
        try {
            app(ParticipationService::class)->contribute($f->viewer, $f->phase->id, $input);
            $this->fail('Infrastructure failure must abort.');
        } catch (ArtifactStorageException) {
            $this->assertSame(0, InstrumentExecution::where('phase_id', $f->phase->id)->count());
            $this->assertCount(1, app(PropositionOptionService::class)->instrumentOptionsFor($f->phase));
        }
        $this->mock(HyperlinkVerifier::class, fn ($mock) => $mock->shouldReceive('verify')->once()
            ->andThrow(new \DomainException('Verification failed')));
        $state = app(ParticipationService::class)->contribute($f->viewer, $f->phase->id, $input);
        $execution = InstrumentExecution::where('phase_id', $f->phase->id)->sole();
        $this->assertSame('completed', $execution->status);
        $this->assertFalse($execution->receipt->metadata['content_verified']);
        $this->assertArrayNotHasKey('instrument.execute', $state['plan']['schema']['$defs']['actions']);
    }

    public function test_allowance_is_cycle_owned_and_independent_of_other_participants_and_loading(): void
    {
        $f = $this->fixture();
        app(ParticipationService::class)->contribute($f->viewer, $f->phase->id, $this->input($f));
        $receipt = InstrumentExecution::where('phase_id', $f->phase->id)->sole()->receipt;
        DB::table('participant_receipt_contexts')->where('receipt_id', $receipt->id)->update(['cleared_at' => now()]);
        $this->assertCount(0, app(PropositionOptionService::class)->instrumentOptionsFor($f->phase));
        $other = (new Participant)->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'other', 'current_proposition_id' => $f->gate->id]);
        $other->save();
        $cycle = $f->phase->cycle->replicate();
        $cycle->participant_id = $other->id;
        $cycle->save();
        $phase = $f->phase->replicate();
        $phase->cycle_id = $cycle->id;
        $phase->save();
        $this->assertCount(1, app(PropositionOptionService::class)->instrumentOptionsFor($phase->fresh()));
        $f->phase->cycle->forceFill(['completed_at' => now()])->save();
        $next = $f->phase->cycle->replicate();
        $next->forceFill(['completed_at' => null, 'sequence' => $next->sequence + 1])->save();
        $nextPhase = $f->phase->replicate();
        $nextPhase->cycle_id = $next->id;
        $nextPhase->save();
        $this->assertCount(1, app(PropositionOptionService::class)->instrumentOptionsFor($nextPhase->fresh()));
    }

    public function test_instrument_then_genuine_persist_action_advances_and_records_birth(): void
    {
        $f = $this->fixture();
        // A different author may develop this distinction, under ordinary generation rules.
        $owner = (new Participant)->forceFill(['substrate_id' => $f->substrate->id, 'callsign' => 'gate-author']);
        $owner->save();
        $f->gate->phase->cycle->forceFill(['participant_id' => $owner->id])->save();
        app(PotentialProjectionService::class)->refresh($f->substrate->id);
        $service = app(ParticipationService::class);
        $state = $service->contribute($f->viewer, $f->phase->id, $this->input($f));
        $this->assertArrayHasKey('proposition.variant', $state['plan']['schema']['$defs']['actions']);
        $births = DB::table('phase_profile_observations')->count();
        $after = $service->contribute($f->viewer, $f->phase->id, ['action' => 'proposition.variant', 'statement' => 'A lawful structural continuation']);
        $this->assertSame('constrain', $after['plan']['phase']['kind']);
        $this->assertNotSame($f->gate->id, $after['plan']['gate_id']);
        $this->assertSame($births + 1, DB::table('phase_profile_observations')->count());
    }
}
