<?php

namespace Tests\Feature;

use App\Domain\Observables\ClosureValues;
use App\Models\Participant;
use App\Models\ParticipantReceiptContext;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\ClosureGateService;
use App\Services\ParticipationService;
use App\Services\PropositionStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullEvidenceFlowTest extends TestCase
{
    use RefreshDatabase;

    private ParticipationService $service;

    public function test_three_participants_ground_classify_mediate_bias_rest_commit_and_continue_without_teleporting(): void
    {
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'full-evidence-flow', 'title' => 'Full evidence flow', 'visibility' => 'public', 'configuration' => []])->save();
        [$a, $b, $c] = array_map(function ($callsign) use ($substrate) {
            $p = new Participant;
            $p->forceFill(['substrate_id' => $substrate->id, 'callsign' => $callsign, 'max_read_count' => 25])->save();

            return $p;
        }, ['a', 'b', 'c']);
        // Isolate the contribution/navigation contract. The real numeric tests
        // independently cover Depth/Cadence; here only the final candidate closes.
        $this->mock(ClosureGateService::class, function ($mock): void {
            $mock->shouldReceive('preview', 'forCommitment')->andReturnUsing(fn ($node) => [
                ...(new ClosureValues($node->statement === 'Origin' ? -1 : 1, $node->statement === 'Right signal' ? 1 : -1))->resolve(),
                'history_proposition_id' => null, 'history_source' => 'negative-default',
            ]);
        });
        $service = $this->service = app(ParticipationService::class);
        $originState = $service->enter($a, ['statement' => 'Origin', 'attestation' => ['direction' => 'support'], 'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/origin']]);
        $origin = Proposition::findOrFail($originState['plan']['gate_id']);
        $signal = $this->move($a, 'proposition.variant', ['statement' => 'Original signal']);
        $this->assertArrayNotHasKey('proposition.reclassify', $this->state($a)['plan']['schema']['$defs']['actions']);
        $service->enter($b);
        $this->instrument($b, $signal, $origin);
        $this->assertArrayNotHasKey('proposition.reclassify', $this->state($a)['plan']['schema']['$defs']['actions']);
        $service->enter($c);
        $this->attest($c, $signal);
        $stance = $this->move($c, 'proposition.reclassify');
        $this->assertSame($signal->statement, $stance->statement);
        $this->assertSame(0, $stance->attestations()->count());
        $this->assertArrayNotHasKey('proposition.reclassify', $this->state($c)['plan']['schema']['$defs']['actions']);
        $this->attest($a, $stance);
        $concord = $this->move($a, 'proposition.reclassify');
        $left = $this->move($a, 'proposition.variant', ['statement' => 'Left signal']);
        $this->attest($b, $stance);
        $this->attest($b, $concord);
        $right = $this->move($b, 'proposition.variant', ['statement' => 'Right signal']);
        $mediated = $this->move($b, 'proposition.reconcile', ['other_proposition_id' => $left->id]);
        $this->assertSame($right->statement, $mediated->statement);
        $this->assertCount(0, app(PropositionStateService::class)->receipts($mediated));
        $this->assertArrayNotHasKey('proposition.bias', $this->state($b)['plan']['schema']['$defs']['actions']);
        $this->attest($c, $concord);
        $this->instrument($c, $left, $origin); // Disperse
        $this->instrument($c, $mediated, $origin); // Mediate can ground its fresh Write
        $this->assertArrayNotHasKey('proposition.bias', $this->state($b)['plan']['schema']['$defs']['actions']);
        $this->attest($a, $mediated);
        $final = $this->move($a, 'proposition.bias', ['proposition_id' => $mediated->id]);
        $this->assertCount(1, app(PropositionStateService::class)->receipts($final));
        $this->assertSame(0, $final->attestations()->count());
        $this->assertSame(['participant.rest'], array_keys($this->state($a)['plan']['schema']['$defs']['actions']));
        $this->move($a, 'participant.rest');
        $this->assertSame('distinguish', $this->state($a)['plan']['phase']['kind']);
        $this->attest($b, $final);
        $this->attest($c, $final, 'refute'); // Either direction permits engagement.
        $this->assertSame(0, ParticipantReceiptContext::active()->count());
        $order = $service->workspaceFor($b)['plan'];
        $this->assertSame([$final->id], array_column($order['gate_options'], 'id'), json_encode(['statement' => $final->statement, 'commitment' => $order['commitment'], 'phase' => $order['phase'], 'register' => $final->register]));
        $this->assertSame([], $order['write_options']);
        $this->assertSame([$mediated->id], array_column($order['read_options'], 'id'));
        $commit = $this->move($b, 'proposition.commit', ['proposition_id' => $final->id]);
        $this->assertSame('committal', $commit->tier);
        $waiting = $service->workspaceFor($c)['plan'];
        $this->assertSame([], $waiting['gate_options']);
        $this->assertSame([$commit->id], array_column($waiting['write_options'], 'id'));
        $this->assertSame(['proposition.traverse'], array_keys($waiting['schema']['$defs']['actions']));
        $this->move($c, 'proposition.traverse', ['proposition_id' => $commit->id]);
        foreach ([$b, $c] as $participant) {
            $completed = $this->state($participant);
            $this->assertSame('ready', $completed['status']);
            $this->assertSame($commit->id, $completed['plan']['gate_id']);
            $next = $completed;
            $this->assertSame('distinguish', $next['plan']['phase']['kind']);
            $this->assertSame($commit->id, $next['plan']['gate_id']);
            $this->assertSame($commit->id, $next['current_proposition_id']);
        }
        $returned = $this->state($a);
        $this->assertSame('distinguish', $returned['plan']['phase']['kind']);
        $this->assertSame('committal', Proposition::findOrFail($returned['plan']['gate_id'])->tier);
    }

    private function state(Participant $participant): array
    {
        return $this->service->stateFor($participant);
    }

    private function move(Participant $participant, string $action, array $input = []): Proposition
    {
        $state = $this->state($participant);
        $this->service->contribute($participant, $state['plan']['phase']['id'], ['action' => $action, ...$input]);

        return $participant->fresh()->position;
    }

    private function attest(Participant $participant, Proposition $node, string $direction = 'support'): void
    {
        $this->move($participant, 'attestation.create', ['proposition_id' => $node->id, 'direction' => $direction]);
    }

    private function instrument(Participant $participant, Proposition $node, Proposition $reference): void
    {
        $this->move($participant, 'instrument.execute', ['proposition_id' => $node->id, 'instrument' => ['kind' => 'citation', 'reference' => $reference->id]]);
    }
}
