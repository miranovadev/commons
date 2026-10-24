<?php

namespace Tests\Feature;

use App\Models\EvidenceExecution;
use App\Models\Receipt;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ParticipationHttpTestCase;

#[Group('integration')]
class DisperseFallbackHttpTest extends ParticipationHttpTestCase
{
    public function test_shared_persist_evidence_consumption_leaves_disperse_fallback_then_generation(): void
    {
        $this->startSubstrate();
        $observer = $this->joinParticipant('Evidence observer');
        $initial = $this->enterParticipant($observer, true);
        $persist = $this->contribute($observer, ['action' => 'proposition.variant', 'statement' => 'Investigate a shared distinction.']);
        $this->contribute($observer, ['action' => 'instrument.execute', 'proposition_id' => $persist['plan']['gate_id'],
            'instrument' => ['kind' => 'citation', 'reference' => $initial['current_proposition_id']]]);
        $source = Receipt::where('type', 'citation')->sole();

        $actor = $this->joinParticipant('Shared evidence developer');
        $this->enterParticipant($actor);
        $sharedPersist = $this->contribute($actor, ['action' => 'attestation.create',
            'proposition_id' => $persist['plan']['gate_id'], 'direction' => 'support']);
        $this->assertSame('persist', $sharedPersist['plan']['phase']['kind']);
        $this->assertActionUnavailable($sharedPersist, 'instrument.execute');
        $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Develop the shared observation.']);
        $disperse = $this->driveUntil($actor, fn ($state) => $state['plan']['phase']['kind'] === 'disperse', 5);
        $this->assertNotNull($source->fresh()->consumption);
        $this->assertSame([], $disperse['plan']['preparation_options']);
        $this->assertActionAvailable($disperse, 'instrument.execute');
        $this->assertActionUnavailable($disperse, 'proposition.variant');
        $this->assertActionUnavailable($disperse, 'preparation.start');
        $acquired = $this->contribute($actor, ['action' => 'instrument.execute', 'proposition_id' => $disperse['plan']['gate_id'],
            'instrument' => ['kind' => 'proposition_search']]);
        $this->assertSame($disperse['plan']['phase'], $acquired['plan']['phase']);
        $this->assertSame($disperse['cycle'], $acquired['cycle']);
        $this->assertSame($disperse['plan']['gate_id'], $acquired['plan']['gate_id']);
        $execution = EvidenceExecution::where('cycle_id', $disperse['cycle']['id'])->sole();
        $this->assertSame('instrument', $execution->type);
        $this->assertNull($execution->receipt->consumption);
        $this->assertActionAvailable($acquired, 'proposition.variant');
        $this->assertActionUnavailable($acquired, 'instrument.execute');
        $mediate = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Express the acquired evidence.']);
        $this->assertSame('mediate', $mediate['plan']['phase']['kind']);
        $this->assertSame($disperse['cycle']['id'], $mediate['cycle']['id']);
    }
}
