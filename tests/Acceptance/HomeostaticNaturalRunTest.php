<?php

namespace Tests\Acceptance;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Phase;
use App\Models\Proposition;
use App\Services\CommitRegionService;
use App\Services\MediationReadService;
use App\Services\Participation\ParticipationContext;
use App\Services\PhaseGenerationService;
use App\Services\PropositionMeasurementService;
use Illuminate\Support\Facades\DB;
use Tests\Support\ParticipationHttpTestCase;

/** Fresh interleaved HTTP probes, not a replay or a target action distribution. */
class HomeostaticNaturalRunTest extends ParticipationHttpTestCase
{
    private bool $measuring = false;
    private int $queries = 0;
    private float $sqlMs = 0;
    private array $queryShapes = [];

    public function beginDatabaseTransaction(): void
    {
        // The parent still migrates a fresh schema before arming its write guard.
        // A sustained probe must commit each real HTTP request, not hold hundreds
        // of requests inside RefreshDatabase's one rollback-only transaction.
        // That artificial transaction prevents normal PostgreSQL tuple cleanup.
    }

    public function test_schema_driven_multi_participant_trace(): void
    {
        $policy = getenv('COMMONS_NATURAL_POLICY') ?: 'local-first';
        $this->assertContains($policy, ['local-first', 'field-first']);
        $directory = base_path('test-results/natural/'.$policy);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $stream = fopen($directory.'/trace.jsonl', 'w');
        $summary = ['policy' => $policy, 'head' => getenv('GITHUB_SHA') ?: null, 'completed' => false,
            'budget' => 600, 'actions' => [], 'additional_selected' => [], 'additional_offered' => [],
            'local_and_additional' => [], 'waiting' => [], 'rest_rollovers' => 0,
            'recovery_relocations' => 0, 'entry_attempts' => 0, 'windows' => [], 'participants' => []];
        DB::listen(function ($query): void {
            if ($this->measuring) {
                $this->queries++;
                $this->sqlMs += $query->time;
                $shape = preg_replace('/\?(?:,\s*\?)+/', '?', $query->sql);
                $key = sha1($shape);
                $this->queryShapes[$key] ??= ['sql' => $shape, 'queries' => 0, 'sql_ms' => 0, 'max_ms' => 0];
                $this->queryShapes[$key]['queries']++;
                $this->queryShapes[$key]['sql_ms'] += $query->time;
                $this->queryShapes[$key]['max_ms'] = max($this->queryShapes[$key]['max_ms'], $query->time);
            }
        });
        $started = microtime(true);
        try {
            $this->startSubstrate();
            $actors = [];
            foreach (['Ada', 'Bea', 'Cai'] as $index => $name) {
                $actors[] = $actor = $this->joinParticipant($name);
                $this->enterParticipant($actor, $index === 0);
            }
            for ($step = 0; $step < $summary['budget']; $step++) {
                $actor = $actors[$step % count($actors)];
                $this->queries = 0;
                $this->sqlMs = 0;
                $requestStart = microtime(true);
                $this->measuring = true;
                $state = $this->readParticipation($actor);
                $this->measuring = false;
                $httpSeconds = microtime(true) - $requestStart;
                $this->assertSame(0, DB::connection()->transactionLevel());
                if ($state['status'] !== 'ready') {
                    $reason = $state['reason']['code'];
                    $summary['waiting'][$reason] = ($summary['waiting'][$reason] ?? 0) + 1;
                    if ($state['entry']['available'] ?? false) {
                        $summary['entry_attempts']++;
                        $this->enterParticipant($actor);
                    }
                    fwrite($stream, json_encode(['step' => $step, 'participant' => $actor, 'waiting' => $state]).PHP_EOL);
                    continue;
                }
                $actions = $this->actionDefinitions($state);
                if (isset($actions['participant.rest'])) {
                    $this->assertSame(['participant.rest'], array_keys($actions));
                }
                $phase = Phase::findOrFail($state['plan']['phase']['id']);
                $cycle = $phase->cycle;
                $entry = $cycle->entry_committal_id;
                $additional = $this->additionalChoices($phase, $state);
                $input = parent::chooseContribution($state);
                foreach ($additional as $action => $ids) {
                    if ($ids === []) {
                        continue;
                    }
                    $summary['additional_offered'][$action] = ($summary['additional_offered'][$action] ?? 0) + 1;
                    $key = $action === 'proposition.reconcile' ? 'other_proposition_id' : 'proposition_id';
                    $local = array_values(array_diff($actions[$action]['properties'][$key]['enum'], $ids));
                    if ($local !== [] || ($phase->kind === PhaseKind::Order && isset($actions['proposition.commit']))) {
                        $summary['local_and_additional'][$action] = ($summary['local_and_additional'][$action] ?? 0) + 1;
                    }
                    if ($policy === 'field-first') {
                        $input = ['action' => $action];
                        foreach (['proposition_id', 'other_proposition_id', 'receipt_id'] as $property) {
                            if (isset($actions[$action]['properties'][$property]['enum'])) {
                                $input[$property] = $actions[$action]['properties'][$property]['enum'][0];
                            }
                        }
                        $input[$key] = $ids[0];
                    } elseif ($input['action'] === $action && $local !== []) {
                        $input[$key] = $local[0];
                    }
                }
                $action = $input['action'];
                $key = $action === 'proposition.reconcile' ? 'other_proposition_id' : 'proposition_id';
                $isAdditional = in_array($input[$key] ?? null, $additional[$action] ?? [], true);
                $before = $action === 'proposition.traverse' ? $this->causalCounts() : null;
                $this->measuring = true;
                $requestStart = microtime(true);
                $after = $this->contribute($actor, $input, $state);
                $this->measuring = false;
                $httpSeconds += microtime(true) - $requestStart;
                $this->assertSame(0, DB::connection()->transactionLevel());
                $this->assertSame($entry, $cycle->fresh()->entry_committal_id);
                $this->assertNotNull($phase->fresh()->completed_at);
                if ($before !== null) {
                    $this->assertSame($before, $this->causalCounts());
                }
                if ($phase->kind === PhaseKind::Order && $action !== 'participant.rest') {
                    $this->assertNotNull($cycle->fresh()->completed_at);
                    $selection = $action === 'proposition.traverse' ? $input['proposition_id'] : $after['current_proposition_id'];
                    $this->assertSame($selection, $after['current_proposition_id']);
                    if (($after['cycle']['id'] ?? $cycle->id) !== $cycle->id) {
                        $this->assertSame($selection, Cycle::findOrFail($after['cycle']['id'])->entry_committal_id);
                    }
                }
                if ($action === 'participant.rest' && ($after['cycle']['id'] ?? null) !== null
                    && $after['cycle']['id'] !== $cycle->id) {
                    $summary['rest_rollovers']++;
                    if ($after['current_proposition_id'] !== $state['current_proposition_id']) {
                        $summary['recovery_relocations']++;
                    }
                }
                $summary['actions'][$action] = ($summary['actions'][$action] ?? 0) + 1;
                $summary['participants'][$actor] = ($summary['participants'][$actor] ?? 0) + 1;
                if ($isAdditional) {
                    $summary['additional_selected'][$action] = ($summary['additional_selected'][$action] ?? 0) + 1;
                }
                $window = (int) floor($step / 100);
                $summary['windows'][$window] ??= ['contributions' => 0, 'queries' => 0, 'sql_ms' => 0, 'http_seconds' => 0, 'max_queries' => 0];
                $bucket = &$summary['windows'][$window];
                $bucket['contributions']++;
                $bucket['queries'] += $this->queries;
                $bucket['sql_ms'] += $this->sqlMs;
                $bucket['http_seconds'] += $httpSeconds;
                $bucket['max_queries'] = max($bucket['max_queries'], $this->queries);
                unset($bucket);
                fwrite($stream, json_encode(['step' => $step, 'participant' => $actor, 'before' => $state,
                    'input' => $input, 'additional_choices' => $additional, 'additional_selected' => $isAdditional,
                    'after' => $after, 'queries' => $this->queries, 'sql_ms' => $this->sqlMs, 'http_seconds' => $httpSeconds], JSON_THROW_ON_ERROR).PHP_EOL);
                if (($step + 1) % 100 === 0) {
                    $summary['windows'][$window]['top_sql'] = collect($this->queryShapes)->sortByDesc('sql_ms')->take(5)->values()->all();
                    $this->queryShapes = [];
                    $summary['checkpoint_step'] = $step + 1;
                    $summary['elapsed_seconds'] = microtime(true) - $started;
                    $summary['checkpoint_counts'] = [];
                    foreach (['tragedies', 'commit_region_exhaustions', 'propositions', 'cycles', 'receipt_consumptions'] as $table) {
                        $summary['checkpoint_counts'][$table] = DB::table($table)->count();
                    }
                    file_put_contents($directory.'/summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);
                    fwrite(STDERR, 'NATURAL_PROGRESS '.json_encode(['step' => $step + 1, 'actions' => $summary['actions'], 'window' => $summary['windows'][$window]]).PHP_EOL);
                }
            }
            $this->assertNotEmpty($summary['actions'], 'The probe must exercise real contributions.');
            $summary['completed'] = true;
        } finally {
            $this->measuring = false;
            $summary['elapsed_seconds'] = microtime(true) - $started;
            foreach (['tragedies', 'commit_region_exhaustions', 'propositions', 'cycles', 'receipt_consumptions'] as $table) {
                $summary['final_counts'][$table] = DB::table($table)->count();
            }
            file_put_contents($directory.'/summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);
            fwrite(STDERR, 'NATURAL_SUMMARY '.json_encode($summary).PHP_EOL);
            fclose($stream);
        }
    }

    /** Diagnostic classification only; selection always comes from the public schema. */
    private function additionalChoices(Phase $phase, array $state): array
    {
        $actions = $this->actionDefinitions($state);
        if ($phase->kind === PhaseKind::Bias) {
            return ['proposition.reclassify' => array_values(array_diff(
                $actions['proposition.reclassify']['properties']['proposition_id']['enum'] ?? [], [$phase->gate_proposition_id]))];
        }
        if (! in_array($phase->kind, [PhaseKind::Mediate, PhaseKind::Order], true)) {
            return [];
        }
        $context = new ParticipationContext($phase);
        if ($phase->kind === PhaseKind::Order) {
            return ['proposition.traverse' => array_values(array_diff(
                $actions['proposition.traverse']['properties']['proposition_id']['enum'] ?? [],
                app(PhaseGenerationService::class)->committedWrites($phase, $context)->pluck('id')->all()))];
        }
        if ($phase->kind !== PhaseKind::Mediate) {
            return [];
        }
        $anchor = app(MediationReadService::class)->anchorFor($phase);
        if ($anchor === null) {
            return [];
        }
        $regions = app(CommitRegionService::class);
        $measurements = app(PropositionMeasurementService::class);
        $direction = $measurements->correspondenceDirectionFor($anchor, $context);
        $reads = collect([$anchor])->concat($regions->forPhase($phase, $anchor, $context)
            ->filter(fn ($node) => $measurements->correspondenceDirectionFor($node, $context) === $direction));
        $pool = $regions->atPhasePosition($phase, $phase->gate, PhaseKind::Disperse, $context, includeSuperseded: true);
        $local = Proposition::query()->active()->whereKey($pool->pluck('id'))
            ->whereHas('parents', fn ($query) => $query->whereKey($reads->pluck('id')))->pluck('id')->all();

        return ['proposition.reconcile' => array_values(array_diff(
            $actions['proposition.reconcile']['properties']['other_proposition_id']['enum'] ?? [], $local))];
    }

    private function causalCounts(): array
    {
        return array_map(fn ($table) => DB::table($table)->count(),
            ['propositions', 'supersessions', 'receipt_consumptions', 'commitment_snapshots']);
    }

    protected function traceMessage(array $state = []): string
    {
        return json_encode(['recent' => array_slice($this->trace, -10), 'state' => $state], JSON_THROW_ON_ERROR);
    }
}
