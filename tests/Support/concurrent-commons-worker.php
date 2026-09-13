<?php

// A separate PHP process gives the regression tests a real, independent backend.
use App\Models\InstrumentExecution;
use App\Models\Participant;
use App\Models\Proposition;
use App\Services\InputPlanService;
use App\Services\InstrumentExecutionService;
use App\Services\ParticipationService;
use App\Services\PropositionMeasurementService;
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
        'plan' => app(InputPlanService::class)->forParticipant(Participant::findOrFail($input['participant_id'])),
        'contribute' => app(ParticipationService::class)->contribute(
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
