<?php

namespace Tests\Fixtures;

use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\ReceiptConsumption;
use App\Models\Substrate;
use App\Services\ParticipationService;

trait HorizonCycleFixture
{
    protected function driveHorizonCycle(int $participantCount = 1, bool $realClosure = false, string $originDirection = 'support', ?callable $observe = null, int $additionalEntrants = 0, int $actionBudget = 100, ?string $expressiveDirection = null): Proposition
    {
        $substrate = (new Substrate)->forceFill(['slug' => 'single-bird', 'title' => 'Single Participant', 'visibility' => 'public', 'configuration' => []]);
        $substrate->save();
        $participant = (new Participant)->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'solo', 'max_read_count' => 25]);
        $participant->save();
        $service = app(ParticipationService::class);
        $state = $service->enter($participant, ['statement' => 'Origin', 'attestation' => ['direction' => $originDirection]]);
        $originId = $state['current_proposition_id'];
        // Isolate numeric eligibility with a consistent birth observation, after Origin.
        if (! $realClosure) {
            $this->controlledBirthClosureFixture(1.0, includeCommittal: true);
            $service = app(ParticipationService::class);
        }
        $participants = [$participant];
        for ($i = 1; $i < $participantCount; $i++) {
            $other = (new Participant)->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'collaborator-'.$i, 'max_read_count' => 25]);
            $other->save();
            $service->enter($other);
            $participants[] = $other;
        }
        $trace = [];
        $lastCommitment = null;
        for ($step = 0; $step < $actionBudget * $participantCount; $step++) {
            $participant = $participants[$step % $participantCount];
            $state = $service->stateFor($participant);
            $this->assertSame('ready', $state['status'], json_encode($trace));
            $plan = $state['plan'];
            $lastCommitment = $plan['commitment'] ?? $lastCommitment;
            $observe?->__invoke($participant, $state);
            $actions = $plan['schema']['$defs']['actions'];
            $action = collect(['proposition.commit', 'proposition.reclassify', 'proposition.variant', 'proposition.reconcile',
                'proposition.traverse', 'attestation.create', 'instrument.execute', 'participant.rest'])
                ->first(fn ($candidate) => isset($actions[$candidate]));
            $definition = $actions[$action];
            $input = ['action' => $action];
            foreach (['proposition_id', 'other_proposition_id', 'receipt_id'] as $key) {
                if (isset($definition['properties'][$key]['enum'])) {
                    $input[$key] = $definition['properties'][$key]['enum'][0];
                }
            }
            if ($action === 'proposition.variant') {
                $input['statement'] = 'Opportunity '.$step;
            }
            if ($action === 'instrument.execute') {
                $input['instrument'] = ['kind' => 'hyperlink', 'reference' => 'https://example.test/evidence/'.$step];
            }
            if ($action === 'attestation.create') {
                $choice = collect($definition['anyOf'])->first(fn ($choice) => $choice['properties']['direction']['enum'][0] === ($expressiveDirection ?? $originDirection))
                    ?? $definition['anyOf'][0];
                $input['proposition_id'] = $choice['properties']['proposition_id']['enum'][0];
                $input['direction'] = $choice['properties']['direction']['enum'][0];
            }
            $trace[] = [$state['cycle']['sequence'], $plan['phase']['number'], $action, $input['direction'] ?? null, $plan['rest_reason'] ?? null];
            try {
                $service->contribute($participant, $plan['phase']['id'], $input);
            } catch (\Throwable $error) {
                $this->fail($error->getMessage().' '.json_encode($trace));
            }
            if ($action === 'proposition.commit') {
                $commit = $participant->fresh()->position;
                $this->assertSame('committal', $commit->tier);
                $this->assertNotSame($originId, $commit->id);
                $this->assertSame($participantCount + $additionalEntrants, Participant::count());
                $this->assertSame([3, 6], Phase::whereIn('id', ReceiptConsumption::pluck('phase_id'))->orderBy('kind')->pluck('kind')->map(fn ($kind) => $kind->value)->unique()->values()->all());
                $this->assertGreaterThan(1, $participant->cycles()->count());
                $next = $service->stateFor($participant);
                $this->assertSame($commit->id, $next['plan']['gate_id']);
                $this->assertSame(1, $next['plan']['phase']['number']);

                return $commit;
            }
        }
        $this->fail('The single-participant motor stalled. Last Commit eligibility: '
            .json_encode($lastCommitment).'; trace: '.json_encode($trace));
    }
}
