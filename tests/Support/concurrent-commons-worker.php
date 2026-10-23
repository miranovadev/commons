<?php

// A separate PHP process gives the regression tests a real, independent backend.
use App\Domain\Evidence\DownloadObservation;
use App\Domain\Evidence\InstrumentObservation;
use App\Models\CommitmentSnapshot;
use App\Models\EvidenceExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\ContextHorizonProjector;
use App\Services\ContributionDispatcher;
use App\Services\EvidenceExecutionService;
use App\Services\InputPlanService;
use App\Services\ParticipationService;
use App\Services\PotentialProjectionService;
use App\Services\PreparationService;
use App\Services\PropositionMeasurementService;
use App\Services\ReceiptContextService;
use App\Services\TragedyService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
config()->set('database.default', 'concurrency_worker');
config()->set('database.connections.concurrency_worker', $input['database']);
config()->set('broadcasting.default', 'null');
if (isset($input['artifact_root'])) {
    config()->set('filesystems.disks.artifacts', ['driver' => 'local', 'root' => $input['artifact_root'], 'visibility' => 'private', 'serve' => false]);
}

DB::statement("SET lock_timeout = '8s'");
DB::statement("SET statement_timeout = '12s'");
echo DB::selectOne('select pg_backend_pid() as pid')->pid."\n";
flush();

try {
    match ($input['operation']) {
        'terminal-encounter' => DB::transaction(function () use ($input): void {
            Substrate::query()->lockForUpdate()->findOrFail($input['substrate_id']);
            $target = Proposition::findOrFail($input['proposition_id']);
            $phase = Phase::findOrFail($input['phase_id']);
            // Post-authorisation observer concurrency component; no fabricated birth.
            app(TragedyService::class)->record($target, $phase, 'instrument.execute');
        }),
        'closure-contribute-conflict' => (function () use ($input): void {
            $participant = Participant::findOrFail($input['participant_id']);
            $plan = app(InputPlanService::class)->forParticipant($participant);
            try {
                app(ParticipationService::class)->contribute($participant, $input['phase_id'],
                    $input['contribution'], $plan['schema']);
            } catch (ConflictHttpException $exception) {
                if (str_contains($exception->getMessage(), 'field changed') || str_contains($exception->getMessage(), 'Closure changed')) {
                    return;
                }
                throw $exception;
            }
            throw new RuntimeException('A concurrent completion did not reject the stale Commit.');
        })(),
        'receipt-desired' => DB::transaction(fn () => app(ReceiptContextService::class)->desired(
            Participant::findOrFail($input['participant_id']), Phase::findOrFail($input['phase_id']), $input['receipt_options'],
        )),
        'preparation-start-conflict', 'preparation-defer-conflict' => (function () use ($input): void {
            try {
                $service = app(PreparationService::class);
                $participant = Participant::findOrFail($input['participant_id']);
                if ($input['operation'] === 'preparation-start-conflict') {
                    $service->start($participant, Phase::findOrFail($input['phase_id']), $input['preparation']);
                } else {
                    $service->defer($participant, EvidenceExecution::findOrFail($input['execution_id']), 'Concurrent deferment loses.');
                }
            } catch (ConflictHttpException $exception) {
                if (str_contains($exception->getMessage(), 'already reserved') || str_contains($exception->getMessage(), 'active Disperse')) {
                    return;
                }
                throw $exception;
            }
            throw new RuntimeException('Concurrent Preparation was accepted twice.');
        })(),
        'instrument-conflict' => (function () use ($input): void {
            try {
                app(ContributionDispatcher::class)->dispatch(
                    Participant::findOrFail($input['participant_id']),
                    Phase::findOrFail($input['phase_id']), $input['contribution'],
                    observed: isset($input['download_observation']) ? new InstrumentObservation(
                        $input['contribution']['instrument'], download: new DownloadObservation(
                            $input['contribution']['instrument']['reference'], $input['download_observation'],
                        ),
                    ) : null,
                );
            } catch (ConflictHttpException $exception) {
                if (str_contains($exception->getMessage(), 'unused Cycle allowance')) {
                    return;
                }
                throw $exception;
            }
            throw new RuntimeException('Concurrent Instrument allowance was spent twice.');
        })(),
        'dispatch-rejected' => (function () use ($input): void {
            try {
                app(ContributionDispatcher::class)->dispatch(
                    Participant::findOrFail($input['participant_id']),
                    Phase::findOrFail($input['phase_id']),
                    $input['contribution'],
                );
            } catch (DomainException $exception) {
                if (str_contains($exception->getMessage(), $input['expected_message'])) {
                    return;
                }
                throw $exception;
            }
            throw new RuntimeException('Concurrent duplicate generation was accepted.');
        })(),
        'generation-conflict', 'receipt-generation-conflict' => (function () use ($input): void {
            $participant = Participant::findOrFail($input['participant_id']);
            $plan = app(InputPlanService::class)->forParticipant($participant);
            try {
                if ($input['operation'] === 'receipt-generation-conflict') {
                    app(ContributionDispatcher::class)->dispatch($participant, Phase::findOrFail($input['phase_id']), $input['contribution']);
                } else {
                    app(ParticipationService::class)->contribute($participant, $input['phase_id'], $input['contribution'], $plan['schema']);
                }
            } catch (ConflictHttpException $exception) {
                if (str_contains($exception->getMessage(), 'field changed') || str_contains($exception->getMessage(), 'options changed')
                    || str_contains($exception->getMessage(), 'Shared Receipt availability changed')) {
                    return;
                }
                throw $exception;
            }
            throw new RuntimeException('A waiting contribution ignored shared Receipt consumption.');
        })(),
        'read' => (function () use ($app, $input): void {
            $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $request = Request::create($input['url'], 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
            $response = $kernel->handle($request);
            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException($response->getContent());
            }
            echo $response->getContent()."\n";
            $kernel->terminate($request, $response);
        })(),
        'state' => app(ParticipationService::class)->stateFor(Participant::findOrFail($input['participant_id'])),
        'workspace' => app(ParticipationService::class)->workspaceFor(Participant::findOrFail($input['participant_id'])),
        'prepare' => app(ParticipationService::class)->prepare(Participant::findOrFail($input['participant_id'])),
        'horizon' => app(ContextHorizonProjector::class)->apply(CommitmentSnapshot::findOrFail($input['proposition_id'])),
        'plan' => app(InputPlanService::class)->forParticipant(Participant::findOrFail($input['participant_id'])),
        'rest' => contributeWithRetry(
            Participant::findOrFail($input['participant_id']), $input['phase_id'], ['action' => 'participant.rest'],
        ),
        'contribute' => contributeWithRetry(
            Participant::findOrFail($input['participant_id']),
            $input['phase_id'],
            [], // Select from fresh shared structure after any optimistic retry.
        ),
        'complete' => app(EvidenceExecutionService::class)->complete(
            EvidenceExecution::findOrFail($input['execution_id']),
            Proposition::findOrFail($input['proposition_id']),
        ),
        'project' => app(PropositionMeasurementService::class)->project(
            Proposition::findOrFail($input['proposition_id']), 0.0, 0.0, 0.0,
        ),
    };
    echo "completed\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
    exit(1);
}

