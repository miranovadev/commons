<?php

namespace App\Console\Commands;

use App\Models\Substrate;
use App\Services\LedgerService;
use Illuminate\Console\Command;

class LedgerFetch extends Command
{
    protected $signature = 'commons:ledger-fetch
        {substrate : Substrate slug}
        {--limit=25 : Number of entries to return}
        {--before= : Return entries before this sequence}
        {--pretty : Pretty-print JSON output}';

    protected $description = 'Fetch an ordered slice of a substrate ledger.';

    public function handle(LedgerService $ledger): int
    {
        $limit = (int) $this->option('limit');
        $before = $this->option('before');

        if ($limit < 1 || $limit > 200) {
            $this->error('--limit must be between 1 and 200.');

            return self::FAILURE;
        }

        if (
            $before !== null
            && (! ctype_digit((string) $before) || (int) $before < 1)
        ) {
            $this->error('--before must be a positive ledger sequence.');

            return self::FAILURE;
        }

        $substrate = Substrate::query()
            ->where('slug', $this->argument('substrate'))
            ->first();

        if ($substrate === null) {
            $this->error('The requested substrate does not exist.');

            return self::FAILURE;
        }

        $result = $ledger->fetch(
            $substrate,
            $limit,
            $before === null ? null : (int) $before,
        );
        $flags = JSON_UNESCAPED_SLASHES;

        if ($this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $this->line(json_encode($result, $flags | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
