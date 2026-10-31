<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('integration')]
#[Group('constitutional')]
class ParticipationVisibilityLifecycleTest extends ScriptedParticipationTestCase
{
    public function test_http_context_exposes_local_evidence_and_edges_without_leaking_foreign_or_distant_structure(): void
    {
        $this->startSubstrate();
        $localSlug = $this->substrateSlug;
        $this->substrateSlug = 'foreign-evidence';
        $this->postJson('/api/substrates', ['slug' => $this->substrateSlug, 'title' => 'Foreign', 'visibility' => 'private'])->assertCreated();
        $foreign = $this->joinParticipant('foreign');
        $state = $this->enterParticipant($foreign, true);
        $foreignOrigin = $state['current_proposition_id'];
        $state = $this->discoverGate($foreign);
        $state = $this->contribute($foreign, ['action' => 'proposition.variant', 'statement' => 'Foreign branch content.'], $state);
        $foreignChild = $state['current_proposition_id'];
        $this->observePersistGate($foreign, $state);
        $foreignReceipts = DB::table('receipts')->pluck('id')->all();
        $foreignAttestations = DB::table('attestations')->pluck('id')->all();
        $foreignEdges = DB::table('supersessions')->pluck('id')->all();
        $this->substrateSlug = $localSlug;
        $author = $this->joinParticipant('local-author');
        $state = $this->enterParticipant($author, true);
        $origin = $state['current_proposition_id'];
        $observer = $this->joinParticipant('local-observer');
        $this->enterParticipant($observer);
        $state = $this->discoverGate($author);
        $state = $this->contribute($author, ['action' => 'proposition.variant', 'statement' => 'Visible local distinction.'], $state);
        $signal = $state['current_proposition_id'];
        $state = $this->observePersistGate($author, $state);
        $state = $this->contribute($author, ['action' => 'proposition.variant', 'statement' => 'Distant forward detail.'], $state);
        $distant = $state['current_proposition_id'];
        $state = $this->readParticipation($observer);
        $originCard = $this->visibleProposition($state, $origin);
        $signalCard = $this->visibleProposition($state, $signal);
        $this->assertSame(1, $originCard['attestation_summary']['positive']);
        $this->assertSame(0, $signalCard['attestation_summary']['positive']);
        $this->assertSame([$origin], $signalCard['parent_ids']);
        $this->assertDatabaseHas('supersessions', ['source_proposition_id' => $origin, 'target_proposition_id' => $signal]);
        $localReceipt = DB::table('receipts')->where('receiptable_id', $signal)->sole()->id;
        $this->assertCount(1, $originCard['receipts']);
        $this->assertDatabaseHas('receipts', ['id' => $originCard['receipts'][0]['id'], 'receiptable_id' => $origin]);
        $this->assertContains($localReceipt, array_column($signalCard['receipts'], 'id'));
        $json = json_encode($state);
        foreach ([$foreignOrigin, $foreignChild, $distant, ...$foreignReceipts, ...$foreignAttestations, ...$foreignEdges,
            'Foreign branch content.', 'Distant forward detail.'] as $excluded) {
            $this->assertStringNotContainsString($excluded, $json);
        }
        $before = DB::table('propositions')->count();
        foreach ([$foreignChild, $distant] as $excluded) {
            $this->postContribution($observer, $state['plan']['phase']['id'], [
                'action' => 'attestation.create', 'proposition_id' => $excluded, 'direction' => 'support',
            ])->assertUnprocessable();
        }
        $this->postContribution($observer, $state['plan']['phase']['id'], ['action' => 'receipt.read', 'receipt_id' => $foreignReceipts[0]])->assertUnprocessable();
        $this->assertSame($before, DB::table('propositions')->count());
        $this->assertSame($state['plan']['phase']['id'], $this->readParticipation($observer)['plan']['phase']['id']);
        $this->getJson('/api/substrates/'.$localSlug.'/propositions/'.$foreignChild)->assertNotFound();
    }

    private function observePersistGate(string $author, array $state): array
    {
        $this->assertSame('persist', $state['plan']['phase']['kind']);
        $distinction = $state['plan']['gate_id'];
        $this->contribute($author, ['action' => 'participant.rest']);
        $state = $this->judge($author, $distinction);
        $next = $this->contribute($author, ['action' => 'instrument.execute', 'proposition_id' => $state['plan']['gate_id'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://evidence.example.test/'.$author]], $state);
        $this->assertSame($state['plan']['phase']['id'], $next['plan']['phase']['id']);

        return $next;
    }
}
