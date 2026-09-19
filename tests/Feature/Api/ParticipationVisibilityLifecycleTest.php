<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ParticipationHttpTestCase;

#[Group('http-participation')]
class ParticipationVisibilityLifecycleTest extends ParticipationHttpTestCase
{
    public function test_http_context_exposes_local_evidence_and_edges_without_leaking_foreign_or_distant_structure(): void
    {
        $this->startSubstrate();
        $localSlug = $this->substrateSlug;
        $this->substrateSlug = 'foreign-evidence';
        $this->postJson('/api/substrates', ['slug' => $this->substrateSlug, 'title' => 'Foreign', 'visibility' => 'private'])->assertCreated();
        $foreign = $this->joinParticipant('foreign');
        $foreignOrigin = $this->enterParticipant($foreign, true)['current_proposition_id'];
        $foreignChild = $this->contribute($foreign, ['action' => 'proposition.variant', 'statement' => 'Foreign branch content.'])['current_proposition_id'];
        $foreignReceipts = DB::table('receipts')->pluck('id')->all();
        $foreignAttestations = DB::table('attestations')->pluck('id')->all();
        $foreignEdges = DB::table('supersessions')->pluck('id')->all();
        $this->substrateSlug = $localSlug;
        $author = $this->joinParticipant('local-author');
        $origin = $this->enterParticipant($author, true)['current_proposition_id'];
        $observer = $this->joinParticipant('local-observer');
        $this->enterParticipant($observer);
        $signal = $this->contribute($author, ['action' => 'proposition.variant', 'statement' => 'Visible local distinction.'])['current_proposition_id'];
        $distant = $this->contribute($author, ['action' => 'proposition.variant', 'statement' => 'Distant forward detail.'])['current_proposition_id'];
        $state = $this->readParticipation($observer);
        $originCard = $this->visibleProposition($state, $origin);
        $signalCard = $this->visibleProposition($state, $signal);
        $this->assertSame(1, $originCard['attestation_summary']['positive']);
        $this->assertSame(0, $signalCard['attestation_summary']['positive']);
        $this->assertSame([$origin], $signalCard['parent_ids']);
        $this->assertDatabaseHas('supersessions', ['source_proposition_id' => $origin, 'target_proposition_id' => $signal]);
        $localReceipt = DB::table('receipts')->whereNotIn('id', $foreignReceipts)->sole()->id;
        $this->assertContains($localReceipt, array_column($state['plan']['receipts'], 'id'));
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
}
