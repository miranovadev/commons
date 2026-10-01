<?php

namespace Tests\Feature\Api;

use App\Enums\PhaseKind;
use App\Models\Attestation;
use App\Models\CommitmentSnapshot;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Models\Proposition;
use App\Models\PropositionMeasurement;
use App\Models\Substrate;
use App\Models\Supersession;
use App\Models\User;
use App\Services\CommitRegionService;
use App\Services\LedgerService;
use App\Services\Observables\LineageMeasurementService;
use App\Services\PotentialChargeService;
use App\Services\PotentialProjectionService;
use App\Services\PropositionMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
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
        app(PotentialProjectionService::class)->rebuild($substrate->id, false);
        $before = $this->snapshot();
        foreach (["/api/substrates/{$substrate->slug}/propositions", "/api/substrates/{$substrate->slug}/propositions/{$node->id}"] as $index => $url) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $response = $this->getJson($url)->assertOk();
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $prefix = $index === 0 ? 'data.0' : 'data';
            $response->assertJsonPath($prefix.'.observables.status', $status === 'invalidated' ? 'stale' : $status)
                ->assertJsonPath($prefix.'.observables.correspondence', $status === 'missing' ? null : -0.75)
                ->assertJsonPath($prefix.'.field.status', $status === 'invalidated' ? 'stale' : $status)
                ->assertJsonPath($prefix.'.field.tension', $status === 'missing' ? null : -0.5)
                ->assertJsonPath($prefix.'.field.potential_charge', null);
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

    #[Group('query')]
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
        // Two fixed historical-marker batches, independent of visible node count.
        $this->assertLessThanOrEqual(22, count($large));
        $this->assertDatabaseCount('proposition_measurements', 0);
    }

    public function test_overlapping_commit_histories_are_batched_and_counts_stop_at_expressive_nodes(): void
    {
        [$substrate, $origin] = $this->fixture();
        $attestation = new Attestation;
        $attestation->forceFill(['phase_id' => $origin->phase_id, 'proposition_id' => $origin->id, 'direction' => 'support'])->save();
        $parent = $origin;
        for ($i = 0; $i < 30; $i++) {
            $child = $this->node($origin->phase, 'committal', false);
            $edge = new Supersession;
            $edge->forceFill(['phase_id' => $origin->phase_id, 'source_proposition_id' => $parent->id,
                'target_proposition_id' => $child->id, 'type' => 'commit'])->save();
            $parent = $child;
        }
        $expressive = $this->node($origin->phase, 'expressive', false);
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $origin->phase_id, 'source_proposition_id' => $parent->id,
            'target_proposition_id' => $expressive->id, 'type' => 'variant'])->save();
        $queries = $this->graphQueries($substrate, 32);
        // Five fixed graph projection queries; history depth never adds per-card work.
        $this->assertLessThanOrEqual(25, count($queries));
        $data = collect($this->getJson("/api/substrates/{$substrate->slug}/propositions")->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(1, $data[$parent->id]['attestation_counts']['support']);
        $this->assertSame(0, $data[$expressive->id]['attestation_counts']['support']);
    }

    public function test_field_projection_uses_snapshots_canonical_potential_and_direct_phase_touches(): void
    {
        [$substrate, $origin] = $this->fixture();
        $snapshot = new CommitmentSnapshot;
        $snapshot->forceFill([
            'proposition_id' => $origin->id, 'substrate_id' => $substrate->id,
            'ledger_entry_id' => $origin->ledgerEntry->id,
            'correspondence' => .8, 'chirality' => .8, 'closure' => .8,
            'prominence' => .9, 'turbulence' => .1, 'tension' => .8,
            'direction_before' => 'emergence', 'direction_after' => 'emergence',
            'calculator_version' => 'fixture', 'evaluated_at' => now(),
        ])->save();
        $child = $this->node($this->governedPhaseFixture($origin->phase, $origin), 'expressive');
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $origin->phase_id, 'source_proposition_id' => $origin->id,
            'target_proposition_id' => $child->id, 'type' => 'variant'])->save();
        $origin->phase->forceFill(['gate_proposition_id' => $child->id])->save();
        app(PropositionMeasurementService::class)->project($child, -.2, -.2, -.2);
        app(PotentialProjectionService::class)->rebuild($substrate->id, false);
        $before = $this->snapshot();
        $url = "/api/substrates/{$substrate->slug}/propositions";
        $data = collect($this->getJson($url)->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(.8, $data[$origin->id]['field']['tension']);
        $this->assertSame('commitment_snapshot', $data[$origin->id]['field']['source']);
        $this->assertSame('current', $data[$origin->id]['field']['status']);
        $this->assertSame('missing', $data[$origin->id]['observables']['status']);
        $this->assertSame(-.05, $data[$child->id]['field']['tension']);
        $this->assertSame(.75, $data[$child->id]['field']['potential_charge']);
        $this->assertSame(.75, $data[$origin->id]['field']['potential_charge']);
        $this->assertSame($origin->id, $data[$child->id]['field']['committal_proposition_id']);
        $this->assertSame(1, $data[$child->id]['participation_count']);
        $this->assertSame(0, $data[$origin->id]['participation_count']);
        $this->assertSame($before, $this->snapshot());
        app(PotentialProjectionService::class)->rebuild($substrate->id, false);
        $canonical = app(PotentialChargeService::class)->selectFor($substrate);
        $this->assertSame($canonical['raw_potential_charge'], $data[$origin->id]['field']['potential_charge']);
        $this->getJson($url.'/'.$child->id)->assertOk()->assertJsonPath('data.field', $data[$child->id]['field']);

        // Absent continuation measurements are unknown, not an invented zero.
        $child->measurement()->delete();
        app(PotentialProjectionService::class)->rebuild($substrate->id, false);
        $this->getJson($url.'/'.$origin->id)->assertOk()->assertJsonPath('data.field.potential_charge', null)
            ->assertJsonPath('data.field.potential_status', 'missing');
    }

    public function test_batched_region_seeds_follow_cycle_governance_despite_cross_region_ancestry(): void
    {
        [$substrate, $origin] = $this->fixture();
        $this->freezeRegionFixture($origin);
        $owned = $this->governedPhaseFixture($origin->phase, $origin);
        $left = $this->node($owned, 'expressive');
        $right = $this->node($owned, 'expressive');
        $joined = $this->node($owned, 'expressive');
        foreach ([[$origin, $left], [$origin, $right], [$left, $joined], [$right, $joined]] as [$from, $to]) {
            $edge = new Supersession;
            $edge->forceFill(['phase_id' => $origin->phase_id, 'source_proposition_id' => $from->id,
                'target_proposition_id' => $to->id, 'type' => 'variant'])->save();
        }
        $regions = app(CommitRegionService::class);
        $batch = fn () => $regions->seedIdsFor(collect([$joined]), Proposition::all()->keyBy('id'),
            DB::table('supersessions')->get()->groupBy('target_proposition_id'))[$joined->id];
        $this->assertSame($regions->seedFor($joined)->id, $batch());
        $other = $this->node($origin->phase, 'committal');
        $edge = new Supersession;
        $edge->forceFill(['phase_id' => $origin->phase_id, 'source_proposition_id' => $other->id,
            'target_proposition_id' => $joined->id, 'type' => 'variant'])->save();
        $this->assertSame($origin->id, $regions->seedFor($joined)?->id);
        $this->assertSame($origin->id, $batch());
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