/** A separate wake retries a version conflict against newly published terrain. */
function contributeWithRetry(Participant $participant, string $phaseId, array $input): array
{
    for ($attempt = 0; ; $attempt++) {
        try {
            $contribution = $input;
            if ($contribution === []) {
                $phase = Phase::findOrFail($phaseId);
                $actions = app(InputPlanService::class)->forPhase($participant->fresh(), $phase)['schema']['$defs']['actions'];
                if (isset($actions['proposition.variant'])) {
                    $contribution = ['action' => 'proposition.variant', 'statement' => 'Concurrent distinction'];
                } elseif (isset($actions['attestation.create'])) {
                    $choice = $actions['attestation.create']['anyOf'][0]['properties'];
                    $contribution = ['action' => 'attestation.create', 'proposition_id' => $choice['proposition_id']['enum'][0],
                        'direction' => $choice['direction']['enum'][0]];
                } elseif (isset($actions['proposition.traverse'])) {
                    $contribution = ['action' => 'proposition.traverse',
                        'proposition_id' => $actions['proposition.traverse']['properties']['proposition_id']['enum'][0]];
                } elseif (isset($actions['instrument.execute'])) {
                    $contribution = ['action' => 'instrument.execute', 'proposition_id' => $actions['instrument.execute']['properties']['proposition_id']['enum'][0],
                        'instrument' => ['kind' => 'citation', 'reference' => $phase->cycle->entry_committal_id]];
                } else {
                    throw new RuntimeException('No forward contribution is available to this concurrency worker.');
                }
            }

            return app(ParticipationService::class)->contribute($participant->fresh(), $phaseId, $contribution);
        } catch (ConflictHttpException $exception) {
            if ($attempt >= 2 || Phase::findOrFail($phaseId)->completed_at !== null) {
                throw $exception;
            }
            app(PotentialProjectionService::class)->refresh($participant->substrate_id);
        }
    }
}
