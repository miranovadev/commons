<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Support\ParticipationHttpTestCase;

class ParticipationHttpReadGuardTest extends ParticipationHttpTestCase
{
    public function test_diagnostics_accept_read_queries_but_reject_data_modifying_ctes(): void
    {
        $this->assertCount(2, DB::select('(select 1 as n) union (select 2 as n)'));
        $this->assertCount(1, DB::select('with recursive observation(n) as (select 1) select n from observation'));
        $before = DB::table('participants')->count();
        foreach (['delete from participants',
            'with recursive removed as (delete from participants returning id) select id from removed'] as $sql) {
            try {
                DB::statement($sql);
                $this->fail('Fixture writes outside an HTTP request must remain forbidden.');
            } catch (LogicException $exception) {
                $this->assertSame('HTTP harness forbids fixture writes outside an application request.', $exception->getMessage());
            }
        }
        $this->assertSame($before, DB::table('participants')->count());
    }
}
