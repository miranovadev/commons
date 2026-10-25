<?php

namespace Tests\Diagnostics;

use App\Models\Connection;
use App\Models\Participant;
use App\Models\Phase;
use App\Services\C2ReceiptProjection;
use App\Services\CognitiveEvidenceHorizon;
use App\Services\InputPlanService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipantReceiptProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Actual PHP/PostgreSQL projections over text-redacted recorded data; no LLM calls. */
class ParticipantContextC3ComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorded_populations_with_identical_operational_plans(): void
    {
        $this->assertSame('pgsql', DB::getDriverName(), 'Recorded fixture validation requires PostgreSQL.');
        $this->assertSame('testing', DB::connection()->getDatabaseName(), 'Restore only the disposable CI test database.');
        $fixture = json_decode(gzdecode(file_get_contents(base_path('tests/Fixtures/c3/recorded-populations.json.gz'))), true, flags: JSON_THROW_ON_ERROR);
        // Schema-only adaptation of the fixed historical diagnostic, not a
        // production compatibility layer or a reconstructed observation history.
        $cycles = array_column($fixture['tables']['phases'], 'cycle_id', 'id');
        $fixture['tables']['evidence_executions'] = array_map(fn ($row) => [...$row,
            'type' => 'instrument', 'cycle_id' => $cycles[$row['phase_id']]], $fixture['tables']['instrument_executions']);
        unset($fixture['tables']['instrument_executions']);
        foreach ($fixture['tables']['receipts'] as &$receipt) {
            $receipt['evidence_execution_id'] = $receipt['instrument_execution_id'];
            unset($receipt['instrument_execution_id']);
        }
        unset($receipt);
        // Stage B intentionally has no compatibility migration from shared
        // failures. Reproject the captured structure/evidence with empty personal
        // history; never relabel a global failure as someone's Rest encounter.
        foreach (['continuation_failures', 'horizon_exploration_states', 'horizon_failure_witnesses'] as $retired) {
            unset($fixture['tables'][$retired]);
        }
        // Restore a captured graph, not a new lifecycle. Replica mode handles
        // circular foreign keys during this isolated import only.
        DB::statement("SET LOCAL session_replication_role = 'replica'");
        try {
            foreach ($fixture['tables'] as $table => $rows) {
                foreach (array_chunk($rows, 100) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
        } catch (\Throwable $error) {
            // The outer test transaction rolls back SET LOCAL too. Do not mask
            // an import failure with a command in an already-aborted transaction.
            throw $error;
        }
        DB::statement("SET LOCAL session_replication_role = 'origin'");
        $base = getenv('COMMONS_CONTEXT_BASELINE_DIR');
        $this->assertNotEmpty($base);
        $source = file_get_contents($base.'/app/Services/ParticipantReceiptProjection.php');
        // Adapt only the captured baseline's execution relationship spelling; its projection policy is unchanged.
        $source = str_replace(['instrumentExecution', 'instrument_execution_id'], ['evidenceExecution', 'evidence_execution_id'], $source);
        eval(str_replace('final class ParticipantReceiptProjection', 'final class C2ReceiptProjection', substr($source, 5)));
        $old = new C2ReceiptProjection;
        $new = new ParticipantReceiptProjection;
        $factory = app(ParticipantPromptFactory::class);
        $connection = (new Connection)->forceFill(['configuration' => ['model' => 'test-model']]);
        $cases = json_decode(file_get_contents(base_path('tests/Fixtures/c3/recorded-cases.json')), true, flags: JSON_THROW_ON_ERROR);
        $before = $this->snapshot($fixture['tables']);
        $rows = [];
        foreach ($cases as $case) {
            $phase = Phase::findOrFail($case['phase']);
            $participant = Participant::findOrFail($phase->cycle->participant_id);
            $start = hrtime(true);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $raw = app(InputPlanService::class)->forPhase($participant, $phase);
            $planProfile = ['queries' => count(DB::getQueryLog()), 'milliseconds' => (hrtime(true) - $start) / 1e6];
            DB::disableQueryLog();
            $state = ['participant' => $participant->only(['id', 'callsign', 'designation']),
                'plan' => app(ParticipantContextSerializer::class)->plan($raw)];
            $profiles = $packets = [];
            foreach (['c2' => $old, 'c3' => $new] as $version => $projection) {
                $this->app->instance(ParticipantReceiptProjection::class, $projection);
                $times = [];
                for ($repeat = 0; $repeat < 3; $repeat++) {
                    DB::flushQueryLog();
                    DB::enableQueryLog();
                    $start = hrtime(true);
                    $prompt = $factory->make($connection, $state);
                    $times[] = (hrtime(true) - $start) / 1e6;
                    $queries = DB::getQueryLog();
                    DB::disableQueryLog();
                    foreach ($queries as $query) {
                        $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|alter|create|drop)\b/i', $query['query']);
                    }
                }
                if ($version === 'c2') {
                    $prompt['system'] = str_replace('Your designation describes your perspective on the subject, not a fact about the subject itself. ', '', $prompt['system']);
                }
                sort($times);
                $packet = json_decode($prompt['user'], true, flags: JSON_THROW_ON_ERROR);
                $packets[$version] = $packet;
                $receipts = $packet['receipts'];
                $bytes = strlen($prompt['system']) + strlen($prompt['user']) + strlen($prompt['evidence']) + strlen($prompt['support']);
                $profiles[$version] = [
                    'eligible_receipts' => count($receipts), 'loaded_receipts' => count(array_filter($receipts, fn ($r) => $r['loaded'])),
                    'visible_bodies' => count(array_filter($receipts, fn ($r) => isset($r['loaded_inline_content']))),
                    'evidence_bytes' => array_sum(array_map(fn ($r) => isset($r['loaded_inline_content']) ? strlen(json_encode($r['loaded_inline_content'], JSON_UNESCAPED_SLASHES)) : 0, $receipts)),
                    'prompt_bytes' => $bytes, 'estimated_input_tokens_bytes_div_4' => (int) ceil($bytes / 4),
                    'database_queries' => count($queries), 'projection_median_ms' => round($times[1], 3),
                    'duplicate_catalogue_entries' => count($receipts) - count(array_unique(array_column($receipts, 'id'))),
                ];
            }
            $this->assertSame($packets['c2']['actions'], $packets['c3']['actions']);
            foreach (['read', 'gate', 'write'] as $role) {
                $this->assertSame($packets['c2'][$role], $packets['c3'][$role]);
                foreach ($packets['c3'][$role]['propositions'] as $node) {
                    foreach ($node['receipts'] as $id) {
                        $this->assertCount(1, array_filter($packets['c3']['receipts'], fn ($r) => $r['id'] === $id));
                    }
                }
            }
            $selected = app(CognitiveEvidenceHorizon::class)->propositionIds($state['plan'])
                ->merge(collect(['read', 'gate', 'write'])->flatMap(fn ($role) => array_column($packets['c3'][$role]['propositions'], 'id')))->unique();
            // Association count is diagnostic-only and deliberately outside timing.
            $method = new \ReflectionMethod(ParticipantReceiptProjection::class, 'referencesFor');
            $associations = $method->invoke($new, $selected)->flatten(1)->pluck('id');
            $rows[] = [...$case, 'plan' => $planProfile, 'projections' => $profiles,
                'qualifying_associations' => $associations->count(),
                'duplicate_associations_eliminated' => $associations->count() - $associations->unique()->count(),
                'actions_and_registers_equal' => true];
        }
        $this->app->instance(ParticipantReceiptProjection::class, $new);
        $this->assertSame($before, $this->snapshot($fixture['tables']));
        $report = ['base' => '2b0612a150dea6e106b72cb9b2227d07593a78e4', 'php' => PHP_VERSION,
            'database' => DB::selectOne('select version() as version')->version,
            'source_sha256' => $fixture['source_sha256'], 'method' => $fixture['method'],
            'notes' => ['Three repetitions per projection, median runtime; actual SQL count.',
                'Full prompt bytes include system/user; strict response schema and provider transport excluded.',
                'Tokens estimated as bytes/4, not billed tokens. Recorded state reprojection, not historical prompt reconstruction.',
                'Operational plan creation measured separately; identical plans supplied to C2 and C3.',
                'Retired shared exploration tables omitted; no personal Exploration Events reconstructed.',
                'Whole restored domain snapshot unchanged after plan and prompt projection.'], 'cases' => $rows];
        file_put_contents(base_path('test-results/c3-performance.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        fwrite(STDERR, "\nC3_PERFORMANCE ".json_encode($report, JSON_UNESCAPED_SLASHES)."\n");
    }

    private function snapshot(array $tables): array
    {
        return collect(array_keys($tables))->mapWithKeys(fn ($table) => [$table => hash('sha256', DB::table($table)->get()
            ->map(fn ($row) => json_encode($row))->sort()->values()->toJson())])->all();
    }
}
