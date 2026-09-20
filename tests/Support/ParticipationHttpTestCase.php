<?php

namespace Tests\Support;

use App\Jobs\WakeParticipant;
use App\Services\Evidence\PublicResourceAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use LogicException;

/** Real participation requests; only external DNS and HTTP evidence are deterministic. */
abstract class ParticipationHttpTestCase extends TestCase
{
    use RefreshDatabase;

    protected string $substrateSlug = 'http-lifecycle';

    protected array $trace = [];

    private int $httpRequestDepth = 0;

    protected function setUp(): void
    {
        // Rolled-back journeys leave PostgreSQL physical/statistical state behind.
        // A fresh schema keeps each complete HTTP scenario independent of that
        // state; migrations still finish before the fixture-write guard is armed.
        RefreshDatabaseState::$migrated = false;
        parent::setUp();
        Http::preventStrayRequests();
        $this->mock(PublicResourceAddress::class, fn ($mock) => $mock
            ->shouldReceive('resolve')->with('evidence.example.test')->andReturn('93.184.216.34'));
        Http::fake(['https://evidence.example.test/*' => fn ($request) => Http::response(
            'Deterministic external observation for '.$request->url(), 200, ['Content-Type' => 'text/plain'],
        )]);
        // Migrations finish in parent::setUp(). Thereafter even setup goes through HTTP.
        DB::connection()->beforeExecuting(function (string $sql): void {
            if ($this->httpRequestDepth === 0 && ! preg_match('/^\s*select\b/i', $sql)) {
                throw new LogicException('HTTP harness forbids fixture writes outside an application request.');
            }
        });
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->httpRequestDepth++;
        try {
            return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        } finally {
            $this->httpRequestDepth--;
        }
    }

    /** Source-equivalence tests may execute the real worker, never fixture writes. */
    protected function wakeParticipant(string $participantConnectionId): void
    {
        $this->httpRequestDepth++;
        try {
            $this->app->call([new WakeParticipant($participantConnectionId), 'handle']);
        } finally {
            $this->httpRequestDepth--;
        }
    }

    protected function startSubstrate(): void
    {
        $this->postJson('/api/bootstrap', [
            'name' => 'HTTP operator', 'email' => 'operator@example.test',
            'password' => 'deterministic-test-password', 'password_confirmation' => 'deterministic-test-password',
        ])->assertCreated();
        $this->postJson('/api/substrates', [
            'slug' => $this->substrateSlug, 'title' => 'HTTP lifecycle', 'visibility' => 'private',
        ])->assertCreated();
    }

    protected function joinParticipant(string $callsign): string
    {
        return $this->postJson('/api/substrates/'.$this->substrateSlug.'/participants', [
            'callsign' => $callsign, 'max_read_count' => 25,
        ])->assertCreated()->json('data.id');
    }

    protected function enterParticipant(string $participant, bool $establishOrigin = false): array
    {
        $payload = $establishOrigin ? ['origin' => [
            'statement' => 'An observed claim can be tested and reconsidered.',
            'attestation' => ['direction' => 'support'],
            'instrument' => ['kind' => 'hyperlink', 'reference' => 'https://evidence.example.test/origin'],
        ]] : [];

        return $this->postJson($this->endpoint($participant).'/enter', $payload)->assertCreated()->json('data');
    }

    protected function readParticipation(string $participant): array
    {
        return $this->getJson($this->endpoint($participant))->assertOk()->json('data');
    }

    protected function actionDefinitions(array $state): array
    {
        return $state['plan']['schema']['$defs']['actions'] ?? [];
    }

    protected function visibleProposition(array $state, string $id): array
    {
        foreach (['lineage', 'read_options', 'gate_options', 'write_options'] as $population) {
            foreach ($state['plan'][$population] ?? [] as $node) {
                if ($node['id'] === $id) {
                    return $node;
                }
            }
        }
        $this->fail('Proposition '.$id.' is missing from participation context. '.$this->traceMessage($state));
    }

    protected function reachFirstCommit(string $participant, string $origin): array
    {
        $state = $this->driveUntil($participant, fn ($state) => ($state['last_commitment']['proposition_id'] ?? $origin) !== $origin);
        $this->assertSame('distinguish', $state['plan']['phase']['kind'], $this->traceMessage($state));
        $this->assertSame($state['last_commitment']['proposition_id'], $state['plan']['gate_id']);

        return $state;
    }

