<?php

namespace App\Console\Commands;

use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\Observables\LineageMeasurementService;
use App\Services\PropositionMeasurementService;
use App\Services\RealtimePublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairPropositionMeasurements extends Command
{
    protected $signature = 'commons:measurements-repair {substrate : Substrate slug} {--limit=100 : Maximum projections to inspect (1-500)}';

    protected $description = 'Repair missing/obsolete measurable projections outside HTTP observation';

    public function handle(PropositionMeasurementService $measurements, RealtimePublisher $realtime): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
        if ($limit === false) {
            $this->error('The limit must be an integer between 1 and 500.');

            return self::INVALID;
        }
        $substrate = Substrate::where('slug', $this->argument('substrate'))->firstOrFail();
        $ids = Proposition::query()
            ->whereHas('contextHorizonMembership.horizon', fn ($query) => $query->where('substrate_id', $substrate->id))
            ->where(fn ($query) => $query->whereDoesntHave('measurement')
                ->orWhereHas('measurement', fn ($query) => $query->whereNull('calculator_version')
                    ->orWhere('calculator_version', '!=', LineageMeasurementService::VERSION)))
            ->orderBy('id')->limit($limit)->pluck('id');

        $repaired = 0;
        foreach ($ids as $id) {
            // One root-ordered transaction per node, never a whole-substrate repair
            // transaction. Re-read after acquiring the lock; another worker may
            // already have repaired this projection while we waited.
            $repaired += DB::transaction(function () use ($substrate, $id, $measurements, $realtime): int {
                Substrate::query()->lockForUpdate()->findOrFail($substrate->id);
                $node = Proposition::with('measurement')->find($id);
                if ($node === null || $node->measurement?->calculator_version === LineageMeasurementService::VERSION) {
                    return 0;
                }
                $measurements->repairMissing(collect([$node]));
                if ($node->measurement?->calculator_version !== LineageMeasurementService::VERSION) {
                    return 0;
                }
                $realtime->proposition($id);

                return 1;
            }, attempts: 3);
        }
        $this->info("Repaired {$repaired} projection(s).");

        return self::SUCCESS;
    }
}
