<?php

namespace App\Console\Commands;

use App\Models\Phase;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\CausalContextService;
use Illuminate\Console\Command;
use Throwable;

class AuditCausalEntries extends Command
{
    protected $signature = 'commons:causal-audit {substrate? : Optional substrate slug}';

    protected $description = 'Report invalid persisted Gate/Horizon and Domain/register relationships without changing history.';

    public function handle(CausalContextService $causality): int
    {
        $substrates = Substrate::query()->when($this->argument('substrate') !== null,
            fn ($query) => $query->where('slug', $this->argument('substrate')))->get();
        if ($substrates->isEmpty()) {
            $this->error('No matching substrates were found.');

            return self::FAILURE;
        }
        $failures = 0;
        foreach ($substrates as $substrate) {
            $phases = Phase::whereHas('cycle.contextHorizon', fn ($query) => $query->where('substrate_id', $substrate->id));
            foreach ($phases->lazyById() as $phase) {
                try {
                    $causality->assertPhase($phase);
                } catch (Throwable $exception) {
                    $failures++;
                    $this->error("{$substrate->slug}: Phase {$phase->id}, Cycle {$phase->cycle_id}, Gate {$phase->gate_proposition_id}: {$exception->getMessage()}");
                }
            }
            foreach (Proposition::whereHas('phase.cycle.contextHorizon', fn ($query) => $query->where('substrate_id', $substrate->id))->lazyById() as $proposition) {
                try {
                    $causality->assertRegister($proposition->register, $proposition->phase->cycle->contextHorizon);
                } catch (Throwable $exception) {
                    $failures++;
                    $this->error("{$substrate->slug}: Proposition {$proposition->id}: {$exception->getMessage()}");
                }
            }
        }
        $this->info("Causal audit: {$failures} invalid relationships. No records changed.");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
