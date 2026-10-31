<?php

namespace Tests\Feature\Api;

use App\Contracts\WebSearchProvider;
use App\Models\EvidenceExecution;
use App\Models\Phase;
use App\Models\Receipt;
use App\Services\AcquisitionAttemptService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantExplorationService;
use App\Services\Participation\ParticipationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\EvidenceLifecycleAudit;
use Tests\Fixtures\PersistCommitHttpFixture;
use Tests\Fixtures\PersistExplorationHttpFixture;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('integration')]
#[Group('http-participation')]
class EvidenceExplorationLifecycleTest extends ScriptedParticipationTestCase
{
    use EvidenceLifecycleAudit, PersistCommitHttpFixture, PersistExplorationHttpFixture;

    public function test_late_operand_reader_must_explore_before_new_acquisition_and_sees_causal_history(): void
    {
        [$author] = $this->beginPersistJourney('reconciliation-author');
        // The exact existing transcript reaches its first lawful Reconciliation.
        foreach (array_keys($this->persistJourneySteps) as $index) {
            $state = $this->executePersistJourneyStep($author, $index);
            if ($this->persistJourneySteps[$index]['input']['action'] === 'proposition.reconcile') {
                break;
            }
        }
        $head = $state['plan']['gate_id'];
        $edges = DB::table('supersessions')->where('target_proposition_id', $head)->orderBy('id')->get()->toJson();
        $consumptions = DB::table('receipt_consumptions')->orderBy('receipt_id')->get()->toJson();
        $before = $this->auditEvidencePacket($state);
        $late = $this->joinParticipant('late-operand-reader');
        $this->enterParticipant($late);
        $this->judge($late, $this->persistJourneyIds['leftDistinction']);
        $this->judge($late, $this->persistJourneyIds['leftPersist']);
        $parked = $this->judge($late, $this->persistJourneyIds['leftCanon']);
        $this->assertActionUnavailable($parked, 'preparation.start');
        $this->assertActionUnavailable($parked, 'instrument.execute');
        $this->assertActionAvailable($parked, 'attestation.create');
        $executions = EvidenceExecution::count();
        $this->postContribution($late, $parked['plan']['phase']['id'], ['action' => 'instrument.execute',
            'proposition_id' => $parked['plan']['gate_id'], 'instrument' => ['kind' => 'citation',
                'reference' => $this->persistJourneyIds['leftDistinction']]])->assertUnprocessable();
        $this->assertSame($executions, EvidenceExecution::count());
        $freshState = $this->readParticipation($author);
        $fresh = $this->auditEvidencePacket($freshState);
        $this->assertSame(array_column($before['receipts'], 'id'), array_column($fresh['receipts'], 'id'));
        $this->assertSame($edges, DB::table('supersessions')->where('target_proposition_id', $head)->orderBy('id')->get()->toJson());
        $this->assertSame($consumptions, DB::table('receipt_consumptions')->orderBy('receipt_id')->get()->toJson());
        $phase = Phase::findOrFail($freshState['plan']['phase']['id']);
        $context = new ParticipationContext($phase);
        $acquisition = app(AcquisitionAttemptService::class);
        $exploration = app(ParticipantExplorationService::class);
        $evaluate = function () use ($acquisition, $exploration, $phase, $context, $head): void {
            $acquisition->state($phase, $context);
            $exploration->exploredIds($phase, collect([$head]), $context);
        };
        DB::flushQueryLog();
        DB::enableQueryLog();
        $evaluate();
        $cold = count(DB::getQueryLog());
        DB::flushQueryLog();
        $evaluate();
        $warm = count(DB::getQueryLog());
        DB::flushQueryLog();
        app(ParticipantContextSerializer::class)->inference($freshState);
        $projection = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(0, $warm);
        fwrite(STDERR, sprintf("\nNATURAL_RECONCILIATION acquisition+exploration cold=%d warm=%d; cognition=%d queries; packet=%d bytes; catalogue=%d bytes/%d receipts\n",
            $cold, $warm, $projection, strlen(json_encode($fresh)), strlen(json_encode($fresh['receipts'])), count($fresh['receipts'])));
    }

