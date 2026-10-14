<?php

namespace Tests\Unit;

use App\Services\TerminalContinuationService;
use PHPUnit\Framework\TestCase;

/** Pure continuation-set coverage: no claim of authored Commit or measurement history. */
class TerminalContinuationTest extends TestCase
{
    public function test_live_committed_exit_survives_a_terminal_source(): void
    {
        $graph = $this->graph(['source']);
        $this->assertSame(['child'], (new TerminalContinuationService)->remaining('parent', $graph)->all());
    }

    public function test_exhausted_child_does_not_close_a_live_sibling(): void
    {
        $graph = $this->graph(['source', 'child']);
        $graph['nodes']->put('sibling', (object) ['id' => 'sibling', 'tier' => 'expressive', 'entry_committal_id' => 'parent']);
        $this->assertSame(['sibling'], (new TerminalContinuationService)->remaining('parent', $graph)->all());
    }

    public function test_all_terminal_routes_leave_no_continuation(): void
    {
        $this->assertSame([], (new TerminalContinuationService)->remaining('parent', $this->graph(['source', 'child']))->all());
    }

    public function test_other_region_members_are_not_continuations(): void
    {
        $graph = $this->graph(['source', 'child']);
        $graph['nodes']->put('foreign', (object) ['id' => 'foreign', 'tier' => 'expressive', 'entry_committal_id' => 'other']);
        $this->assertSame([], (new TerminalContinuationService)->remaining('parent', $graph)->all());
    }

    private function graph(array $closed): array
    {
        return ['nodes' => collect([
            'source' => (object) ['id' => 'source', 'tier' => 'expressive', 'entry_committal_id' => 'parent'],
            'child' => (object) ['id' => 'child', 'tier' => 'committal', 'entry_committal_id' => 'parent'],
        ]), 'edges' => collect([(object) ['source_proposition_id' => 'source', 'target_proposition_id' => 'child', 'type' => 'commit']]),
            'closed' => collect($closed)->flip()->map(fn () => true)];
    }
}
