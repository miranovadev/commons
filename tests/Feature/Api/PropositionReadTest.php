<?php

namespace Tests\Feature\Api;

use App\Enums\PhaseKind;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use App\Models\Substrate;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\PropositionMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PropositionReadTest extends TestCase
{
    use RefreshDatabase;

    public static function projections(): array
    {
        return [['missing'], ['stale'], ['invalidated'], ['current']];
    }

    #[DataProvider('projections')]
    public function test_graph_and_detail_read_persisted_values_without_repair(string $status): void
    {
        [$substrate, $node] = $this->fixture();
        if ($status !== 'missing') {
            app(PropositionMeasurementService::class)->project($node, -0.75, 0.25, 0.5);
            if ($status !== 'current') {
                $node->measurement()->update(['calculator_version' => $status === 'stale' ? 'obsolete' : null]);
            }
        }
        $before = $this->snapshot();
        foreach (["/api/substrates/{$substrate->slug}/propositions", "/api/substrates/{$substrate->slug}/propositions/{$node->id}"] as $index => $url) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $response = $this->getJson($url)->assertOk();
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $prefix = $index === 0 ? 'data.0' : 'data';
            $response->assertJsonPath($prefix.'.observables.status', $status === 'invalidated' ? 'stale' : $status)
                ->assertJsonPath($prefix.'.observables.correspondence', $status === 'missing' ? null : -0.75);
            $this->assertReadOnly($queries);
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_bounded_maintenance_repairs_missing_and_obsolete_but_preserves_current_and_unmeasurable_nodes(): void
    {
        [$substrate, $missing] = $this->fixture();
        $stale = $this->node($missing->phase, 'committal');
        $valid = $this->node($missing->phase, 'committal');
        $unmeasurable = $this->node($missing->phase, 'expressive', false);
        $service = app(PropositionMeasurementService::class);
        $service->project($stale, -0.75, 0.25, 0.5);
        $stale->measurement()->update(['calculator_version' => 'obsolete']);
        $service->project($valid, 0.75, -0.25, 0.5);
        $validBefore = $valid->measurement->getAttributes();

        $this->artisan('commons:measurements-repair', ['substrate' => $substrate->slug, '--limit' => 1])
            ->expectsOutput('Repaired 1 projection(s).')->assertSuccessful();
        $this->assertSame(2, PropositionMeasurement::where('calculator_version', LineageMeasurementService::VERSION)->count());
        $this->artisan('commons:measurements-repair', ['substrate' => $substrate->slug])
            ->expectsOutput('Repaired 1 projection(s).')->assertSuccessful();
        $this->assertSame(LineageMeasurementService::VERSION, $missing->fresh()->measurement->calculator_version);
        $this->assertSame(LineageMeasurementService::VERSION, $stale->fresh()->measurement->calculator_version);
        $this->assertSame($validBefore, $valid->fresh()->measurement->getAttributes());
        $this->assertNull($unmeasurable->fresh()->measurement);
        $this->artisan('commons:measurements-repair', ['substrate' => $substrate->slug])
            ->expectsOutput('Repaired 0 projection(s).')->assertSuccessful();
        $this->artisan('commons:measurements-repair', ['substrate' => $substrate->slug, '--limit' => 501])->assertExitCode(2);
    }

    public function test_500_node_graph_has_a_bounded_read_only_query_budget(): void
    {
        [$substrate, $node] = $this->fixture();
        $small = $this->graphQueries($substrate, 1);
        // Origins exercise Commit-history counts too, rather than hiding an N+1
        // behind an all-expressive fixture. No projection exists for any node.
        for ($i = 1; $i < 500; $i++) {
            $this->node($node->phase, 'committal');
        }
        $large = $this->graphQueries($substrate, 500);
        $this->assertLessThanOrEqual(count($small) + 2, count($large));
        $this->assertLessThanOrEqual(20, count($large));
        $this->assertDatabaseCount('proposition_measurements', 0);
    }

    public function test_overlapping_commit_histories_are_batched_and_counts_stop_at_expressive_nodes(): void
    {
        [$substrate, $origin] = $this->fixture();
        $attestation = new \App\Models\Attestation;
        $attestation->forceFill(['phase_id' => $origin->phase_id, 'proposition_id' => $origin->id, 'direction' => 'support'])->save();
        $parent = $origin;
        for ($i = 0; $i < 30; $i++) {
            $child = $this->node($origin->phase, 'committal', false);
            $edge = new \App\Models\Supersession;
            $edge->forceFill(['phase_id' => $origin->phase_id, 'source_proposition_id' => $parent->id,
                'target_proposition_id' => $child->id, 'type' => 'commit'])->save();
            $parent = $child;
        }
        $expressive = $this->node($origin->phase, 'expressive', false);
        $edge = new \App\Models\Supersession;
        $edge->forceFill(['phase_id' => $origin->phase_id, 'source_proposition_id' => $parent->id,
            'target_proposition_id' => $expressive->id, 'type' => 'variant'])->save();
        $queries = $this->graphQueries($substrate, 32);
        $this->assertLessThanOrEqual(20, count($queries));
        $data = collect($this->getJson("/api/substrates/{$substrate->slug}/propositions")->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(1, $data[$parent->id]['attestation_counts']['support']);
        $this->assertSame(0, $data[$expressive->id]['attestation_counts']['support']);
    }

    private function graphQueries(Substrate $substrate, int $count): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson("/api/substrates/{$substrate->slug}/propositions")->assertOk()->assertJsonCount($count, 'data');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        fwrite(STDERR, sprintf("\nGRAPH %d nodes: %d queries, %.2f SQL ms\n", $count, count($queries), array_sum(array_column($queries, 'time'))));
        $this->assertReadOnly($queries);

        return $queries;
    }

    private function assertReadOnly(array $queries): void
    {
        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression('/^(select|with recursive)\b/i', $query['query']);
            $this->assertDoesNotMatchRegularExpression('/\b(insert|update|delete|upsert|replace|alter|create|drop)\b/i', $query['query']);
            $this->assertDoesNotMatchRegularExpression('/\bfor\s+(update|share)\b/i', $query['query']);
        }
    }

    private function snapshot(): array
    {
        return [DB::table('propositions')->orderBy('id')->get()->toJson(),
            DB::table('proposition_measurements')->orderBy('proposition_id')->get()->toJson()];
    }

    private function fixture(): array
    {
        User::factory()->create();
        $substrate = new Substrate;
        $substrate->forceFill(['slug' => 'read-purity', 'title' => 'Read purity', 'visibility' => 'public', 'configuration' => []])->save();
        $participant = new Participant;
        $participant->forceFill(['substrate_id' => $substrate->id, 'callsign' => 'observer'])->save();
        $cycle = new Cycle;
        $cycle->forceFill(['participant_id' => $participant->id, 'sequence' => 1])->save();
        $phase = new Phase;
        $phase->forceFill(['cycle_id' => $cycle->id, 'kind' => PhaseKind::Distinguish, 'write_register' => 'activity', 'started_at' => now()])->save();

        return [$substrate, $this->node($phase, 'committal')];
    }

    private function node(Phase $phase, string $tier, bool $record = true): Proposition
    {
        $node = new Proposition;
        $node->forceFill(['phase_id' => $phase->id, 'register' => $tier === 'committal' ? 'canon' : 'activity', 'tier' => $tier, 'statement' => 'Observe without mutation'])->save();
        if ($record) {
            app(LedgerService::class)->record($node);
        }

        return $node;
    }
}
