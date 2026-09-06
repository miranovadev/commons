<?php

namespace App\Services;

use App\Contracts\ParticipantInference;
use App\Enums\ConnectionProvider;
use App\Models\Connection;
use RuntimeException;

final readonly class ParticipantInferenceManager implements ParticipantInference
{
    public function __construct(
        private OpenAIResponsesClient $openAI,
    ) {}

    public function contribute(Connection $connection, array $state): array
    {
        return match ($connection->provider) {
            ConnectionProvider::OpenAI => $this->openAI->contribute(
                $connection,
                $state,
            ),
            default => throw new RuntimeException(
                "The {$connection->provider->value} provider is not implemented yet."
            ),
        };
    }
}
