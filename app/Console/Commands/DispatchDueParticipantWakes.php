<?php

namespace App\Console\Commands;

use App\Services\DueParticipantWakeDispatcher;
use Illuminate\Console\Command;

class DispatchDueParticipantWakes extends Command
{
    protected $signature = 'commons:participants-wake-due {--limit=100}';

    protected $description = 'Queue wake jobs for due Participant connections';

    public function handle(DueParticipantWakeDispatcher $dispatcher): int
    {
        $count = $dispatcher->dispatch(
            max(1, (int) $this->option('limit')),
        );

        $this->info("Queued {$count} Participant wake job(s).");

        return self::SUCCESS;
    }
}
