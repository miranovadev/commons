<?php

namespace App\Console\Commands;

use App\Models\Substrate;
use App\Services\ExhaustionAuditService;
use Illuminate\Console\Command;

class ExhaustionAudit extends Command
{
    protected $signature = 'commons:exhaustion-audit {substrate : Substrate ID}';

    protected $description = 'Audit persisted Horizon Tragedy, Receipt exercise and final-attempt Exhaustion proofs.';

    public function handle(ExhaustionAuditService $audit): int
    {
        $substrate = Substrate::findOrFail($this->argument('substrate'));
        $result = $audit->inspect($substrate->id);
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $result['matches'] ? self::SUCCESS : self::FAILURE;
    }
}
