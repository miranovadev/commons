<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Exceptions\ArtifactStorageException;
use App\Jobs\WakeParticipant;
use App\Models\Attestation;
use App\Models\Connection;
use App\Models\EvidenceExecution;
use App\Models\ParticipantConnection;
use App\Models\Proposition;
use App\Models\Receipt;
use App\Models\User;
use App\Services\ContributionDispatcher;
use App\Services\ContributionService;
use App\Services\Evidence\ArtifactStore;
use App\Services\ExplorationEpochService;
use App\Services\HorizonReceiptService;
use App\Services\InputPlanService;
use App\Services\LedgerService;
use App\Services\ParticipationService;
use App\Services\PotentialProjectionService;
use App\Services\PreparationService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\PreparationFixture;
use Tests\TestCase;

class PreparationJourneyTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('artifacts');
    }

    public function test_disperse_availability_changes_with_shared_consumption_and_completed_deferment(): void
    {
        $fixture = new PreparationFixture(available: true);
        $f = $fixture->context;
        $plan = fn () => app(InputPlanService::class)->forPhase($f->viewer, $f->phase);
        $this->assertArrayHasKey('proposition.variant', $plan()['schema']['$defs']['actions']);
        $this->assertArrayNotHasKey('preparation.start', $plan()['schema']['$defs']['actions']);
        $fixture->consume($fixture->source);
        $this->assertArrayHasKey('preparation.start', $plan()['schema']['$defs']['actions']);
        $this->assertArrayNotHasKey('proposition.variant', $plan()['schema']['$defs']['actions']);
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $delivery = $service->delivery($f->viewer, $execution, 'human-text');
        $this->assertSame('pending', $execution->fresh()->status);
        $this->assertSame('preserved artifact body', $delivery['deliveries'][0]['content']);
        $service->acknowledge($f->viewer, $execution, $delivery['presentation_token']);
        $service->acknowledge($f->viewer, $execution, $delivery['presentation_token']);
        $receipt = $service->defer($f->viewer, $execution, '  I need another comparison.  ');
        $this->assertSame('  I need another comparison.  ', $receipt->metadata['outcome']['reason']);
        $this->assertNull($f->phase->fresh()->completed_at);
        $this->assertNull($receipt->consumption);
        $this->assertArrayHasKey('proposition.variant', $plan()['schema']['$defs']['actions']);
        $this->assertArrayNotHasKey('preparation.start', $plan()['schema']['$defs']['actions']);
        $fixture->consume($receipt);
        $actions = $plan()['schema']['$defs']['actions'];
        $this->assertArrayNotHasKey('proposition.variant', $actions);
        $this->assertArrayNotHasKey('preparation.start', $actions);
        $this->assertArrayHasKey('participant.rest', $actions);
        $fixture->receipt('Another Participant observation', available: true);
        $this->assertArrayHasKey('proposition.variant', $plan()['schema']['$defs']['actions']);
    }

    public function test_prepared_contribution_atomically_creates_unattached_receipt_without_prior_available_evidence(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        app(PotentialProjectionService::class)->refresh($f->substrate->id);
        $service = app(ParticipationService::class);
        $started = $service->contribute($f->viewer, $f->phase->id, ['action' => 'preparation.start', 'proposition_id' => $f->gate->id, ...$fixture->input()]);
        $id = $started['plan']['preparation']['execution_id'];
        $execution = EvidenceExecution::findOrFail($id);
        $this->assertSame($f->phase->id, $started['plan']['phase']['id']);
        $this->assertTrue(app(HorizonReceiptService::class)->ordinaryAvailable($f->phase)->isEmpty());
        $reading = app(PreparationService::class)->delivery($f->viewer, $execution, 'inline-text');
        app(PreparationService::class)->acknowledge($f->viewer, $execution, $reading['presentation_token']);
        $state = $service->contribute($f->viewer, $f->phase->id, ['action' => 'proposition.variant', 'execution_id' => $id, 'statement' => 'The prepared contribution.']);
        $this->assertSame(PhaseKind::Mediate->value, $state['plan']['phase']['number']);
        $receipt = $execution->fresh()->receipt;
        $this->assertSame($f->gate->id, $receipt->receiptable_id);
        $this->assertSame($execution->fresh()->resulting_proposition_id, $receipt->metadata['outcome']['proposition_id']);
        $this->assertNull($receipt->consumption);
        $this->assertStringNotContainsString('preserved artifact body', json_encode($receipt->metadata));
        $this->assertStringNotContainsString('prepared_context', json_encode($state));
    }

    public function test_identical_deliveries_are_shared_but_different_read_requests_are_not_collapsed(): void
    {
        $fixture = new PreparationFixture;
        $second = $fixture->receipt('preserved artifact body');
        $input = $fixture->input([$fixture->source, $second]);
        $input['artifacts'][] = [...$input['artifacts'][0], 'read_type' => 'text', 'parameters' => ['offset' => 2, 'max_chars' => 5]];
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $input);
        $reading = $service->delivery($f->viewer, $execution, 'human-text');
        $this->assertCount(3, $reading['selections']);
        $this->assertCount(2, $reading['deliveries']);
        $this->assertSame('eserv', $reading['deliveries'][1]['content']);
        $service->acknowledge($f->viewer, $execution, $reading['presentation_token']);
        $receipt = $service->defer($f->viewer, $execution, 'Compare later.');
        $this->assertCount(3, $receipt->metadata['selections']);
        $this->assertCount(2, $receipt->metadata['deliveries']);
    }

    public function test_unsupported_request_records_method_and_artifact_failure_without_receipt(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $input = $fixture->input();
        $input['artifacts'][0]['read_type'] = 'regex';
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $input);
        try {
            $service->delivery($f->viewer, $execution, 'inline-text');
            $this->fail('Unsupported reading accepted.');
        } catch (\DomainException) {
            $this->assertSame('failed', $execution->fresh()->status);
            $this->assertSame('missing_capability', $execution->fresh()->metadata['failure']['classification']);
            $this->assertSame('regex', $execution->fresh()->metadata['failure']['read_type']);
            $this->assertSame($input['artifacts'][0]['artifact_id'], $execution->fresh()->metadata['failure']['artifact_id']);
            $this->assertNull($execution->fresh()->receipt);
            $this->assertNull($execution->fresh()->presented_at);
        }
    }

    public function test_stale_direct_generation_and_unfinished_preparation_cannot_bypass_authority(): void
    {
        $fixture = new PreparationFixture(available: true);
        $f = $fixture->context;
        $plan = app(InputPlanService::class)->forPhase($f->viewer, $f->phase);
        $input = ['action' => 'proposition.variant', 'statement' => 'Stale generation.'];
        app(ContributionService::class)->validateSchema($plan['schema'], $input);
        $fixture->consume($fixture->source);
        try {
            app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, $input);
            $this->fail('Stale evidence bypassed.');
        } catch (ConflictHttpException) {
            $this->assertDatabaseMissing('propositions', ['statement' => $input['statement']]);
        }
        app(PreparationService::class)->start($f->viewer, $f->phase, $fixture->input());
        $fixture->receipt('New shared evidence', available: true);
        $this->expectException(ConflictHttpException::class);
        app(ContributionDispatcher::class)->dispatch($f->viewer, $f->phase, $input);
    }

    public function test_expired_preparation_does_not_orphan_participant_or_infer_deferment(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $this->travel(31)->minutes();
        $service->recover($f->viewer, $f->phase);
        $this->assertSame('failed', $execution->fresh()->status);
        $this->assertNull($execution->fresh()->receipt);
        $this->assertNull($execution->fresh()->outcome);
        $this->assertNull($service->active($f->phase));
        $this->assertArrayHasKey('participant.rest', app(InputPlanService::class)->forPhase($f->viewer, $f->phase)['schema']['$defs']['actions']);
    }

    public function test_combined_delivery_budget_fails_without_partial_success_or_receipt(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $sources = [];
        for ($index = 0; $index < 4; $index++) {
            $sources[] = DB::transaction(fn () => $fixture->receipt(str_repeat('x', 14000).$index));
        }
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input($sources));
        try {
            $service->delivery($f->viewer, $execution, 'human-text');
            $this->fail('The combined budget was bypassed.');
        } catch (\DomainException) {
            $execution->refresh();
            $this->assertSame('failed', $execution->status);
            $this->assertSame('payload_limit', $execution->metadata['failure']['classification']);
            $this->assertNull($execution->presentation);
            $this->assertNull($execution->receipt);
            $this->assertNull($f->phase->fresh()->completed_at);
        }
    }

    public function test_empty_preserved_material_can_be_delivered_and_resolved_without_fabricated_content(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $source = DB::transaction(fn () => $fixture->receipt(''));
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input([$source]));
        $delivery = $service->delivery($f->viewer, $execution, 'human-text');
        $this->assertSame('', $delivery['deliveries'][0]['content']);
        $this->assertSame(0, $delivery['deliveries'][0]['payload_bytes']);
        $service->acknowledge($f->viewer, $execution, $delivery['presentation_token']);
        $receipt = $service->defer($f->viewer, $execution, 'This preserved resource was empty.');
        $this->assertTrue($receipt->isEvidence());
        $this->assertNull($receipt->consumption);
    }

    public function test_artifact_storage_rejects_path_escape_and_reads_inside_write_transactions(): void
    {
        $fixture = new PreparationFixture;
        $artifact = $fixture->source->metadata['artifacts'][0];
        try {
            app(ArtifactStore::class)->read([...$artifact, 'path' => '../private']);
            $this->fail('Invalid path accepted.');
        } catch (ArtifactStorageException) {
            $this->assertSame('preserved artifact body', app(ArtifactStore::class)->read($artifact));
        }
        $this->expectException(ArtifactStorageException::class);
        DB::transaction(fn () => app(ArtifactStore::class)->read($artifact));
    }

    public function test_human_workspace_reads_acknowledges_and_defers_through_the_same_domain(): void
    {
        $this->postJson('/api/bootstrap', ['name' => 'Owner', 'email' => 'owner@example.test',
            'password' => 'test-password-1234', 'password_confirmation' => 'test-password-1234'])->assertCreated();
        $this->actingAs(User::firstOrFail());
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $this->publishPotentialFixture($f->viewer);
        $url = '/api/substrates/'.$f->substrate->slug.'/participants/'.$f->viewer->id;
        $plan = $this->getJson($url.'/workspace')->assertOk()->json('data.plan');
        $this->assertArrayHasKey('preparation.start', $plan['schema']['$defs']['actions']);
        $this->assertSame(['raw', 'text'], array_column($plan['preparation_options'][0]['capabilities'], 'read_type'));
        $started = $this->postJson($url.'/participate', ['phase_id' => $f->phase->id,
            'contribution' => ['action' => 'preparation.start', 'proposition_id' => $f->gate->id, ...$fixture->input()]])->assertOk();
        $id = $started->json('data.plan.preparation.execution_id');
        $reading = $this->getJson($url.'/preparations/'.$id.'/reading')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('data');
        $this->assertSame('preserved artifact body', $reading['deliveries'][0]['content']);
        $this->assertSame('pending', EvidenceExecution::findOrFail($id)->status);
        $resolution = ['action' => 'preparation.defer', 'execution_id' => $id, 'reason' => '  More investigation is needed. 💚  '];
        $this->postJson($url.'/participate', ['phase_id' => $f->phase->id, 'contribution' => $resolution])->assertConflict();
        $this->postJson($url.'/preparations/'.$id.'/presented', ['presentation_token' => $reading['presentation_token']])->assertOk();
        $this->postJson($url.'/preparations/'.$id.'/presented', ['presentation_token' => $reading['presentation_token']])->assertOk();
        $resolved = $this->postJson($url.'/participate', ['phase_id' => $f->phase->id, 'contribution' => $resolution])->assertOk();
        $this->assertSame($f->phase->id, $resolved->json('data.plan.phase.id'));
        $this->assertArrayHasKey('proposition.variant', $resolved->json('data.plan.schema.$defs.actions'));
        $this->assertSame($resolution['reason'], EvidenceExecution::findOrFail($id)->receipt->metadata['outcome']['reason']);
        $this->getJson($url.'/preparations/'.$id.'/reading')->assertConflict();
        $this->postJson($url.'/participate', ['phase_id' => $f->phase->id, 'contribution' => $resolution])->assertConflict();
        $this->assertSame(1, Receipt::where('type', 'preparation')->count());
    }

    private function refutedWrite(PreparationFixture $fixture): Proposition
    {
        return DB::transaction(function () use ($fixture) {
            $f = $fixture->context;
            $head = $f->node($f->gate, 'activity', position: PhaseKind::Disperse);
            $vote = (new Attestation)->forceFill(['phase_id' => $head->phase_id,
                'proposition_id' => $head->id, 'direction' => 'refute']);
            $vote->save();
            app(LedgerService::class)->record($vote);

            return $head;
        });
    }

    public function test_human_prepared_revision_uses_existing_ownership_and_structural_progression(): void
    {
        $this->postJson('/api/bootstrap', ['name' => 'Owner', 'email' => 'owner@example.test',
            'password' => 'test-password-1234', 'password_confirmation' => 'test-password-1234'])->assertCreated();
        $this->actingAs(User::firstOrFail());
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $head = $this->refutedWrite($fixture);
        $this->publishPotentialFixture($f->viewer);
        $epoch = app(ExplorationEpochService::class)->forPhase($f->phase);
        $url = '/api/substrates/'.$f->substrate->slug.'/participants/'.$f->viewer->id;
        $started = $this->postJson($url.'/participate', ['phase_id' => $f->phase->id,
            'contribution' => ['action' => 'preparation.start', 'proposition_id' => $f->gate->id, ...$fixture->input()]])->assertOk();
        $id = $started->json('data.plan.preparation.execution_id');
        $delivery = $this->getJson($url.'/preparations/'.$id.'/reading')->assertOk()->json('data');
        $this->postJson($url.'/preparations/'.$id.'/presented', ['presentation_token' => $delivery['presentation_token']])->assertOk();
        $this->postJson($url.'/participate', ['phase_id' => $f->phase->id, 'contribution' => [
            'action' => 'proposition.revise', 'proposition_id' => $head->id, 'execution_id' => $id,
            'statement' => 'Reconsider this account after reading its source.']])->assertOk()->assertJsonPath('data.plan.phase.number', 5);
        $execution = EvidenceExecution::findOrFail($id);
        $this->assertSame([$head->id], $execution->resultingProposition->parents()->pluck('propositions.id')->all());
        $this->assertSame($epoch, app(ExplorationEpochService::class)->forPhase($f->phase));
        $this->assertSame('contributed', $execution->outcome);
        $this->assertNull($execution->receipt->consumption);
    }

    public static function autonomousOutcomes(): array
    {
        return [['contributed'], ['deferred'], ['failed'], ['revised']];
    }

    #[DataProvider('autonomousOutcomes')]
    public function test_autonomous_wake_delivers_actual_content_in_same_phase_and_uses_resolution_only_schema(string $outcome): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $revision = $outcome === 'revised' ? $this->refutedWrite($fixture) : null;
        $this->publishPotentialFixture($f->viewer);
        $connection = (new Connection)->forceFill(['name' => 'Preparation transport', 'provider' => 'openai',
            'configuration' => ['model' => 'gpt-test'], 'credentials' => ['api_key' => 'test-secret']]);
        $connection->save();
        $link = (new ParticipantConnection)->forceFill(['participant_id' => $f->viewer->id, 'connection_id' => $connection->id,
            'is_enabled' => true, 'configuration' => ['trigger' => ['mode' => 'manual'], 'wake' => ['termination' => 'phase']]]);
        $link->save();
        $requests = [];
        Http::fake(function ($request) use (&$requests, $fixture, $f, $outcome, $revision) {
            $payload = $request->data();
            $requests[] = $payload;
            if (count($requests) === 1) {
                $this->assertStringNotContainsString('preserved artifact body', json_encode($payload));
                $input = ['action' => 'preparation.start', 'proposition_id' => $f->gate->id, ...$fixture->input()];
            } else {
                $execution = EvidenceExecution::where('type', 'prepare')->sole();
                if ($execution->status !== 'completed') {
                    $this->assertNull($f->phase->fresh()->completed_at);
                    $this->assertStringContainsString('preserved artifact body', $payload['input'][1]['content']);
                    $this->assertStringContainsString($fixture->input()['purpose'], $payload['input'][1]['content']);
                    $this->assertStringContainsString('untrusted preserved material', $payload['input'][1]['content']);
                    $schema = $payload['text']['format']['schema'];
                    $this->assertStringNotContainsString('attestation.create', json_encode($schema));
                    $this->assertStringNotContainsString('preparation.start', json_encode($schema));
                    $this->assertStringNotContainsString('participant.rest', json_encode($schema));
                    if ($outcome === 'failed') {
                        return Http::response(['error' => 'API unavailable'], 503);
                    }
                    $input = $outcome === 'deferred'
                        ? ['action' => 'preparation.defer', 'execution_id' => $execution->id, 'reason' => 'Compare more sources.']
                        : ['action' => $revision ? 'proposition.revise' : 'proposition.variant', 'execution_id' => $execution->id,
                            ...($revision ? ['proposition_id' => $revision->id] : []), 'statement' => 'Prepared autonomous contribution.'];
                } else {
                    $this->assertSame('deferred', $execution->outcome);
                    $this->assertStringNotContainsString('preserved artifact body', json_encode($payload));
                    $input = ['action' => 'proposition.variant', 'statement' => 'Ordinary generation after deferment.'];
                }
            }

            return Http::response(['id' => 'test-prepared-response',
                'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['contribution' => $input])]]]]]);
        });
        try {
            app()->call([new WakeParticipant($link->id), 'handle']);
            $this->assertNotSame('failed', $outcome);
        } catch (RequestException) {
            $this->assertSame('failed', $outcome);
        }
        $execution = EvidenceExecution::where('type', 'prepare')->sole();
        $this->assertSame($f->phase->id, $execution->phase_id);
        if ($outcome === 'failed') {
            $this->assertSame('failed', $execution->status);
            $this->assertNull($execution->presented_at);
            $this->assertNull($execution->receipt);
            $this->assertNull($f->phase->fresh()->completed_at);
            $this->assertNull(app(PreparationService::class)->active($f->phase));
        } else {
            $this->assertSame($outcome === 'revised' ? 'contributed' : $outcome, $execution->outcome);
            $this->assertNotNull($f->phase->fresh()->completed_at);
            $this->assertNull($execution->receipt->consumption);
            $this->assertCount($outcome === 'deferred' ? 3 : 2, $requests);
        }
        $this->assertStringNotContainsString('preserved artifact body', json_encode($execution->toArray()));
        if ($outcome !== 'revised' && ($path = getenv('COMMONS_PREPARATION_READING_EXAMPLES'))) {
            $examples = is_file($path) ? json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) : [];
            $examples[$outcome] = ['source' => 'Isolated automated journey, real application request with deterministic provider response',
                'execution' => $execution->getAttributes(), 'receipt' => $execution->receipt?->getAttributes(),
                'prepared_inference_request' => $requests[1],
                'following_ordinary_request' => $outcome === 'deferred' ? $requests[2] : null];
            file_put_contents($path, json_encode($examples, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

    }
}
