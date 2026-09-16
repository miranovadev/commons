<?php

namespace App\Jobs;

use App\Services\PotentialProjectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class RefreshPotentialProjection implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $uniqueFor = 300;
    public int $timeout = 300;
    public int $tries = 3;
    public array $backoff = [5, 15];

    public function __construct(public readonly string $substrateId)
    {
        $this->onQueue('participants');
    }

    public function uniqueId(): string
    {
        return $this->substrateId;
    }

    public function handle(PotentialProjectionService $projection): void
    {
        // Exhausted optimistic retries are contention, not a failed queue
        // attempt. Dirt remains durable and the next sweep retries it.
        $projection->refresh($this->substrateId);
    }
}
