<?php

namespace App\Services;

use App\Enums\RestReason;
use App\Models\Phase;

/** Called after the action schema establishes Rest. Preserve the recorded reason on historical reads. */
final class RestReasonService
{
    public function derive(Phase $phase): RestReason
    {
        $cycle = $phase->cycle;

        return $cycle->rested_at !== null && $cycle->rest_reason !== null
            ? $cycle->rest_reason : RestReason::NoAvailableMove;
    }
}
