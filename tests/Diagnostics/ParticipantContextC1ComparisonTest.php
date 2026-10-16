<?php

namespace Tests\Diagnostics;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Models\Connection;
use App\Services\InputPlanService;
use App\Services\InstrumentExecutionService;
use App\Services\MediationReadService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantPromptFactory;
use App\Services\ReceiptContextService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

/** Explicit offline diagnostic; not part of routine Core discovery. No inference calls. */
class ParticipantContextC1ComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_identical_states_against_the_pre_c1_checkout(): void
    {
        $this->freezeTime();
        $base = getenv('COMMONS_CONTEXT_BASELINE_DIR');
        $this->assertNotEmpty($base, 'Set COMMONS_CONTEXT_BASELINE_DIR to a checkout of 23258245.');
        foreach (['ParticipantContextSerializer', 'ParticipantPromptLayout', 'ParticipantPromptCore', 'ParticipantPhaseInstructions', 'ParticipantPromptFactory'] as $class) {
            $source = file_get_contents($base.'/app/Services/'.$class.'.php');
            eval(str_replace('namespace App\\Services;', 'namespace Tests\\Diagnostics\\Baseline;', substr($source, 5)));
        }
        $oldFactory = new Baseline\ParticipantPromptFactory(new Baseline\ParticipantContextSerializer);
        $newConfig = config('schemas');
        $oldConfig = require $base.'/config/schemas.php';
        $connection = (new Connection)->forceFill(['configuration' => ['model' => 'test-model']]);
        $f = new ParticipantContextFixture(25, 6);
        $anchor = app(MediationReadService::class)->anchorFor($f->phase);
        $executions = app(InstrumentExecutionService::class);
        $receipt = $executions->complete($executions->start($f->phase, $anchor, 'hyperlink', 'https://example.test/c1-evidence'),
            $anchor, verification: new VerifiedHyperlink('example.test/c1-evidence',
                hash('sha256', 'c1-evidence'), CarbonImmutable::now(), contentExcerpt: 'A preserved external observation.'));
        app(ReceiptContextService::class)->read($f->viewer, $f->phase, $receipt->id);
        $mediateGate = $f->gate;
        $distinction = $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
        $counterpart = $f->node($f->seed, 'activity', position: PhaseKind::Disperse);
        $reconciled = $f->node($mediateGate, 'frontier', 'reconcile', additionalParent: $counterpart);
        $classified = $f->node($reconciled, 'canon', 'reclassify', position: PhaseKind::Bias);
        $cases = [
            [PhaseKind::Distinguish, $f->seed], [PhaseKind::Persist, $distinction],
            [PhaseKind::Mediate, $mediateGate], [PhaseKind::Bias, $reconciled], [PhaseKind::Order, $classified],
        ];
        $rows = [];
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        foreach ($cases as [$kind, $gate]) {
            $f->phase->forceFill(['kind' => $kind, 'gate_proposition_id' => $gate->id,
                'write_register' => ContextDomain::Emergence->registerFor($kind)])->save();
            $f->phase->unsetRelation('gate');
            config(['schemas' => $oldConfig]);
            Cache::clear();
            $oldPlan = app(InputPlanService::class)->forPhase($f->viewer, $f->phase);
            $oldState = ['status' => 'ready', 'participant' => $f->viewer->only(['callsign', 'designation']),
                'plan' => (new Baseline\ParticipantContextSerializer)->plan($oldPlan)];
            $old = $oldFactory->make($connection, $oldState);
            config(['schemas' => $newConfig]);
            Cache::clear();
            $newPlan = app(InputPlanService::class)->forPhase($f->viewer, $f->phase);
            $newState = [...$oldState, 'plan' => app(ParticipantContextSerializer::class)->plan($newPlan)];
            $this->assertSame($this->constraints(json_decode(json_encode($oldPlan), true)), $this->constraints(json_decode(json_encode($newPlan), true)));
            $queries = [];
            $started = hrtime(true);
            $new = app(ParticipantPromptFactory::class)->make($connection, $newState);
            $elapsed = (hrtime(true) - $started) / 1e6;
            foreach ($queries as $query) {
                $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace)\b/i', $query);
            }
            $this->assertSame($old['evidence'], $new['evidence']);
            $this->assertSame($old['loaded_receipt_count'], $new['loaded_receipt_count']);
            $this->assertSame($this->constraints($old['schema']), $this->constraints($new['schema']));
            $rows[] = ['phase' => $kind->name, 'before' => $this->sizes($old), 'after' => $this->sizes($new),
                'projection_ms' => round($elapsed, 3), 'projection_queries' => count($queries),
                'actions' => array_keys($new['schema']['$defs']['actions']),
                'schema_constraints_equal' => true, 'evidence_bytes_equal' => true];
        }
        $report = ['base' => '23258245c42ffe4263870e403323526a7f2d9703',
            'method' => 'Identical persisted fixtures, old/new authoritative schema annotations; bytes/4 rough token estimate, not provider tokenization.',
            'live_inference' => false, 'cases' => $rows];
        if ($path = getenv('COMMONS_CONTEXT_COMPARISON_OUTPUT')) {
            file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        }
        fwrite(STDERR, "\n".json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    private function sizes(array $prompt): array
    {
        $sizes = [];
        foreach (['system', 'evidence', 'user', 'support'] as $part) {
            $sizes[$part.'_bytes'] = strlen($prompt[$part] ?? '');
        }
        $sizes['prompt_bytes'] = array_sum($sizes);
        $sizes['rough_tokens'] = (int) ceil($sizes['prompt_bytes'] / 4);

        return $sizes;
    }

    private function constraints(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        unset($value['description'], $value['title']);

        return array_map($this->constraints(...), $value);
    }
}