    protected function assertActionAvailable(array $state, string $action): void
    {
        $this->assertArrayHasKey($action, $this->actionDefinitions($state), $this->traceMessage($state));
    }

    protected function assertActionUnavailable(array $state, string $action): void
    {
        $this->assertArrayNotHasKey($action, $this->actionDefinitions($state), $this->traceMessage($state));
    }

    protected function contribute(string $participant, array $input, ?array $state = null): array
    {
        // A driver may use the GET it just performed for this same request.
        $state ??= $this->readParticipation($participant);
        $this->assertSame('ready', $state['status'], $this->traceMessage($state));
        $this->assertActionAvailable($state, $input['action']);
        $this->trace[] = [
            'participant' => $participant, 'cycle' => $state['cycle']['sequence'],
            'phase' => $state['plan']['phase']['kind'], 'phase_id' => $state['plan']['phase']['id'],
            'horizon' => $state['context_horizon_id'], 'gate' => $state['plan']['gate_id'],
            'available' => array_keys($this->actionDefinitions($state)), 'input' => $input,
        ];
        if (getenv('COMMONS_TRACE_HTTP') === '1' && count($this->trace) % 25 === 0) {
            fwrite(STDERR, json_encode(['contributions' => count($this->trace), 'domain' => $state['domain'],
                'phase' => $state['plan']['phase']['kind'], 'last_commitment' => $state['last_commitment']]).PHP_EOL);
        }
        $response = $this->postContribution($participant, $state['plan']['phase']['id'], $input);
        $this->assertSame(200, $response->status(), $response->getContent().$this->traceMessage($state));

        return $response->json('data');
    }

    protected function postContribution(string $participant, string $phase, array $input): TestResponse
    {
        return $this->postJson($this->endpoint($participant), ['phase_id' => $phase, 'contribution' => $input]);
    }

    /** Select only published choices; scenarios can override this policy with explicit actions. */
    protected function chooseContribution(array $state): array
    {
        $actions = $this->actionDefinitions($state);
        foreach (['proposition.commit', 'proposition.reclassify', 'proposition.variant', 'proposition.reconcile',
            'proposition.traverse', 'attestation.create', 'instrument.execute', 'participant.rest', 'receipt.read'] as $action) {
            if (isset($actions[$action])) {
                $input = ['action' => $action];
                foreach (['proposition_id', 'other_proposition_id', 'receipt_id'] as $key) {
                    if (isset($actions[$action]['properties'][$key]['enum'])) {
                        $input[$key] = $actions[$action]['properties'][$key]['enum'][0];
                    }
                }
                if ($action === 'proposition.variant') {
                    $input['statement'] = 'Testable opportunity '.count($this->trace);
                }
                if ($action === 'instrument.execute') {
                    $input['instrument'] = ['kind' => 'hyperlink',
                        'reference' => 'https://evidence.example.test/observation/'.count($this->trace)];
                }
                if ($action === 'attestation.create') {
                    $choices = $actions[$action]['anyOf'];
                    $choice = collect($choices)->first(fn ($choice) => $choice['properties']['direction']['enum'][0] === 'support') ?? $choices[0];
                    $input['proposition_id'] = $choice['properties']['proposition_id']['enum'][0];
                    $input['direction'] = $choice['properties']['direction']['enum'][0];
                }

                return $input;
            }
        }
        $this->fail('No advertised action. '.$this->traceMessage($state));
    }

    protected function driveUntil(string $participant, callable $reached, int $maxActions = 100): array
    {
        for ($step = 0; $step <= $maxActions; $step++) {
            $state = $this->readParticipation($participant);
            if ($reached($state)) {
                return $state;
            }
            $this->assertSame('ready', $state['status'], $this->traceMessage($state));
            if ($step < $maxActions) {
                $this->contribute($participant, $this->chooseContribution($state), $state);
            }
        }
        $this->fail('HTTP action budget exhausted. '.$this->traceMessage($state));
    }

    protected function traceMessage(array $state = []): string
    {
        return json_encode(['trace' => $this->trace, 'state' => $state], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function endpoint(string $participant): string
    {
        return '/api/substrates/'.$this->substrateSlug.'/participants/'.$participant.'/participate';
    }
}
