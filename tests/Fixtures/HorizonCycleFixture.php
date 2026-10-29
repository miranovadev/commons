<?php

namespace Tests\Fixtures;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Models\EvidenceExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\ReceiptConsumption;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Services\ChiralityGateService;
use App\Services\Evidence\ArtifactStore;
use App\Services\Evidence\HyperlinkIdentity;
use App\Services\Evidence\HyperlinkMetadataExtractor;
use App\Services\Evidence\HyperlinkVerifier;
use App\Services\Evidence\PublicResourceRetriever;
use App\Services\HorizonReceiptService;
use App\Services\ParticipationService;
use App\Services\PreparationService;
use Carbon\CarbonImmutable;

trait HorizonCycleFixture
{
    protected function driveHorizonCycle(int $participantCount = 1, bool $realClosure = false, string $originDirection = 'support', ?callable $observe = null, int $additionalEntrants = 0, int $actionBudget = 100, ?string $expressiveDirection = null, ?callable $recordAction = null, ?string $mediateDirection = null): Proposition
    {
        // Preserve real immutable material for the activated reading workflow.
        $this->app->bind(HyperlinkVerifier::class, function ($app) {
            return new class($app->make(HyperlinkIdentity::class), $app->make(PublicResourceRetriever::class), $app->make(ArtifactStore::class), $app->make(HyperlinkMetadataExtractor::class)) extends HyperlinkVerifier
            {
                public function verify(string $reference): VerifiedHyperlink
                {
                    $resource = app(HyperlinkIdentity::class)->resource($reference);
                    $body = 'Deterministic preserved observation for '.$resource;
                    $artifact = app(ArtifactStore::class)->preserve($body, 'text/plain');

                    return new VerifiedHyperlink($resource, hash('sha256', $body), CarbonImmutable::now(), artifacts: [$artifact]);
                }
            };
        });
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
            // Origin already resolved the request-scoped Preparation service.
            // Rebuild its dependencies after installing this fixture's numeric boundary.
            $this->app->forgetInstance(PreparationService::class);
            $service = app(ParticipationService::class);
        }
        $participants = [$participant];
        for ($i = 1; $i < $participantCount; $i++) {
            $other = (new Participant)->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'collaborator-'.$i, 'max_read_count' => 25]);
            $other->save();
            $service->enter($other);
            $participants[] = $other;
        }

        return $this->driveAuthorisedCycle($participants, $originId, $originDirection,
            $observe, $additionalEntrants, $actionBudget, $expressiveDirection, $recordAction, $mediateDirection);
    }

    /** Continue actual active participation; never seed or request a Domain/depth. */
    protected function driveAuthorisedCycle(array $participants, string $entryId, string $originDirection = 'support', ?callable $observe = null, int $additionalEntrants = 0, int $actionBudget = 100, ?string $expressiveDirection = null, ?callable $recordAction = null, ?string $mediateDirection = null): Proposition
    {
        $service = app(ParticipationService::class);
        $participantCount = count($participants);
        $trace = [];
        $lastCommitment = null;
        $lastBias = null;
        for ($step = 0; $step < $actionBudget * $participantCount; $step++) {
            $participant = $participants[$step % $participantCount];
            $state = $service->stateFor($participant);
            $this->assertSame('ready', $state['status'], json_encode($trace));
            $plan = $state['plan'];
            $lastCommitment = $plan['commitment'] ?? $lastCommitment;
            $observe?->__invoke($participant, $state);
            $actions = $plan['schema']['$defs']['actions'];
            if ($plan['phase']['number'] === 6 && ! isset($actions['proposition.reclassify'])) {
                $lastBias = app(ChiralityGateService::class)->evaluate(Phase::findOrFail($plan['phase']['id'])->gate);
            }
            $needsEvidence = app(HorizonReceiptService::class)
                ->ordinaryAvailable(Phase::findOrFail($plan['phase']['id']))->isEmpty();
            $judgment = $plan['phase']['number'] === 5 && $mediateDirection !== null
                ? $mediateDirection : ($expressiveDirection ?? $originDirection);
            $choices = collect($actions['attestation.create']['anyOf'] ?? []);
            $classifiedChoice = null;
            $classifiedTarget = null;
            if ($plan['phase']['number'] === 5 && $mediateDirection !== null) {
                $targets = $choices->flatMap(fn ($choice) => $choice['properties']['proposition_id']['enum']);
                $classified = Supersession::where('type', 'reclassify')->whereIn('source_proposition_id', $targets)
                    ->pluck('source_proposition_id');
                $classifiedChoice = $choices->first(fn ($choice) => $classified->intersect($choice['properties']['proposition_id']['enum'])->isNotEmpty());
                if ($classifiedChoice !== null) {
                    $classifiedTarget = collect($classifiedChoice['properties']['proposition_id']['enum'])->intersect($classified)->first();
                }
            }
            $preferredJudgment = $classifiedChoice !== null || $choices
                ->contains(fn ($choice) => $choice['properties']['direction']['enum'][0] === $judgment);
            // This authored policy can Support a Mediate synthesis while retaining
            // skeptical development elsewhere. Continuous Cadence no longer makes
            // every new synthesis immature. Revisit an offered classification route
            // before judging another Mediate branch; eligibility is unchanged.
            // The original aligned driver retains its entry-only reconsideration.
            $action = collect(['preparation.start', 'instrument.execute', 'proposition.commit', 'proposition.reclassify', 'attestation.create',
                'proposition.variant', 'proposition.reconcile', 'proposition.traverse', 'participant.rest'])
                ->first(fn ($candidate) => isset($actions[$candidate])
                    && ($candidate !== 'instrument.execute' || ($plan['acquisition']['status'] ?? null) === 'required' || $needsEvidence || count($actions) === 1)
                    && ($candidate !== 'attestation.create' || $preferredJudgment
                        || ($plan['phase']['number'] === 1 && ! isset($actions['proposition.variant']))))
                ?? 'attestation.create';
            $definition = $actions[$action];
            $input = ['action' => $action];
            foreach (['proposition_id', 'other_proposition_id', 'receipt_id'] as $key) {
                if (isset($definition['properties'][$key]['enum'])) {
                    $input[$key] = $definition['properties'][$key]['enum'][0];
                }
            }
            if ($action === 'preparation.start') {
                $selection = $plan['preparation_options'][0];
                $input += ['proposition_id' => $plan['gate_id'], 'purpose' => 'Examine the evidence before developing this continuation.',
                    'artifacts' => [['receipt_id' => $selection['receipt_id'], 'artifact_id' => $selection['artifact_id'],
                        'read_type' => 'text', 'parameters' => ['max_chars' => 4000]]]];
            }
            if ($action === 'proposition.variant') {
                $input['statement'] = 'Opportunity '.$step;
            }
            if ($action === 'instrument.execute') {
                $input['instrument'] = ['kind' => 'hyperlink', 'reference' => 'https://example.test/evidence/'.$step];
            }
            if ($action === 'attestation.create') {
                $choice = $classifiedChoice ?? collect($definition['anyOf'])->first(fn ($choice) => $choice['properties']['direction']['enum'][0] === $judgment)
                    ?? $definition['anyOf'][0];
                $input['proposition_id'] = $classifiedTarget ?? $choice['properties']['proposition_id']['enum'][0];
                $input['direction'] = $choice['properties']['direction']['enum'][0];
            }
            $trace[] = [$state['cycle']['sequence'], $plan['phase']['number'], $action, $input['direction'] ?? null, $plan['rest_reason'] ?? null];
            try {
                $result = $service->contribute($participant, $plan['phase']['id'], $input);
                $recordAction?->__invoke($participant, $state, $input, $result);
                if ($action === 'preparation.start') {
                    $execution = EvidenceExecution::findOrFail($result['plan']['preparation']['execution_id']);
                    $preparation = app(PreparationService::class);
                    $delivery = $preparation->delivery($participant, $execution, 'inline-text');
                    $preparation->acknowledge($participant, $execution, $delivery['presentation_token']);
                    $prepared = $service->stateFor($participant);
                    $resolution = isset($prepared['plan']['schema']['$defs']['actions']['proposition.variant'])
                        ? ['action' => 'proposition.variant', 'statement' => 'Prepared opportunity '.$step]
                        : ['action' => 'preparation.defer', 'reason' => 'No structurally lawful fresh Variant is available.'];
                    $resolution['execution_id'] = $execution->id;
                    $trace[] = [$prepared['cycle']['sequence'], $prepared['plan']['phase']['number'], $resolution['action'], null, null];
                    $resolved = $service->contribute($participant, $prepared['plan']['phase']['id'], $resolution);
                    $recordAction?->__invoke($participant, $prepared, $resolution, $resolved);
                }
            } catch (\Throwable $error) {
                $this->fail($error->getMessage().' '.json_encode($trace));
            }
            if ($action === 'proposition.commit') {
                $commit = $participant->fresh()->position;
                $this->assertSame('committal', $commit->tier);
                $this->assertNotSame($entryId, $commit->id);
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
            .json_encode($lastCommitment).'; last Bias eligibility: '.json_encode($lastBias).'; trace: '.json_encode($trace));
    }
}
