<?php

namespace Tests\Diagnostics;

use App\Domain\Evidence\VerifiedHyperlink;
use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Models\Connection;
use App\Models\Proposition;
use App\Services\InputPlanService;
use App\Services\InstrumentExecutionService;
use App\Services\MediationReadService;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantPromptFactory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ParticipantContextFixture;
use Tests\TestCase;

/** Offline, identical-state C1/C2 presentation comparison. No provider calls. */
class ParticipantContextC2ComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_identical_states_against_current_main_c1(): void
    {
        $this->freezeTime();
        $base = getenv('COMMONS_CONTEXT_BASELINE_DIR');
        $this->assertNotEmpty($base, 'Set COMMONS_CONTEXT_BASELINE_DIR to main at 62a4f065.');
        foreach (['ParticipantContextSerializer', 'ParticipantPromptLayout', 'ParticipantPromptCore', 'ParticipantPromptFactory'] as $class) {
            $source = file_get_contents($base.'/app/Services/'.$class.'.php');
            eval(str_replace('namespace App\\Services;', 'namespace Tests\\Diagnostics\\C1; use App\\Services\\ParticipantCognitiveProjection; use App\\Services\\ParticipantRegisterQuestions;', substr($source, 5)));
        }
        $oldFactory = new C1\ParticipantPromptFactory(new C1\ParticipantContextSerializer);
        $connection = (new Connection)->forceFill(['configuration' => ['model' => 'test-model']]);
        $f = new ParticipantContextFixture(25, 6, false);
        $anchor = app(MediationReadService::class)->anchorFor($f->phase);
        $executions = app(InstrumentExecutionService::class);
        foreach ([$anchor, $f->gate, Proposition::findOrFail($f->writes[0])] as $index => $node) {
            $executions->complete($executions->start($f->phase, $node, 'hyperlink', 'https://example.test/c2-'.$index), $node,
                verification: new VerifiedHyperlink('example.test/c2-'.$index, hash('sha256', 'evidence-'.$index),
                    CarbonImmutable::now(), contentExcerpt: 'C2-DIAGNOSTIC-BODY-'.$index));
        }
        $mediateGate = $f->gate;
        $distinction = $f->node($f->seed, 'activity', position: PhaseKind::Distinguish);
        $counterpart = $f->node($f->seed, 'activity', position: PhaseKind::Disperse);
        $reconciled = $f->node($mediateGate, 'frontier', 'reconcile', additionalParent: $counterpart);
        $classified = $f->node($reconciled, 'canon', 'reclassify', position: PhaseKind::Bias);
        $cases = [[PhaseKind::Distinguish, $f->seed], [PhaseKind::Persist, $distinction],
            [PhaseKind::Mediate, $mediateGate], [PhaseKind::Bias, $reconciled], [PhaseKind::Order, $classified]];
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $rows = [];
        foreach ($cases as [$kind, $gate]) {
            $f->phase->forceFill(['kind' => $kind, 'gate_proposition_id' => $gate->id,
                'write_register' => ContextDomain::Emergence->registerFor($kind)])->save();
            $f->phase->unsetRelation('gate');
            $plan = app(InputPlanService::class)->forPhase($f->viewer, $f->phase);
            $state = ['status' => 'ready', 'participant' => $f->viewer->only(['id', 'callsign', 'designation']),
                'plan' => app(ParticipantContextSerializer::class)->plan($plan)];
            $before = $this->snapshot();
            $profiles = [];
            foreach (['c1' => $oldFactory, 'c2' => app(ParticipantPromptFactory::class)] as $name => $factory) {
                $queries = [];
                $start = hrtime(true);
                $prompts[$name] = $factory->make($connection, $state);
                $profiles[$name] = ['queries' => count($queries), 'milliseconds' => round((hrtime(true) - $start) / 1e6, 3)];
                foreach ($queries as $sql) {
                    $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace)\b/i', $sql);
                }
            }
            $this->assertSame($before, $this->snapshot());
            $this->assertSame($prompts['c1']['schema'], $prompts['c2']['schema']);
            $old = json_decode($prompts['c1']['user'], true);
            $new = json_decode($prompts['c2']['user'], true);
            $this->assertSame($old['actions'], $new['actions']);
            foreach (['read', 'gate', 'write'] as $role) {
                $this->assertSame(array_column($old[$role]['propositions'], 'id'), array_column($new[$role]['propositions'], 'id'));
                $this->assertSame($old[$role]['question'] ?? null, $new[$role]['question'] ?? null);
            }
            $rows[] = ['phase' => $kind->name, 'before' => $this->sizes($prompts['c1']), 'after' => $this->sizes($prompts['c2']),
                'projection' => $profiles, 'actions_equal' => true, 'register_ids_and_questions_equal' => true,
                'persistent_state_unchanged' => true];
        }
        $report = ['base' => '62a4f065e3333176c26403cc12881c3fe2507998',
            'method' => 'Identical persisted fixtures and operational plans, including identical action schemas. Prompt bytes sum system/evidence/user/support; response schema reported separately. Tokens are bytes/4 estimates, not provider tokenization. Timing is illustrative local wall time.',
            'live_inference' => false, 'cases' => $rows];
        if ($path = getenv('COMMONS_CONTEXT_COMPARISON_OUTPUT')) {
            file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        }
        fwrite(STDERR, "\n".json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    private function sizes(array $prompt): array
    {
        $packet = json_decode($prompt['user'], true);
        $metadata = [];
        $bodies = [];
        foreach (['read', 'gate', 'write'] as $role) {
            foreach ($packet[$role]['propositions'] as $node) {
                foreach ($node['receipts'] ?? [] as $receipt) {
                    if (is_array($receipt)) {
                        $metadata[] = $receipt;
                    }
                }
            }
        }
        foreach (array_merge($packet['receipts'] ?? [], json_decode($prompt['support'] ?: '{}', true)['receipts'] ?? [],
            json_decode($prompt['evidence'] ?: '{}', true)['loaded_receipts'] ?? []) as $receipt) {
            foreach (['loaded_inline_content', 'evidence', 'tragedy_memory', 'proposition_collection'] as $key) {
                if (isset($receipt[$key])) {
                    $bodies[] = $receipt[$key];
                    unset($receipt[$key]);
                }
            }
            $metadata[] = $receipt;
        }
        $bytes = array_sum(array_map(fn ($key) => strlen($prompt[$key]), ['system', 'evidence', 'user', 'support']));

        return ['prompt_bytes' => $bytes, 'estimated_tokens_bytes_div_4' => (int) ceil($bytes / 4),
            'receipt_metadata_bytes' => strlen(json_encode($metadata, JSON_UNESCAPED_SLASHES)),
            'evidence_bytes' => array_sum(array_map(fn ($body) => strlen(json_encode($body, JSON_UNESCAPED_SLASHES)), $bodies)),
            'receipt_metadata_entries' => count($metadata), 'duplicate_receipt_entries' => count($metadata) - count(array_unique(array_column($metadata, 'id'))),
            'visible_evidence_bodies' => count($bodies), 'schema_bytes' => strlen(json_encode($prompt['schema'], JSON_UNESCAPED_SLASHES)),
            'messages' => count(array_filter(array_map(fn ($key) => $prompt[$key], ['system', 'evidence', 'user', 'support'])))];
    }

    private function snapshot(): array
    {
        return collect(['receipts', 'participant_receipt_contexts', 'receipt_context_events', 'supersessions',
            'proposition_measurements', 'substrate_ledger_entries', 'phases', 'participants'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy($table === 'proposition_measurements' ? 'proposition_id' : 'id')->get()->toJson()])->all();
    }
}
