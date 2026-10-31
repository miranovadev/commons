<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\EvidenceExecution;
use Tests\Support\ParticipationHttpTestCase;

class GenerativeAcquisitionJourneyTest extends ParticipationHttpTestCase
{
    public function test_instrument_defer_repeats_at_the_same_gate_and_then_generates(): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('attempt-reader');
        $state = $this->enterParticipant($actor, true);
        $gate = $state['plan']['gate_id'];
        for ($i = 0; $i < 3; $i++) {
            $this->assertActionUnavailable($state, 'proposition.variant');
            $state = $this->contribute($actor, ['action' => 'instrument.execute', 'proposition_id' => $gate,
                'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://evidence.example.test/attempt/'.$i]]);
            $execution = EvidenceExecution::whereNotNull('attempt_admitted_at')->latest('id')->firstOrFail();
            $this->assertSame($gate, $execution->proposition_id);
            $this->assertActionAvailable($state, 'proposition.variant');
            $this->assertActionAvailable($state, 'acquisition.defer');
            $this->assertActionUnavailable($state, 'instrument.execute');
            if ($i < 2) {
                $state = $this->contribute($actor, ['action' => 'acquisition.defer', 'execution_id' => $execution->id, 'reason' => 'Read further before developing.']);
                $this->assertNotNull(Cycle::findOrFail($execution->cycle_id)->deferred_at);
                $this->assertNull(Cycle::findOrFail($execution->cycle_id)->rested_at);
                $this->assertDatabaseCount('propositions', 1);
                $this->assertDatabaseCount('exploration_events', 0);
            } else {
                $state = $this->contribute($actor, ['action' => 'proposition.variant', 'execution_id' => $execution->id, 'statement' => 'A considered distinction']);
                $this->assertSame('persist', $state['plan']['phase']['kind']);
            }
        }
        $this->assertDatabaseCount('receipt_consumptions', 0);
        $this->assertSame(3, EvidenceExecution::whereNotNull('attempt_admitted_at')->count());
    }

    public function test_two_admitted_instruments_survive_competing_variant_and_slower_actor_explores_without_acquisition(): void
    {
        $this->startSubstrate();
        $a = $this->joinParticipant('first');
        $initial = $this->enterParticipant($a, true);
        $b = $this->joinParticipant('second');
        $this->enterParticipant($b);
        $gate = $initial['plan']['gate_id'];
        $attempts = [];
        foreach ([$a, $b] as $actor) {
            $this->contribute($actor, ['action' => 'instrument.execute', 'proposition_id' => $gate,
                'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://evidence.example.test/'.$actor]]);
            $attempts[$actor] = EvidenceExecution::whereNotNull('attempt_admitted_at')->latest('id')->firstOrFail();
        }
        foreach ([$a, $b] as $actor) {
            $state = $this->readParticipation($actor);
            $this->assertActionAvailable($state, 'proposition.variant');
            $this->contribute($actor, ['action' => 'proposition.variant', 'execution_id' => $attempts[$actor]->id,
                'statement' => 'Independent observation '.$actor]);
        }
        $slow = $this->joinParticipant('slower');
        $state = $this->enterParticipant($slow);
        $this->assertActionUnavailable($state, 'instrument.execute');
        $this->assertActionAvailable($state, 'attestation.create');
        $target = $state['plan']['schema']['$defs']['actions']['attestation.create']['properties']['proposition_id']['enum'][0];
        $this->contribute($slow, ['action' => 'attestation.create', 'proposition_id' => $target, 'direction' => 'support']);
        $this->assertDatabaseCount('receipt_consumptions', 0);
    }

    public function test_progressive_preparation_deferments_preserve_observations_and_later_generate(): void
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant('progressive');
        $state = $this->enterParticipant($actor, true);
        $state = $this->contribute($actor, ['action' => 'instrument.execute', 'proposition_id' => $state['plan']['gate_id'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://evidence.example.test/reading']]);
        $state = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Examine progressively',
            'execution_id' => $state['plan']['acquisition']['execution_id']]);
        $gate = $state['plan']['gate_id'];
        $this->assertActionUnavailable($state, 'preparation.start');
        $this->contribute($actor, ['action' => 'participant.rest']);
        for ($i = 0; $i < 3; $i++) {
            $this->contribute($actor, $i === 0 ? ['action' => 'attestation.create', 'proposition_id' => $gate, 'direction' => 'support']
                : ['action' => 'proposition.traverse', 'proposition_id' => $gate]);
            $state = $this->readParticipation($actor);
            $this->assertActionUnavailable($state, 'instrument.execute');
            $source = $state['plan']['preparation_options'][0];
            $state = $this->contribute($actor, ['action' => 'preparation.start', 'proposition_id' => $gate, 'purpose' => 'Read next section',
                'artifacts' => [['receipt_id' => $source['receipt_id'], 'artifact_id' => $source['artifact_id'],
                    'read_type' => 'text', 'parameters' => ['offset' => $i * 10, 'max_chars' => 10]]]]);
            $id = $state['plan']['preparation']['execution_id'];
            $this->readAndAcknowledgePreparation($actor, $id);
            $this->contribute($actor, $i < 2 ? ['action' => 'preparation.defer', 'execution_id' => $id, 'reason' => 'Read the next section first']
                : ['action' => 'proposition.variant', 'execution_id' => $id, 'statement' => 'A considered persistence']);
            $this->assertSame($gate, EvidenceExecution::findOrFail($id)->receipt->evidenceExecution->proposition_id);
        }
        $this->assertSame(3, EvidenceExecution::where('type', 'prepare')->where('status', 'completed')->count());
        $this->assertDatabaseCount('exploration_events', 0);
        $this->assertDatabaseCount('receipt_consumptions', 0);
    }
}
