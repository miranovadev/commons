<?php

namespace App\Console\Commands;

use App\Models\Proposition;
use App\Services\Observables\PhaseProfileService;
use Illuminate\Console\Command;

class InspectPhaseProfile extends Command
{
    protected $signature = 'commons:phase-profile-inspect {proposition : Ledgered Proposition ID}';

    protected $description = 'Inspect causal Phase Profile observations and diagnostic comparison without changing production measurements.';

    public function handle(PhaseProfileService $profiles): int
    {
        $this->line(json_encode($profiles->inspect(Proposition::findOrFail($this->argument('proposition'))),
            JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
