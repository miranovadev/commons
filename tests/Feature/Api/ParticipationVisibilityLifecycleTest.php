<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('constitutional')]
class ParticipationVisibilityLifecycleTest extends ScriptedParticipationTestCase
{
    public function test_http_context_exposes_local_evidence_and_edges_without_leaking_foreign_or_distant_structure(): void
    {
        $this->replayParticipationScript(__DIR__.'/../../Fixtures/participation/visibility.json', 0);
        $localSlug = $this->substrateSlug;
        $this->substrateSlug = 'foreign-evidence';
        $this->postJson('/api/substrates', ['slug' => $this->substrateSlug, 'title' => 'Foreign', 'visibility' => 'private'])->assertCreated();
        $journey = $this->continueParticipationScript(2);
        $foreignOrigin = $journey['responses'][0]['current_proposition_id'];
        $foreignChild = $journey['responses'][1]['current_proposition_id'];
        $foreignReceipts = DB::table('receipts')->pluck('id')->all();
        $foreignAttestations = DB::table('attestations')->pluck('id')->all();
        $foreignEdges = DB::table('supersessions')->pluck('id')->all();
        $this->substrateSlug = $localSlug;
        $journey = $this->continueParticipationScript(4);
        $this->assertParticipationScriptComplete();
        $observer = $journey['participants']['local-observer'];
        $origin = $journey['responses'][2]['current_proposition_id'];
        $signal = $journey['responses'][4]['current_proposition_id'];
        $distant = $journey['responses'][5]['current_proposition_id'];
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
