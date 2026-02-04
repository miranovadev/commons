<?php

namespace App\Console\Commands;

use App\Services\LedgerService;
use Illuminate\Console\Command;

class LedgerFetch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commons:ledger-fetch
        {--limit=25 : Number of propositions to return}
        {--offset=0 : Offset for pagination}
        {--visibility= : Optional filter: internal|external}
        {--pretty : Pretty-print JSON output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch the Commons ledger as JSON.';

    /**
     * Execute the console command.
     */
    public function handle(LedgerService $ledger)
    {
        $limit = (int) $this->option('limit');
        $offset = (int) $this->option('offset');
        $visibility = $this->option('visibility');

        if ($limit < 1 || $limit > 200) {
            $this->error('--limit must be between 1 and 200');
            return self::FAILURE;
        }

        if ($offset < 0) {
            $this->error('--offset must be >= 0');
            return self::FAILURE;
        }

        if ($visibility !== null && !in_array($visibility, ['internal', 'external'], true)) {
            $this->error('--visibility must be internal or external (or omitted)');
            return self::FAILURE;
        }

        $result = $ledger->fetch($limit, $offset, $visibility);

        $flags = JSON_UNESCAPED_SLASHES;
        if ($this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $this->line(json_encode($result, $flags));

        return self::SUCCESS;
    }
}