    public function test_three_personal_origin_observations_include_a_resolved_failure_and_survive_return(): void
    {
        $this->startSubstrate();
        $actors = [];
        $receipts = [];
        foreach (['first', 'second', 'third'] as $index => $name) {
            $actor = $actors[] = $this->joinParticipant($name);
            $state = $this->enterParticipant($actor, $index === 0);
            $gate = $state['plan']['gate_id'];
            $packet = $this->auditEvidencePacket($state);
            foreach ($receipts as $id) {
                $this->assertContains($id, array_column($packet['receipts'], 'id'));
            }
            $this->assertSame('required', $state['plan']['acquisition']['status']);
            $this->assertActionUnavailable($state, 'proposition.variant');
            if ($index === 2) {
                $this->mock(WebSearchProvider::class)->shouldReceive('search')->once()->andThrow(new \RuntimeException('Deterministic provider outage'));
                $after = $this->contribute($actor, ['action' => 'instrument.execute', 'proposition_id' => $gate,
                    'instrument' => ['kind' => 'web_search', 'query' => 'Unavailable observation']], $state);
            } else {
                $after = $this->discoverGate($actor);
            }
            $execution = EvidenceExecution::where('phase_id', $state['plan']['phase']['id'])->sole();
            $this->assertSame($gate, $execution->proposition_id);
            $this->assertSame($actor, $execution->cycle->participant_id);
            $receipts[] = $execution->receipt->id;
            $this->assertSame($state['plan']['phase'], $after['plan']['phase']);
            $this->assertSame('awaiting_resolution', $after['plan']['acquisition']['status']);
            $this->assertActionAvailable($after, 'proposition.variant');
            if ($index === 2) {
                $this->assertFalse($execution->receipt->metadata['content_verified']);
                $this->assertNull($execution->receipt->signature);
            }
        }
        $this->assertCount(3, array_unique($receipts));
        foreach ($actors as $actor) {
            $packet = $this->auditEvidencePacket($this->readParticipation($actor));
            $this->assertEmpty(array_diff($receipts, array_column($packet['receipts'], 'id')));
        }
        $actor = $actors[0];
        $state = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Develop shared observations']);
        $distinction = $state['plan']['gate_id'];
        $this->contribute($actor, ['action' => 'participant.rest']);
        $state = $this->judge($actor, $distinction);
        $this->assertActionUnavailable($state, 'preparation.start');
        $state = $this->contribute($actor, ['action' => 'instrument.execute', 'proposition_id' => $state['plan']['gate_id'],
            'instrument' => ['kind' => 'citation', 'reference' => $gate]]);
        $this->assertActionUnavailable($state, 'preparation.start');
        $persist = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Persistent observation'])['plan']['gate_id'];
        $returned = $this->contribute($actor, ['action' => 'participant.rest']);
        $this->assertSame($gate, $returned['plan']['gate_id']);
        $this->assertSame('not_required', $returned['plan']['acquisition']['status']);
        $this->assertActionUnavailable($returned, 'instrument.execute');
        $this->assertDatabaseCount('receipt_consumptions', 0);
        $this->assertDatabaseCount('exploration_events', 0);
        $this->contribute($actor, ['action' => 'proposition.traverse', 'proposition_id' => $distinction]);
        $state = $this->judge($actor, $persist);
        $receipt = $state['plan']['schema']['$defs']['actions']['proposition.reclassify']['properties']['receipt_id']['enum'][0];
        $state = $this->contribute($actor, ['action' => 'proposition.reclassify', 'proposition_id' => $persist, 'receipt_id' => $receipt]);
        $this->assertSame('disperse', $state['plan']['phase']['kind']);
        $this->assertActionAvailable($state, 'instrument.execute');
        $this->assertActionUnavailable($state, 'preparation.start');
        $after = $this->contribute($actor, ['action' => 'instrument.execute', 'proposition_id' => $state['plan']['gate_id'],
            'instrument' => ['kind' => 'citation', 'reference' => $gate]]);
        $this->assertSame($state['plan']['phase'], $after['plan']['phase']);
        $this->assertActionUnavailable($after, 'preparation.start');
        $this->assertActionAvailable($after, 'proposition.variant');
        $this->auditEvidencePacket($after);
    }

