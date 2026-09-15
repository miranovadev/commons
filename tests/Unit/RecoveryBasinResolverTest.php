<?php

namespace Tests\Unit;

use App\Models\ContextHorizon;
use App\Services\RecoveryBasinResolver;
use DomainException;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class RecoveryBasinResolverTest extends TestCase
{
    public function test_viable_downstream_domains_scope_out_historical_candidates(): void
    {
        [$root, $settlement, $emergence] = $this->chain();
        $result = $this->resolve($settlement, [$root, $settlement, $emergence], ['settlement', 'emergence']);
        $this->assertSame(['emergence'], $result['candidates']->all());
        $this->assertSame(['emergence'], array_column($result['searched_basins'], 'context_horizon_id'));
        $this->assertSame(0, $result['fallback_depth']);
    }

    public function test_sibling_frontiers_remain_peers_without_using_sequence_or_recency(): void
    {
        [$root, $settlement, $emergence] = $this->chain();
        $sibling = $this->basin('sibling', $settlement, 999);
        $deep = $this->basin('deep', $emergence, 4);
        $result = $this->resolve($settlement, [$root, $settlement, $emergence, $sibling, $deep],
            ['settlement', 'emergence', 'sibling', 'deep']);
        $this->assertEqualsCanonicalizing(['deep', 'sibling'], $result['candidates']->all());
        $this->assertNotContains('emergence', array_column($result['searched_basins'], 'context_horizon_id'));
    }

    public function test_a_viable_current_branch_does_not_compete_with_its_parents_other_children(): void
    {
        [$root, $settlement, $emergence] = $this->chain();
        $sibling = $this->basin('sibling', $settlement, 4);
        $result = $this->resolve($emergence, [$root, $settlement, $emergence, $sibling], ['emergence', 'sibling']);
        $this->assertSame(['emergence'], $result['candidates']->all());
        $this->assertSame(0, $result['fallback_depth']);
    }

    public function test_exhaustion_retreats_to_provenance_and_can_discover_a_viable_sibling(): void
    {
        [$root, $settlement, $emergence] = $this->chain();
        $sibling = $this->basin('sibling', $settlement, 4);
        $result = $this->resolve($emergence, [$root, $settlement, $emergence, $sibling], ['settlement', 'sibling']);
        $this->assertSame(['sibling'], $result['candidates']->all());
        $this->assertSame(1, $result['fallback_depth']);
        $this->assertSame(['emergence', 'sibling'], array_column($result['searched_basins'], 'context_horizon_id'));
    }

    public function test_exhaustion_reaches_historical_domains_and_the_root_without_revisiting_basins(): void
    {
        [$root, $settlement, $emergence] = $this->chain();
        $result = $this->resolve($emergence, [$root, $settlement, $emergence], ['root']);
        $this->assertSame(['root'], $result['candidates']->all());
        $this->assertSame(2, $result['fallback_depth']);
        $this->assertSame(['emergence', 'settlement', 'root'], array_column($result['searched_basins'], 'context_horizon_id'));
        $empty = $this->resolve($emergence, [$root, $settlement, $emergence], []);
        $this->assertTrue($empty['candidates']->isEmpty());
    }

    public function test_an_exhausted_descendant_does_not_permanently_suppress_its_parent(): void
    {
        [$root, $settlement, $emergence] = $this->chain();
        $result = $this->resolve($settlement, [$root, $settlement, $emergence], ['settlement']);
        $this->assertSame(['settlement'], $result['candidates']->all());
        $this->assertSame(0, $result['fallback_depth']);
        $changed = $this->resolve($settlement, [$root, $settlement, $emergence], ['settlement', 'emergence']);
        $this->assertSame(['emergence'], $changed['candidates']->all());
    }

    public function test_missing_provenance_does_not_jump_to_an_unrelated_root(): void
    {
        [$root, , $emergence] = $this->chain();
        $this->expectException(DomainException::class);
        $this->resolve($emergence, [$root, $emergence], ['root']);
    }

    public function test_corrupt_horizon_cycles_terminate_with_an_error(): void
    {
        [$root, $settlement, $emergence] = $this->chain();
        $root->parent_context_horizon_id = $emergence->id;
        $this->expectException(DomainException::class);
        $this->resolve($settlement, [$root, $settlement, $emergence], []);
    }

    private function resolve(ContextHorizon $start, array $basins, array $viable): array
    {
        return (new RecoveryBasinResolver)->resolve($start, collect($basins),
            fn (ContextHorizon $basin): Collection => collect(in_array($basin->id, $viable, true) ? [$basin->id] : []));
    }

    private function chain(): array
    {
        $root = $this->basin('root');
        $settlement = $this->basin('settlement', $root, 2);

        return [$root, $settlement, $this->basin('emergence', $settlement, 3)];
    }

    private function basin(string $id, ?ContextHorizon $parent = null, int $sequence = 1): ContextHorizon
    {
        return (new ContextHorizon)->forceFill(['id' => $id, 'substrate_id' => 'substrate', 'sequence' => $sequence,
            'parent_context_horizon_id' => $parent?->id, 'domain' => $parent?->domain->opposite() ?? 'emergence']);
    }
}
