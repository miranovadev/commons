<?php

namespace Tests\Diagnostics;

use App\Models\EvidenceExecution;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\CorrespondenceGateService;
use App\Services\ExplorationEventService;
use App\Services\Observables\PhaseProfileService;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ParticipationHttpTestCase;

/** Completed failed Preparations remain observations in both publication outcomes. */
class SoloFailedPreparationFrontierTest extends ParticipationHttpTestCase
{
    public static function restProofs(): array
    {
        return ['current witness' => [false], 'stale witness' => [true]];
    }

    #[DataProvider('restProofs')]
    public function test_judged_signal_after_failed_preparations(bool $stale): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('solo-failure');
        $initial = $this->enterParticipant($actor, true);
        $origin = $initial['plan']['gate_id'];
        $this->contribute($actor, ['action' => 'instrument.execute', 'proposition_id' => $origin,
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://evidence.example.test/solo-source']]);
        $source = Receipt::sole();
        $variant = fn (string $statement) => $this->contribute($actor,
            ['action' => 'proposition.variant', 'statement' => $statement])['plan']['gate_id'];
        $judge = fn (string $id) => $this->contribute($actor,
            ['action' => 'attestation.create', 'proposition_id' => $id, 'direction' => 'refute']);
        $move = fn (string $id) => $this->contribute($actor,
            ['action' => 'proposition.traverse', 'proposition_id' => $id]);
        $rest = fn () => $this->contribute($actor, ['action' => 'participant.rest']);

        $distinction = $variant('First distinction');
        $this->attemptUnavailableMaterial($actor, $source);
        $persist = $variant('First persistence');
        $rest();
        $judge($distinction);
        $judge($persist);
        $state = $this->contribute($actor, ['action' => 'proposition.reclassify',
            'proposition_id' => $persist, 'receipt_id' => $source->id]);
        $canon = $state['plan']['gate_id'];
        $this->attemptUnavailableMaterial($actor, $source);
        $signal = $variant('First signal');
        $rest();
        $move($distinction);
        $move($persist);
        $judge($canon);
        $judge($signal);
        if ($stale) {
            // Isolate the existing field-version race. A discarded proof leaves the
            // same unfinished route used by the historical Revision measurement fixture.
            $events = app(ExplorationEventService::class);
            $probe = \Mockery::mock($events);
            $probe->shouldReceive('inspect')->andReturnUsing(function ($phase) use ($events) {
                $proof = $events->inspect($phase);
                $proof['field_version']--;

                return $proof;
            });
            $this->instance(ExplorationEventService::class, $probe);
        }
        $entry = $rest();
        if (! $stale) {
            $this->assertDatabaseHas('exploration_events', ['proposition_id' => $signal]);
            $this->assertDatabaseCount('exploration_events', 1);
            $this->assertTrue($this->visibleProposition($entry, $distinction)['explored']);
            $this->assertActionAvailable($entry, 'instrument.execute');
            $this->assertDatabaseMissing('supersessions', ['type' => 'reconcile']);

            return;
        }
        $this->assertDatabaseCount('exploration_events', 0);

        for ($revision = 1; $revision <= 2; $revision++) {
            $move($distinction);
            $move($persist);
            $move($canon);
            $state = $this->readParticipation($actor);
            $this->assertActionAvailable($state, 'proposition.revise');
            $this->assertFalse($this->visibleProposition($state, $signal)['explored']);
            $state = $this->contribute($actor, ['action' => 'proposition.revise',
                'proposition_id' => $signal, 'statement' => 'Reconsider signal '.$revision]);
            $signal = $state['plan']['gate_id'];
            $node = Proposition::findOrFail($signal);
            $this->assertTrue(app(CorrespondenceGateService::class)->evaluate($node)['admissible']);
            $profile = app(PhaseProfileService::class)->inspect($node);
            $this->assertSame(-1 / 7, $profile['phase_profile']);
            $this->assertSame([null, 0.0, 1.0, (float) $revision, null, null, null], $profile['current']['means']);
            $this->assertSame(['participant.rest'], array_keys($this->actionDefinitions($state)));
            $this->assertSame([$signal], array_column($state['plan']['gate_options'], 'id'));
            $rest();
            $move($distinction);
            $move($persist);
            $move($canon);
            $judge($signal);
            $state = $rest();
            $this->assertActionUnavailable($state, 'proposition.variant');
        }
        $this->assertDatabaseCount('participants', 1);
        $this->assertDatabaseCount('exploration_events', 0);
        $this->assertDatabaseMissing('supersessions', ['type' => 'reconcile']);
        $this->assertDatabaseMissing('phases', ['kind' => 6]);
    }

    /** Simulate temporary object-store unavailability around an actual reading request. */
    private function attemptUnavailableMaterial(string $actor, Receipt $source): void
    {
        $artifact = $source->metadata['artifacts'][0];
        $disk = Storage::disk('artifacts');
        $body = $disk->get($artifact['path']);
        $before = $this->readParticipation($actor);
        $started = $this->contribute($actor, ['action' => 'preparation.start',
            'proposition_id' => $before['plan']['gate_id'], 'purpose' => 'Read preserved source',
            'artifacts' => [['receipt_id' => $source->id, 'artifact_id' => $artifact['hash'], 'read_type' => 'text']]]);
        $id = $started['plan']['preparation']['execution_id'];
        $disk->delete($artifact['path']);
        try {
            $this->getJson('/api/substrates/'.$this->substrateSlug.'/participants/'.$actor.'/preparations/'.$id.'/reading')
                ->assertStatus(422);
        } finally {
            $disk->put($artifact['path'], $body);
        }
        $after = $this->readParticipation($actor);
        $this->assertSame($before['plan']['phase'], $after['plan']['phase']);
        $this->assertSame($before['cycle'], $after['cycle']);
        $this->assertSame('awaiting_resolution', $after['plan']['acquisition']['status']);
        $execution = EvidenceExecution::findOrFail($id);
        $this->assertSame('failed', $execution->status);
        $this->assertFalse($execution->receipt->metadata['content_verified']);
        $this->assertNull($execution->receipt->signature);
    }
}
