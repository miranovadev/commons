<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Proposition;
use App\Models\User;
use App\Services\GraphProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\ExhaustionFixture;
use Tests\TestCase;

class GraphHistoricalMarkersTest extends TestCase
{
    use ExhaustionFixture, RefreshDatabase;

    public function test_only_the_recorded_terminal_is_tragedy_independent_of_current_charge(): void
    {
        $this->initializeExhaustionFixture();
        $first = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->boundaryTragedyFixture($first);
        $later = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin);
        $this->boundaryTragedyFixture($later);
        $this->measure($first, .2);
        $nodes = Proposition::whereKey([$first->id, $later->id])->with(['measurement', 'phase.cycle'])->get()->keyBy('id');
        app(GraphProjectionService::class)->load($this->substrate, $nodes, historical: true);
        $this->assertTrue($nodes[$first->id]->tragedy);
        $this->assertFalse($nodes[$later->id]->tragedy);
        $this->assertEquals(0, $nodes[$later->id]->field['charge']);
        $this->assertFalse($nodes[$first->id]->renewal);
        $this->assertFalse($nodes[$later->id]->renewal);
        $this->assertArrayNotHasKey('provenance', $nodes[$first->id]->getAttributes());
        $this->assertDatabaseCount('tragedies', 1);
        $this->assertDatabaseCount('commit_region_exhaustions', 1);
        User::factory()->create();
        $history = collect($this->getJson("/api/substrates/{$this->substrate->slug}/propositions?view=history")
            ->assertOk()->json('data'))->keyBy('id');
        $this->assertTrue($history[$first->id]['tragedy']);
        $this->assertFalse($history[$later->id]['tragedy']);
        $this->assertFalse($history[$later->id]['renewal']);
        $this->assertArrayNotHasKey('provenance', $history[$first->id]);
    }

    public function test_graph_projection_queries_do_not_grow_per_visible_node(): void
    {
        $this->initializeExhaustionFixture();
        $ids = [];
        for ($i = 0; $i < 12; $i++) {
            $ids[] = $this->node($this->phase($this->origin, PhaseKind::Distinguish), $this->origin)->id;
        }
        $measure = function (array $ids): int {
            $nodes = Proposition::whereKey($ids)->with(['measurement', 'phase.cycle'])->get();
            DB::enableQueryLog();
            DB::flushQueryLog();
            app(GraphProjectionService::class)->load($this->substrate, $nodes, historical: true);
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };
        $this->assertSame($measure([$ids[0]]), $measure($ids));
    }
}
