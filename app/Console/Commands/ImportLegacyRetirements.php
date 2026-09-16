<?php

namespace App\Console\Commands;

use App\Models\ContextHorizon;
use App\Models\Substrate;
use App\Services\FieldMutationService;
use App\Services\PotentialProjectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyRetirements extends Command
{
    protected $signature = 'commons:exhaustion-legacy-import {substrate : Substrate ID} {--apply : Preserve missing legacy holds; never infer exhaustion or resurrect history}';

    protected $description = 'Inspect or explicitly import legacy retirement metadata after a historical database restore.';

    public function handle(): int
    {
        $substrate = Substrate::findOrFail($this->argument('substrate'));
        $query = fn () => DB::table('origin_branches')->where('substrate_id', $substrate->id)
            ->whereNotNull('pruned_at')->whereNull('legacy_retired_at');
        $this->line('Legacy retirements missing an explicit hold: '.$query()->count());
        if (! $this->option('apply')) {
            return self::SUCCESS;
        }
        DB::transaction(function () use ($substrate, $query): void {
            Substrate::whereKey($substrate->id)->lockForUpdate()->firstOrFail();
            if ($query()->update(['legacy_retired_at' => DB::raw('pruned_at')])) {
                app(FieldMutationService::class)->mark($substrate->id,
                    ContextHorizon::where('substrate_id', $substrate->id)->pluck('id')->all(), measurementsDirty: false);
            }
        });
        app(PotentialProjectionService::class)->refresh($substrate->id);

        return self::SUCCESS;
    }
}
