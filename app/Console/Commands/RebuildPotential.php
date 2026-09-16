<?php

namespace App\Console\Commands;

use App\Models\Substrate;
use App\Services\PotentialProjectionService;
use Illuminate\Console\Command;

final class RebuildPotential extends Command
{
    protected $signature = 'commons:potential-rebuild {substrate : Substrate ID or slug}';

    protected $description = 'Explicitly rebuild active measurements and Potential from ledger state, outside write locks';

    public function handle(PotentialProjectionService $projection): int
    {
        $substrate = Substrate::where('id', $this->argument('substrate'))->orWhere('slug', $this->argument('substrate'))->firstOrFail();
        $start = microtime(true);
        if (! $projection->rebuild($substrate->id)) {
            $this->error('The field changed during projection maintenance. Retry when writers are quieter; no stale result was published.');
            return self::FAILURE;
        }
        $this->info(sprintf('Potential projection current at field version %d (%.3fs).', $projection->assertCurrent($substrate->id), microtime(true) - $start));

        return self::SUCCESS;
    }
}
