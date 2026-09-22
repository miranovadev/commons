<?php

namespace Tests\Unit;

use App\Services\PotentialRegionCalculator;
use PHPUnit\Framework\TestCase;

final class PotentialRegionCalculatorTest extends TestCase
{
    public function test_exhausting_best_sibling_exposes_next_maximum_without_subtraction(): void
    {
        $calculator = new PotentialRegionCalculator;
        $local = $this->region();
        $this->assertSame(.7, max(array_column($calculator->candidates($local), 'raw_potential_charge')));
        $entries = $calculator->candidates($local, 'a-leaf');
        $this->assertSame(.4, max(array_column($entries, 'raw_potential_charge')));
        $local['nodes']['a-leaf']['active'] = false;
        $this->assertSame('b', $calculator->frontier($local, [])[0]['proposition_id']);
        $this->assertSame(2, $calculator->distances($local, 'a-leaf')['opening']);
    }

    public function test_dominated_sibling_changes_do_not_change_provenance_summary(): void
    {
        $calculator = new PotentialRegionCalculator;
        $local = $this->region();
        $before = $calculator->frontier($local, []);
        $local['entries']['b']['tension'] = .55;
        $this->assertSame($before, $calculator->frontier($local, []));
        $local['entries']['a']['tension'] = .3;
        $this->assertSame('b', $calculator->frontier($local, [])[0]['proposition_id']);
    }

    public function test_nearer_weaker_candidate_is_retained_because_attenuation_can_change_the_winner(): void
    {
        $calculator = new PotentialRegionCalculator;
        $local = $this->region();
        $local['edges'] = [['opening', 'c'], ['c', 'b'], ['b', 'a'], ['a', 'a-leaf'], ['b', 'b-leaf'], ['c', 'c-leaf']];
        $frontier = $calculator->frontier($local, []);
        $this->assertCount(3, $frontier);
        $near = $calculator->rank(array_map(fn ($entry) => $calculator->attenuate($entry, $entry['distance']), $frontier));
        $far = $calculator->rank(array_map(fn ($entry) => $calculator->attenuate($entry, 100 + $entry['distance']), $frontier));
        $this->assertSame('c', $near[0]['proposition_id']);
        $this->assertSame('a', $far[0]['proposition_id']);
    }

    public function test_viable_descendant_shadows_stronger_parent_and_exhaustion_reveals_parent(): void
    {
        $calculator = new PotentialRegionCalculator;
        $local = $this->region();
        $child = ['opener' => 'b', 'frontier' => [[
            'proposition_id' => 'deep', 'context_horizon_id' => 'child', 'raw_potential_charge' => .0001,
            'raw_charge' => .9, 'distance' => 2, 'complete' => true,
        ]]];
        $frontier = $calculator->frontier($local, [$child]);
        $this->assertSame('deep', $frontier[0]['proposition_id']);
        $this->assertSame(3, $frontier[0]['distance']);
        $child['frontier'] = [];
        $this->assertSame('a', $calculator->frontier($local, [$child])[0]['proposition_id']);
    }

    public function test_exclusion_preserves_an_alternative_path_through_a_diamond(): void
    {
        $calculator = new PotentialRegionCalculator;
        $local = $this->region();
        $local['nodes']['mid'] = $local['nodes']['a-leaf'];
        $local['nodes']['mid']['tension'] = 1;
        $local['edges'][] = ['a', 'mid'];
        $local['edges'][] = ['mid', 'a-leaf'];
        $entries = $calculator->candidates($local, 'mid');
        $this->assertSame(.7, $entries[0]['raw_potential_charge']);
        $this->assertSame(1, $calculator->distances($local, 'a')['a-leaf']);
    }

    public function test_branch_aggregation_cannot_borrow_another_origin_branch(): void
    {
        $calculator = new PotentialRegionCalculator;
        $local = $this->region();
        $local['nodes']['a-leaf']['roots'] = ['other'];
        $entries = $calculator->candidates($local, branch: 'branch');
        $this->assertSame(0.0, $entries[0]['raw_potential_charge']);
        $this->assertSame(.4, max(array_column($entries, 'raw_potential_charge')));
    }

    private function region(): array
    {
        $local = ['id' => 'region', 'parent' => 'parent', 'opener' => 'opening', 'nodes' => [], 'entries' => [], 'edges' => []];
        foreach (['a' => .7, 'b' => .4, 'c' => .2] as $id => $tension) {
            $local['entries'][$id] = ['tension' => $tension];
            $local['nodes'][$id] = ['seed_id' => $id, 'tier' => 'committal', 'active' => true, 'complete' => true, 'tension' => $tension, 'origin' => false, 'roots' => ['branch']];
            $local['nodes'][$id.'-leaf'] = ['seed_id' => $id, 'tier' => 'expressive', 'active' => true, 'complete' => true, 'tension' => 0, 'origin' => false, 'roots' => ['branch']];
            $local['edges'][] = ['opening', $id];
            $local['edges'][] = [$id, $id.'-leaf'];
        }

        return $local;
    }
}

