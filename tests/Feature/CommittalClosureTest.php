<?php

namespace Tests\Feature;

use App\Exceptions\ClosureHistoryUnavailable;
use App\Services\ClosureGateService;
use App\Services\Observables\ProspectiveClosureService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\GovernedExpressionFixture;
use Tests\TestCase;

/** Real bootstrap and persistence contracts; ordinary Commit is covered by CommitSourceHistoryLifecycleTest. */
class CommittalClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_constitutional_origin_is_frozen_and_distinct_from_developmental_state(): void
    {
        $fixture = new GovernedExpressionFixture;
        $states = app(ProspectiveClosureService::class);
        $origin = $states->stored($fixture->origin);
        foreach (['depth', 'cadence', 'historical_extent', 'historical_rhythm', 'closure'] as $component) {
            $this->assertSame(-1.0, $origin[$component]);
        }
        $this->assertSame('constitutional_origin', $origin['trigger']['kind']);
        $expression = $states->stored($fixture->expression);
        $this->assertSame(['kind' => 'expressive_birth', 'id' => $fixture->expression->id], $expression['trigger']);
        $this->assertSame($fixture->origin->id, $expression['history_proposition_id']);
        $fixture->advanceToConstrain();
        $this->assertSame($origin, $states->stored($fixture->origin));
    }

    public function test_preview_reads_authorised_state_without_writes_or_a_new_measurement(): void
    {
        $fixture = new GovernedExpressionFixture;
        $expected = app(ProspectiveClosureService::class)->stored($fixture->expression);
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $actual = app(ClosureGateService::class)->preview($fixture->expression, $fixture->phase);
            $writes = collect(DB::getQueryLog())->filter(fn ($q) => preg_match('/^\s*(insert|update|delete)/i', $q['query']));
        } finally {
            DB::disableQueryLog();
        }
        $this->assertSame($expected, $actual);
        $this->assertCount(0, $writes);
    }

    public function test_missing_authorised_expressive_state_fails_closed_without_repair(): void
    {
        $fixture = new GovernedExpressionFixture;
        DB::table('prospective_closure_states')->where('proposition_id', $fixture->expression->id)->delete();
        $preview = app(ClosureGateService::class)->preview($fixture->expression, $fixture->phase);
        $this->assertFalse($preview['admissible']);
        $this->assertNull($preview['closure']);
        $this->assertSame('prospective_closure_unavailable', $preview['unavailable_reason']);
        $this->assertDatabaseMissing('prospective_closure_states', ['proposition_id' => $fixture->expression->id]);
        $this->expectException(ClosureHistoryUnavailable::class);
        app(ProspectiveClosureService::class)->stored($fixture->expression);
    }

    public function test_constitutional_state_cannot_be_updated_in_storage(): void
    {
        $fixture = new GovernedExpressionFixture;
        $this->expectException(QueryException::class);
        DB::table('prospective_closure_states')->where('proposition_id', $fixture->origin->id)->update(['frozen' => false]);
    }
}