    public function test_real_persist_read_failure_completes_only_after_attempted_delivery(): void
    {
        Storage::fake('artifacts');
        $this->startSubstrate();
        $actor = $this->joinParticipant('failed-reader');
        $state = $this->enterParticipant($actor, true);
        $this->contribute($actor, ['action' => 'instrument.execute', 'proposition_id' => $state['plan']['gate_id'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://evidence.example.test/source']]);
        $state = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Examine preserved source']);
        $distinction = $state['plan']['gate_id'];
        $this->contribute($actor, ['action' => 'participant.rest']);
        $state = $this->judge($actor, $distinction);
        $this->assertActionUnavailable($state, 'instrument.execute');
        $source = $state['plan']['preparation_options'][0];
        $started = $this->contribute($actor, ['action' => 'preparation.start', 'proposition_id' => $state['plan']['gate_id'],
            'purpose' => 'Bounded examination', 'artifacts' => [['receipt_id' => $source['receipt_id'],
                'artifact_id' => $source['artifact_id'], 'read_type' => 'text', 'parameters' => ['offset' => 2, 'max_chars' => 30]]]]);
        $this->assertSame('pending', $started['plan']['acquisition']['status']);
        $this->assertSame($state['plan']['phase'], $started['plan']['phase']);
        $id = $started['plan']['preparation']['execution_id'];
        // The worker receives its resolution schema with the reading. Execution
        // still requires the real presentation acknowledgement.
        $this->postContribution($actor, $started['plan']['phase']['id'], [
            'action' => 'proposition.variant', 'execution_id' => $id,
            'statement' => 'Premature unread contribution',
        ])->assertConflict();
        $this->assertDatabaseMissing('receipts', ['evidence_execution_id' => $id]);
        Storage::disk('artifacts')->delete(Receipt::findOrFail($source['receipt_id'])->metadata['artifacts'][0]['path']);
        $url = '/api/substrates/'.$this->substrateSlug.'/participants/'.$actor.'/preparations/'.$id.'/reading';
        $this->getJson($url)->assertStatus(422);
        $after = $this->readParticipation($actor);
        $this->assertSame('ready', $after['status'], json_encode($after));
        $this->assertSame($state['plan']['phase'], $after['plan']['phase']);
        $this->assertSame('awaiting_resolution', $after['plan']['acquisition']['status']);
        $this->assertActionAvailable($after, 'proposition.variant');
        $receipt = EvidenceExecution::findOrFail($id)->receipt;
        $this->assertFalse($receipt->metadata['content_verified']);
        $this->assertSame('failed', $receipt->metadata['outcome']['type']);
        $this->assertSame([], $receipt->metadata['deliveries']);
        $this->getJson($url)->assertStatus(409);
        $this->assertSame(1, Receipt::where('evidence_execution_id', $id)->count());
        $this->auditEvidencePacket($after);
        $continued = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Record the failed examination']);
        $this->assertSame('constrain', $continued['plan']['phase']['kind']);
        $this->assertDatabaseCount('receipt_consumptions', 0);
    }

    public function test_shared_disperse_choice_and_slow_read_context_preserve_personal_memory(): void
    {
        ['actor' => $actor, 'observer' => $observer, 'witness' => $witness, 'a' => $a,
            'persist' => $persist, 'b' => $head, 'slot' => $slot] = $this->personalExplorationJourney(observerInstrument: true);
        $gate = $slot['current_proposition_id'];
        $executions = EvidenceExecution::where('proposition_id', $gate)->get();
        $this->assertEqualsCanonicalizing(['instrument', 'prepare'], $executions->pluck('type')->all());
        $this->assertCount(2, $executions->pluck('cycle.participant_id')->unique());
        $state = $this->readParticipation($observer);
        $this->assertSame('not_required', $state['plan']['acquisition']['status']);
        $this->assertActionUnavailable($state, 'preparation.start');
        $this->assertActionUnavailable($state, 'instrument.execute');
        $consumed = DB::table('receipt_consumptions')->where('proposition_id', $gate)->sole()->receipt_id;
        $state = $this->judge($observer, $head);
        $packet = $this->auditEvidencePacket($state);
        $entry = collect($packet['receipts'])->firstWhere('id', $consumed);
        $this->assertSame('read_context', $entry['load_mode']);
        $this->assertFalse($entry['loaded']);
        $this->assertNotEmpty($entry['loaded_inline_content']);
        $this->assertContains($consumed, collect($packet['read']['propositions'])->firstWhere('id', $gate)['receipts']);
        $this->assertDatabaseMissing('participant_receipt_contexts', ['participant_id' => $observer, 'receipt_id' => $consumed]);
        foreach ($executions as $execution) {
            $this->assertContains($execution->receipt->id, array_column($packet['receipts'], 'id'));
            if ($execution->cycle->participant_id === $actor) {
                $this->assertArrayNotHasKey('loaded_inline_content', collect($packet['receipts'])->firstWhere('id', $execution->receipt->id),
                    'An ancestral acquisition is not direct Read consumption');
            }
        }
        $prior = DB::table('exploration_events')->get()->toJson();
        $parked = $this->readParticipation($witness);
        $this->assertSame('not_required', $parked['plan']['acquisition']['status']);
        $this->assertActionUnavailable($parked, 'preparation.start');
        $this->assertActionUnavailable($parked, 'instrument.execute');
        $this->assertActionAvailable($parked, 'attestation.create');
        $this->assertSame($prior, DB::table('exploration_events')->get()->toJson());
        $returned = $this->contribute($observer, ['action' => 'participant.rest']);
        $withoutRead = $this->auditEvidencePacket($returned);
        $this->assertArrayNotHasKey('loaded_inline_content', collect($withoutRead['receipts'])->firstWhere('id', $consumed));
        $event = DB::table('exploration_events')->join('phases', 'phases.id', '=', 'exploration_events.phase_id')
            ->join('cycles', 'cycles.id', '=', 'phases.cycle_id')->where('cycles.participant_id', $observer)->sole();
        $proof = json_decode($event->witness, true);
        $this->assertSame($observer, $proof['participant_id']);
        $judgment = DB::table('attestations')->join('phases', 'phases.id', '=', 'attestations.phase_id')
            ->join('cycles', 'cycles.id', '=', 'phases.cycle_id')->where('attestations.id', $proof['attestation_id'])->sole();
        $this->assertSame($observer, $judgment->participant_id);
        $this->assertTrue($this->visibleProposition($returned, $a)['explored']);
        $this->assertActionUnavailable($returned, 'attestation.create');
        $this->assertActionUnavailable($returned, 'proposition.traverse');
        $history = DB::table('exploration_events')->orderBy('phase_id')->get()->toJson();
        foreach (['support', 'refute'] as $direction) {
            $response = $this->postContribution($observer, $returned['plan']['phase']['id'],
                ['action' => 'attestation.create', 'proposition_id' => $a, 'direction' => $direction]);
            $this->assertContains($response->status(), [409, 422]);
        }
        $this->assertSame($history, DB::table('exploration_events')->orderBy('phase_id')->get()->toJson());
    }
}
