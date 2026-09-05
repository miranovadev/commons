<?php

namespace App\Console\Commands;

use App\Models\Substrate;
use App\Services\ContextHorizonProjector;
use Illuminate\Console\Command;

class ContextHorizonsRebuild extends Command
{
    protected $signature = 'commons:horizons-rebuild
        {substrate? : Optional substrate slug}';

    protected $description = 'Rebuild Context Horizons from the substrate ledger.';

    public function handle(ContextHorizonProjector $projector): int
    {
        $slug = $this->argument('substrate');
        $substrates = Substrate::query()
            ->when($slug !== null, fn ($query) => $query->where('slug', $slug))
            ->get();

        if ($substrates->isEmpty()) {
            $this->error('No matching substrates were found.');

            return self::FAILURE;
        }

        foreach ($substrates as $substrate) {
            $count = $projector->rebuild($substrate);
            $this->info(
                "Rebuilt {$count} Context Horizons for {$substrate->slug}."
            );
        }

        return self::SUCCESS;
    }
}
