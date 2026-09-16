<?php

namespace App\Console\Commands;

use App\Jobs\RefreshPotentialProjection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RefreshPotential extends Command
{
    protected $signature = 'commons:potential-refresh';

    protected $description = 'Dispatch maintenance for initialized substrates with durable field mutations';

    public function handle(): int
    {
        DB::table('potential_dirty_horizons as dirty')->join('potential_projection_states as projection',
            'projection.substrate_id', '=', 'dirty.substrate_id')->select('dirty.substrate_id')->distinct()->orderBy('dirty.substrate_id')
            ->get()->each(fn ($row) => RefreshPotentialProjection::dispatch($row->substrate_id));

        return self::SUCCESS;
    }
}
