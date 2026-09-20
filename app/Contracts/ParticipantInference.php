<?php

namespace App\Contracts;

use App\Models\Connection;

interface ParticipantInference
{
    /**
     * @param  array<string, mixed>  $state
     * @param  array{participant_id?: string, substrate_id?: string}  $metadata  Transport/logging only, never model context.
     * @return array<string, mixed>
     */
    public function contribute(Connection $connection, array $state, array $metadata = []): array;
}
