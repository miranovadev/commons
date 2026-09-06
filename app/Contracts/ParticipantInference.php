<?php

namespace App\Contracts;

use App\Models\Connection;

interface ParticipantInference
{
    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function contribute(Connection $connection, array $state): array;
}
