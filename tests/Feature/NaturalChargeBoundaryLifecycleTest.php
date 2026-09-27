<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Tragedy;
use App\Services\ExhaustionAuditService;
use App\Services\HorizonReceiptService;
use App\Services\ParticipationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\ExhaustionFixture;
use Tests\Fixtures\NaturalBoundaryHistory;
use Tests\TestCase;

#[Group('integration')]
class NaturalChargeBoundaryLifecycleTest extends TestCase
{
    use ExhaustionFixture, NaturalBoundaryHistory, RefreshDatabase;

    public function test_two_participants_reproduce_catastrophe_then_recover_to_the_open_ancestor(): void
    {
        [$this->substrate, $this->origin, $ancestor, $seed] = $this->naturalBoundaryHistory();
        // Seed topology only. Every semantic event below is an ordinary contribution,
        // with real observables, evidence, phase progression and recovery. No field mock.
        $a = $this->phase($seed, PhaseKind::Distinguish);
        $b = $this->phase($seed, PhaseKind::Distinguish);
        $service = app(ParticipationService::class);
        $first = $service->contribute($a->cycle->participant, $a->id,
            ['action' => 'proposition.variant', 'statement' => 'First untested trajectory']);
        $tragedy = Tragedy::sole();
        $firstHistory = $tragedy->getAttributes();
        $warrantHistory = DB::table('receipts')->where('id', $tragedy->receipt_id)->sole();
        $this->assertEquals(0, json_decode($warrantHistory->metadata, true)['field']['charge']);
        $this->assertSame($first['current_proposition_id'], $tragedy->terminal_proposition_id);
        $this->assertSame($seed->id, $tragedy->governing_committal_id);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $this->assertNotNull(app(HorizonReceiptService::class)->tragedyWarrant($b));

        // B actually progresses through the first Write by observing it, then
        // creates its own Persist result from that legitimate current Gate.
        $observed = $service->contribute($b->cycle->participant, $b->id,
            ['action' => 'instrument.execute', 'proposition_id' => $first['current_proposition_id'],
                'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://example.test/boundary-transcript'],
                'receipt_options' => ['load' => [$tragedy->receipt_id]]]);
        $this->assertSame('persist', $observed['plan']['phase']['kind']);
        $memory = collect($observed['plan']['receipts'])->firstWhere('id', $tragedy->receipt_id);
        $this->assertTrue($memory['memory_active']);
        $this->assertFalse($memory['active']);
        $this->assertDatabaseHas('participant_receipt_contexts', ['participant_id' => $b->cycle->participant_id,
            'receipt_id' => $tragedy->receipt_id, 'evidence_signature' => null]);
        $this->assertDatabaseCount('commit_region_exhaustions', 0);
        $second = $service->contribute($b->cycle->participant, $observed['plan']['phase']['id'],
            ['action' => 'proposition.variant', 'statement' => 'A distinct trajectory reproduces the boundary']);
        $proof = DB::table('commit_region_exhaustions')->sole();
        $this->assertSame($second['current_proposition_id'], $proof->terminal_proposition_id);
        $this->assertNotSame($tragedy->terminal_proposition_id, $proof->terminal_proposition_id);
        $this->assertNotSame($tragedy->participant_id, $b->cycle->participant_id);
        $this->assertSame(0, json_decode($proof->boundary_field, true)['charge']);
        $this->assertSame(['participant.rest'], array_keys($second['plan']['schema']['$defs']['actions']));
        $this->assertNull(app(HorizonReceiptService::class)->tragedyWarrant(Phase::findOrFail($second['plan']['phase']['id'])));
        $this->assertDatabaseMissing('receipt_consumptions', ['receipt_id' => $tragedy->receipt_id]);
        $this->assertDatabaseCount('tragedies', 1);

        $recovered = $service->contribute($b->cycle->participant, $second['plan']['phase']['id'], ['action' => 'participant.rest']);
        $this->assertSame($ancestor->id, $recovered['current_proposition_id']);
        $this->assertSame('distinguish', $recovered['plan']['phase']['kind']);
        $remembered = collect($recovered['plan']['receipts'])->firstWhere('id', $tragedy->receipt_id);
        $this->assertTrue($remembered['memory_active']);
        $this->assertSame([$ancestor->id, $seed->id], array_column($remembered['tragedy_memory']['committed_chain'], 'proposition_id'));
        $this->assertSame($first['current_proposition_id'], $remembered['tragedy_memory']['catastrophe']['proposition_id']);
        $this->assertNull(app(HorizonReceiptService::class)->tragedyWarrant(Phase::findOrFail($recovered['plan']['phase']['id'])));
        $this->assertDatabaseHas('propositions', ['id' => $proof->terminal_proposition_id]);
        // Rest/recovery only routes the participant: both catastrophe events and
        // their dedicated warrant remain the history recorded before that Rest.
        $this->assertSame($firstHistory, $tragedy->fresh()->getAttributes());
        $this->assertEquals($proof, DB::table('commit_region_exhaustions')->sole());
        $this->assertEquals($warrantHistory, DB::table('receipts')->where('id', $tragedy->receipt_id)->sole());
        $this->assertDatabaseMissing('receipt_consumptions', ['receipt_id' => $tragedy->receipt_id]);
        $this->assertSame($first['current_proposition_id'], $a->cycle->participant->fresh()->current_proposition_id);
        $this->assertSame(1, Proposition::where('id', $proof->terminal_proposition_id)->count());
        $this->assertTrue(app(ExhaustionAuditService::class)->inspect($this->substrate->id)['matches']);
    }
}
