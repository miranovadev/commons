<?php

namespace Tests\Support;

use App\Jobs\WakeParticipant;
use App\Services\Evidence\PublicResourceAddress;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use LogicException;
use Tests\Support\RefreshEvidenceDatabase as RefreshDatabase;

/** Real participation requests; only external DNS and HTTP evidence are deterministic. */
abstract class ParticipationHttpTestCase extends TestCase
{
    /** Fixed lawful two-edge history with phase-neutral Persist acquisition. */
    protected function developPersist(string $name): array
    {
        $this->startSubstrate();
        $actor = $this->joinParticipant($name);
        $initial = $this->enterParticipant($actor, true);
        $this->discoverGate($actor);
        $first = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'First distinction']);
        $this->contribute($actor, ['action' => 'participant.rest']);
        $first = $this->contribute($actor, ['action' => 'attestation.create',
            'proposition_id' => $first['current_proposition_id'], 'direction' => 'support']);
        $observed = $this->contribute($actor, ['action' => 'instrument.execute',
            'proposition_id' => $first['plan']['gate_id'],
            'instrument' => ['kind' => 'citation', 'reference' => $initial['current_proposition_id']]]);
        $this->assertSame($first['plan']['phase'], $observed['plan']['phase']);
        $this->assertSame($first['cycle'], $observed['cycle']);
        $state = $this->contribute($actor, ['action' => 'proposition.variant', 'statement' => 'Persistent development']);
        $this->assertSame('constrain', $state['plan']['phase']['kind']);

        return compact('actor', 'initial', 'first', 'observed', 'state');
    }

    /** Phase-neutral personal discovery, through the same HTTP contribution endpoint. */
    protected function discoverGate(string $actor): array
    {
        $state = $this->readParticipation($actor);

        return $this->contribute($actor, ['action' => 'instrument.execute', 'proposition_id' => $state['plan']['gate_id'],
            'instrument' => ['kind' => 'proposition_search', 'query' => 'Personal discovery '.$actor.' Cycle '.$state['cycle']['sequence'], 'limit' => 1]]);
    }

    protected function judge(string $participant, string $proposition): array
    {
        return $this->contribute($participant, ['action' => 'attestation.create', 'proposition_id' => $proposition, 'direction' => 'support']);
    }

    use RefreshDatabase;

    protected string $substrateSlug = 'http-lifecycle';

    protected array $trace = [];

    private int $httpRequestDepth = 0;

    protected function setUp(): void
    {
        // Rolled-back journeys leave PostgreSQL physical/statistical state behind.
        // Outside the Integration runner retain the existing fresh-schema setup.
        // Integration reclaims storage before the fixture-write guard is armed.
        if (getenv('COMMONS_INTEGRATION_MAINTENANCE') !== '1') {
            RefreshDatabaseState::$migrated = false;
        }
        parent::setUp();
        Http::preventStrayRequests();
        $this->mock(PublicResourceAddress::class, fn ($mock) => $mock
            ->shouldReceive('resolve')->with('evidence.example.test')->andReturn('93.184.216.34'));
        Http::fake(['https://evidence.example.test/*' => fn ($request) => Http::response(
            'Deterministic external observation for '.$request->url(), 200, ['Content-Type' => 'text/plain'],
        )]);
        // Migrations finish in parent::setUp(). Thereafter even setup goes through HTTP.
        DB::connection()->beforeExecuting(function (string $sql): void {
            if ($this->httpRequestDepth !== 0) {
                return;
            }
            // Read-only observation also uses parenthesized UNIONs and recursive
            // committed-ancestry SELECTs. Never allow a data-modifying CTE here.
            $read = preg_match('/^\s*(?:\(*\s*select\b|with\s+recursive\b)/i', $sql)
                && ! preg_match('/\b(?:insert|update|delete|merge|create|alter|drop|truncate|call|copy)\b|;/i', $sql);
            if (! $read) {
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

    protected function prepareVariant(string $participant, string $statement): array
    {
        $state = $this->readParticipation($participant);
        $this->assertActionAvailable($state, 'preparation.start');
        $this->assertActionUnavailable($state, 'proposition.variant');
        $source = $state['plan']['preparation_options'][0];
        $started = $this->contribute($participant, ['action' => 'preparation.start',
            'proposition_id' => $state['plan']['gate_id'], 'purpose' => 'Examine preserved evidence before exploring this continuation.',
            'artifacts' => [['receipt_id' => $source['receipt_id'], 'artifact_id' => $source['artifact_id'],
                'read_type' => 'text', 'parameters' => ['max_chars' => 4000]]]], $state);
        $this->assertSame($state['plan']['phase'], $started['plan']['phase']);
        $id = $started['plan']['preparation']['execution_id'];
        $this->readAndAcknowledgePreparation($participant, $id);

        return $this->contribute($participant, ['action' => 'proposition.variant', 'execution_id' => $id, 'statement' => $statement]);
    }

    protected function readAndAcknowledgePreparation(string $participant, string $execution): void
    {
        $url = '/api/substrates/'.$this->substrateSlug.'/participants/'.$participant.'/preparations/'.$execution;
        $delivery = $this->getJson($url.'/reading')->assertOk()->json('data');
        $this->assertNotEmpty($delivery['deliveries']);
        $this->postJson($url.'/presented', ['presentation_token' => $delivery['presentation_token']])->assertOk();
    }

    protected function postContribution(string $participant, string $phase, array $input): TestResponse
    {
        return $this->postJson($this->endpoint($participant), ['phase_id' => $phase, 'contribution' => $input]);
    }

    /** Select only published choices; scenarios can override this policy with explicit actions. */
    protected function chooseContribution(array $state): array
    {
        $actions = $this->actionDefinitions($state);
        foreach (['instrument.execute', 'proposition.commit', 'proposition.reclassify', 'proposition.variant', 'proposition.reconcile',
            'proposition.traverse', 'attestation.create', 'participant.rest'] as $action) {
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

    protected function driveUntil(string $participant, callable $reached, int $maxActions = 100, ?string $preferredReceipt = null): array
    {
        $lastCommitment = null;
        for ($step = 0; $step <= $maxActions; $step++) {
            $state = $this->readParticipation($participant);
            $lastCommitment = $state['plan']['commitment'] ?? $lastCommitment;
            if ($reached($state)) {
                return $state;
            }
            $this->assertSame('ready', $state['status'], $this->traceMessage($state));
            if ($step < $maxActions) {
                $input = $this->chooseContribution($state);
                if ($preferredReceipt !== null && $input['action'] === 'proposition.reclassify') {
                    $this->assertContains($preferredReceipt, $this->actionDefinitions($state)['proposition.reclassify']['properties']['receipt_id']['enum']);
                    $input['receipt_id'] = $preferredReceipt;
                }
                $this->contribute($participant, $input, $state);
            }
        }
        $this->fail('HTTP action budget exhausted. Last Commit eligibility: '.json_encode($lastCommitment)
            .'; '.$this->traceMessage($state));
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
