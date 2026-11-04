<?php

namespace Tests\Feature\Api;

use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Services\MediatedIncomingBiasService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\PersistCommitHttpFixture;
use Tests\Support\ScriptedParticipationTestCase;

#[Group('http-participation')]
#[Group('integration')]
class IncomingReconciliationCommitTest extends ScriptedParticipationTestCase
{
    use PersistCommitHttpFixture;

    public function test_bound_operand_reader_can_judge_the_existing_local_child_and_commit_its_interpretation(): void
    {
        [$author] = $this->beginPersistJourney('explorer');
        foreach ($this->persistJourneySteps as $index => $step) {
            $this->executePersistJourneyStep($author, $index);
            if ($step['result'] === 'thirdSignal') {
                break;
            }
        }
        $ids = $this->persistJourneyIds;
        $readPath = ['leftDistinction', 'leftPersist', 'leftCanon', 'leftSignal'];
        $reader = $this->joinParticipant('local-reader');
        $this->enterParticipant($reader);
        $witness = $this->joinParticipant('supporting-reader');
        $this->enterParticipant($witness);
        // Their ordinary judgments establish agreement with the incoming source's ancestry.
        foreach ([$witness, $reader] as $participant) {
            foreach ($readPath as $ref) {
                $this->contribute($participant, ['action' => 'attestation.create', 'proposition_id' => $ids[$ref], 'direction' => 'support']);
            }
        }
        $state = $this->readParticipation($reader);
        $this->assertActionUnavailable($state, 'proposition.reconcile');
        $this->postContribution($reader, $state['plan']['phase']['id'], ['action' => 'proposition.reconcile',
            'other_proposition_id' => $ids['thirdSignal'], 'statement' => 'The third reading repeats the onset.'])->assertUnprocessable();
        $source = Proposition::findOrFail($ids['leftSynthesis']);
        $state = $this->contribute($reader, ['action' => 'attestation.create', 'proposition_id' => $source->id,
            'direction' => 'support'], $state);
        $bias = Phase::findOrFail($state['plan']['phase']['id']);
        $this->assertSame($source->id, $bias->gate_proposition_id);
        $this->assertNotContains($source->id, app(MediatedIncomingBiasService::class)->forPhase($bias)->pluck('id'));

        $statement = 'The local evidence narrows the observed relationship to the onset.';
        $state = $this->contribute($reader, ['action' => 'proposition.reclassify', 'proposition_id' => $source->id,
            'receipt_id' => $ids['rightDiscovery'], 'statement' => $statement], $state);
        $child = Proposition::findOrFail($state['current_proposition_id']);
        $this->assertSame('The observations share a common cause.', $source->fresh()->statement);
        $this->assertSame($statement, $child->statement);
        $this->assertSame($source->id, $child->parents()->sole()->id);
        $this->assertSame($child->id, Receipt::findOrFail($ids['rightDiscovery'])->consumption->proposition_id);
        $this->assertSame($bias->id, $child->phase_id);
        $this->assertSame($bias->cycle->context_horizon_id, $child->contextHorizonMembership->context_horizon_id);
        $this->assertSame(0, $child->attestations()->count());

        $judge = $this->joinParticipant('later-reader');
        $this->enterParticipant($judge);
        foreach ([...array_map(fn ($ref) => $ids[$ref], $readPath), $source->id, $child->id] as $id) {
            $order = $this->contribute($judge, ['action' => 'attestation.create', 'proposition_id' => $id, 'direction' => 'refute']);
        }
        $this->assertActionUnavailable($order, 'proposition.commit');
        // A real Cycle entry refreshes prospective Closure after the new judgments.
        $this->enterParticipant($this->joinParticipant('next-reader'));
        $state = $this->contribute($judge, ['action' => 'proposition.commit', 'proposition_id' => $child->id]);
        $commit = Proposition::findOrFail($state['last_commitment']['proposition_id']);
        $this->assertSame($child->id, $commit->commitSources()->sole()->id);
        $this->assertSame($statement, $commit->statement);
        $this->assertSame($bias->cycle->entry_committal_id, $commit->phase->cycle->entry_committal_id);
        $this->assertSame($commit->id, $commit->commitmentSnapshot->proposition_id);
    }
}
