<?php

namespace Tests\Fixtures;

use App\Models\Receipt;

/** Fixed actions and structural expectations; never selects from runtime options. */
trait PersistCommitHttpFixture
{
    private array $persistJourneyIds = [];

    private array $persistJourneySteps = [];

    private function beginPersistJourney(string $name): array
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant($name);
        $initial = $this->enterParticipant($actor, true);
        $this->persistJourneyIds = ['origin' => $initial['current_proposition_id']];
        $this->persistJourneySteps = json_decode(file_get_contents(__DIR__.'/participation/persist-skeptical-commit.json'), true, flags: JSON_THROW_ON_ERROR)['steps'];

        return [$actor, $initial];
    }

    private function persistJourneyInput(int $index): array
    {
        $input = $this->persistJourneySteps[$index]['input'];
        $resolve = function ($value) use (&$resolve) {
            if (! is_array($value)) {
                return $value;
            }
            if (isset($value['ref'])) {
                $this->assertArrayHasKey($value['ref'], $this->persistJourneyIds);

                return $this->persistJourneyIds[$value['ref']];
            }
            if (isset($value['artifact_of'])) {
                return Receipt::findOrFail($this->persistJourneyIds[$value['artifact_of']])->metadata['artifacts'][0]['hash'];
            }

            return array_map($resolve, $value);
        };
        $input = $resolve($input);

        return $input;
    }

    private function persistCommitWithObserver(string $authorName, string $observerName): array
    {
        [$actor, $initial] = $this->beginPersistJourney($authorName);
        $observer = $this->joinParticipant($observerName);
        $this->enterParticipant($observer);
        foreach (array_keys($this->persistJourneySteps) as $index) {
            $committed = $this->executePersistJourneyStep($actor, $index);
        }

        return [$actor, $observer, $initial, $committed];
    }

    private function revisitPersistCommit(string $observer): array
    {
        // The fixed final Cycle supplies the selected path, not all Reconcile parents.
        foreach ([79, 81, 82, 83, 84] as $index) {
            $target = $this->persistJourneyIds[$this->persistJourneySteps[$index]['gate']];
            $state = $this->contribute($observer, ['action' => 'attestation.create',
                'proposition_id' => $target, 'direction' => 'refute']);
        }

        return $state;
    }

    private function executePersistJourneyStep(string $actor, int $index): array
    {
        $step = $this->persistJourneySteps[$index];
        $before = $this->readParticipation($actor);
        $this->assertSame($step['phase'], $before['plan']['phase']['number'], 'Fixed Phase at step '.$index);
        $this->assertSame($step['cycle'], $before['cycle']['sequence'], 'Fixed Cycle at step '.$index);
        $this->assertSame($this->persistJourneyIds[$step['gate']], $before['plan']['gate_id']);
        $after = $this->contribute($actor, $this->persistJourneyInput($index), $before);
        if (! isset($this->persistJourneyIds[$step['result']])) {
            $this->assertNotContains($after['current_proposition_id'], $this->persistJourneyIds);
            $this->persistJourneyIds[$step['result']] = $after['current_proposition_id'];
        }
        $this->assertSame($this->persistJourneyIds[$step['result']], $after['current_proposition_id']);
        if (isset($step['receipt'])) {
            $instrument = $step['input']['action'] === 'instrument.execute';
            $this->assertSame($instrument ? 2 : 4, $step['phase']);
            if ($instrument) {
                $this->assertSame($before['plan']['phase'], $after['plan']['phase']);
                $this->assertSame($before['cycle'], $after['cycle']);
            } else {
                $this->assertNotSame($before['plan']['phase']['id'], $after['plan']['phase']['id']);
            }
            $receipt = Receipt::whereHas('evidenceExecution', fn ($q) => $q->where('phase_id', $before['plan']['phase']['id']))->sole();
            $this->assertSame($before['plan']['gate_id'], $receipt->evidenceExecution->proposition_id);
            $this->assertNotContains($receipt->id, $this->persistJourneyIds);
            $this->persistJourneyIds[$step['receipt']] = $receipt->id;
        } elseif (isset($step['execution'])) {
            $this->assertSame($before['plan']['phase'], $after['plan']['phase']);
            $id = $after['plan']['preparation']['execution_id'];
            $this->persistJourneyIds[$step['execution']] = $id;
            $this->readAndAcknowledgePreparation($actor, $id);
            $after = $this->readParticipation($actor);
        } else {
            $this->assertNotSame($before['plan']['phase']['id'], $after['plan']['phase']['id']);
        }

        return $after;
    }
}
