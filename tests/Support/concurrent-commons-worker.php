<?php

// A separate PHP process gives the regression tests a real, independent backend.
use App\Models\CommitmentSnapshot;
use App\Models\InstrumentExecution;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\ContextHorizonProjector;
use App\Services\ContributionDispatcher;
use App\Services\InputPlanService;
use App\Services\InstrumentExecutionService;
use App\Services\ParticipationService;
use App\Services\PropositionMeasurementService;
use App\Services\ReceiptContextService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
config()->set('database.default', 'concurrency_worker');
config()->set('database.connections.concurrency_worker', $input['database']);
config()->set('broadcasting.default', 'null');
DB::statement("SET lock_timeout = '8s'");
DB::statement("SET statement_timeout = '12s'");
echo DB::selectOne('select pg_backend_pid() as pid')->pid."\n";
flush();

try {
    match ($input['operation']) {
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
        'receipt-read' => app(ReceiptContextService::class)->read(Participant::findOrFail($input['participant_id']), Phase::findOrFail($input['phase_id']), $input['receipt_id']),
        'horizon' => app(ContextHorizonProjector::class)->apply(CommitmentSnapshot::findOrFail($input['proposition_id'])),
        'plan' => app(InputPlanService::class)->forParticipant(Participant::findOrFail($input['participant_id'])),
        'rest' => contributeWithRetry(
            Participant::findOrFail($input['participant_id']), $input['phase_id'], ['action' => 'participant.rest'],
        ),
        'contribute' => contributeWithRetry(
            Participant::findOrFail($input['participant_id']),
            $input['phase_id'],
            ['action' => 'proposition.variant', 'statement' => 'Concurrent distinction'],
        ),
        'complete' => app(InstrumentExecutionService::class)->complete(
            InstrumentExecution::findOrFail($input['execution_id']),
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
            return app(ParticipationService::class)->contribute($participant->fresh(), $phaseId, $input);
        } catch (Symfony\Component\HttpKernel\Exception\ConflictHttpException $exception) {
            if ($attempt >= 2 || Phase::findOrFail($phaseId)->completed_at !== null) {
                throw $exception;
            }
            app(App\Services\PotentialProjectionService::class)->refresh($participant->substrate_id);
        }
    }
}
