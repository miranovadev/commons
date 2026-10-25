<?php

namespace Tests\Fixtures;

/** Explicit Persist-only HTTP actions; no fixture writes or inferred failure rows. */
trait PersistExplorationHttpFixture
{
    private function personalExplorationJourney(): array
    {
        // Authored HTTP journey: observation is an extra Persist action, not
        // an obsolete Distinguish/Disperse progression operation.
        $this->startSubstrate();
        $actor = $this->joinParticipant('staggered-explorer');
        $initial = $this->enterParticipant($actor, true);
        $origin = $initial['current_proposition_id'];
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
        $state = $this->prepareVariant($actor, 'Disperse opportunity B');
        $b = $state['current_proposition_id'];
        $this->contribute($actor, ['action' => 'participant.rest']);
        $this->contribute($actor, ['action' => 'proposition.traverse', 'proposition_id' => $a]);
        $this->assertActionUnavailable($this->readParticipation($actor), 'instrument.execute');
        // The completed Preparation supplies shared evidence for both observers;
        // its source Instrument Receipt remains consumed.
        $observer = $this->joinParticipant('disperse-observer');
        $this->enterParticipant($observer);
        $this->judge($observer, $a);
        $this->judge($observer, $persist);
        $parked = $this->judge($observer, $constrain);
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

        return compact('actor', 'originObserver', 'observer', 'witness', 'origin', 'a', 'b', 'slot', 'parked', 'witnessState', 'state');
    }

    private function judge(string $participant, string $proposition): array
    {
        return $this->contribute($participant, ['action' => 'attestation.create', 'proposition_id' => $proposition, 'direction' => 'support']);
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
