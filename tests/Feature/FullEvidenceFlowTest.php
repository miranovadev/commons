<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Fixtures\HorizonCycleFixture;
use Tests\TestCase;

#[Group('integration')]
class FullEvidenceFlowTest extends TestCase
{
    use HorizonCycleFixture, RefreshDatabase;

    public function test_three_participants_share_receipts_and_commit_without_redundant_healthy_lineages(): void
    {
        $this->driveHorizonCycle(3);
        $shared = DB::table('receipt_consumptions as attachment')
            ->join('phases as consuming', 'consuming.id', '=', 'attachment.phase_id')
            ->join('cycles as consumer', 'consumer.id', '=', 'consuming.cycle_id')
            ->join('receipts as receipt', 'receipt.id', '=', 'attachment.receipt_id')
            ->join('phases as generating', 'generating.id', '=', 'receipt.phase_id')
            ->join('cycles as producer', 'producer.id', '=', 'generating.cycle_id')
            ->whereColumn('producer.participant_id', '!=', 'consumer.participant_id')->exists();
        $this->assertTrue($shared, 'Receipt consumption needs no transfer from its generator');
    }
}
