<?php

namespace Tests\Fixtures;

/** Explicit Persist-only HTTP actions; no fixture writes or inferred failure rows. */
trait PersistExplorationHttpFixture
{
    private function personalExplorationJourney(bool $observerInstrument = false): array
    {
        // Authored HTTP journey with personal discovery and Persist examination.
        $this->startSubstrate();
        $actor = $this->joinParticipant('staggered-explorer');
        $initial = $this->enterParticipant($actor, true);
        $origin = $initial['current_proposition_id'];
        $this->discoverGate($actor);
        $state = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Distinguish opportunity A']);
        $a = $state['current_proposition_id'];
        $originObserver = $this->joinParticipant('distinguish-observer');
        $this->enterParticipant($originObserver);
        $this->observePersist($actor, $a, 'first');
        $state = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Persist development']);
        $persist = $state['current_proposition_id'];
        $this->contribute($actor, ['action' => 'participant.rest']);
        $this->judge($actor, $a);
        // The first observation is still available until Constrain consumes it.
        $this->assertActionUnavailable($this->readParticipation($actor), 'instrument.execute');
        $state = $this->judge($actor, $persist);
        $receipt = $state['plan']['schema']['$defs']['actions']['proposition.reclassify']['properties']['receipt_id']['enum'][0];
        $slot = $this->contribute($actor, ['action' => 'proposition.reclassify', 'proposition_id' => $persist, 'receipt_id' => $receipt]);
        $constrain = $slot['current_proposition_id'];
        // Admit the author's Preparation before another Participant reaches the same empty position.
        $source = $slot['plan']['preparation_options'][0];
        $started = $this->contribute($actor, ['action' => 'preparation.start', 'proposition_id' => $constrain,
            'purpose' => 'Examine this fresh continuation', 'artifacts' => [['receipt_id' => $source['receipt_id'],
                'artifact_id' => $source['artifact_id'], 'read_type' => 'text', 'parameters' => ['max_chars' => 4000]]]]);
        $execution = $started['plan']['preparation']['execution_id'];
        $this->readAndAcknowledgePreparation($actor, $execution);
        $observer = $this->joinParticipant('disperse-observer');
        $this->enterParticipant($observer);
        $this->judge($observer, $a);
        $this->judge($observer, $persist);
        $this->judge($observer, $constrain);
        if ($observerInstrument) {
            $observed = $this->contribute($observer, ['action' => 'instrument.execute', 'proposition_id' => $constrain,
                'instrument' => ['kind' => 'citation', 'reference' => $a]]);
            $this->contribute($observer, ['action' => 'acquisition.defer',
                'execution_id' => $observed['plan']['acquisition']['execution_id'], 'reason' => 'Preserve observation without a new claim']);
        } else {
            $this->resolvePersonalReading($observer);
        }
        $state = $this->contribute($actor, ['action' => 'proposition.variant', 'execution_id' => $execution,
            'statement' => 'Disperse opportunity B']);
        $b = $state['current_proposition_id'];
        $this->contribute($actor, ['action' => 'participant.rest']);
        foreach ([$a, $persist, $constrain] as $visited) {
            $parked = $this->contribute($observer, ['action' => 'proposition.traverse', 'proposition_id' => $visited]);
        }
        $this->contribute($actor, ['action' => 'proposition.traverse', 'proposition_id' => $a]);
        $witness = $this->joinParticipant('disperse-witness');
        $this->enterParticipant($witness);
        $this->judge($witness, $a);
        $this->judge($witness, $persist);
        $witnessState = $this->judge($witness, $constrain);
        $this->contribute($actor, ['action' => 'proposition.traverse', 'proposition_id' => $persist]);
        $this->judge($actor, $constrain);
        $this->judge($actor, $b);
        $state = $this->contribute($actor, ['action' => 'participant.rest']);
        $this->assertDatabaseMissing('evidence_executions', ['proposition_id' => $b]);

        return compact('actor', 'originObserver', 'observer', 'witness', 'origin', 'a', 'persist', 'constrain', 'b', 'slot', 'parked', 'witnessState', 'state');
    }

    private function resolvePersonalReading(string $participant): array
    {
        $state = $this->readParticipation($participant);
        $this->assertActionAvailable($state, 'preparation.start');
        $source = $state['plan']['preparation_options'][0];
        $started = $this->contribute($participant, ['action' => 'preparation.start',
            'proposition_id' => $state['plan']['gate_id'], 'purpose' => 'Personally examine this route.',
            'artifacts' => [['receipt_id' => $source['receipt_id'], 'artifact_id' => $source['artifact_id'],
                'read_type' => 'text', 'parameters' => ['max_chars' => 4000]]]], $state);
        $id = $started['plan']['preparation']['execution_id'];
        $this->readAndAcknowledgePreparation($participant, $id);

        return $this->contribute($participant, ['action' => 'preparation.defer', 'execution_id' => $id,
            'reason' => 'Observation complete; continue through existing structure.']);
    }

    private function observePersist(string $participant, string $gate, string $reference): void
    {
        $before = $this->readParticipation($participant);
        $this->assertSame('persist', $before['plan']['phase']['kind']);
        $after = $this->contribute($participant, ['action' => 'instrument.execute', 'proposition_id' => $gate,
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://evidence.example.test/staggered/'.$reference]]);
        $this->assertSame($before['plan']['phase'], $after['plan']['phase']);
        $this->assertSame($before['cycle'], $after['cycle']);
        $this->assertSame($gate, $after['current_proposition_id']);
    }
}
