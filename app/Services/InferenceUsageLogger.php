<?php

namespace App\Services;

use Psr\Log\LoggerInterface;
use Throwable;

final readonly class InferenceUsageLogger
{
    public function __construct(private LoggerInterface $logger) {}

    public function record(array $response, array $request, array $state, int $loadedReceiptCount, array $metadata = []): void
    {
        // Missing provider measurements are unknown, never synthetic cache misses.
        $input = $this->tokens(data_get($response, 'usage.input_tokens'));
        $cached = $this->tokens(data_get($response, 'usage.input_tokens_details.cached_tokens'));
        $written = $this->tokens(data_get($response, 'usage.input_tokens_details.cache_write_tokens'));
        $uncached = $input !== null && $cached !== null && $cached <= $input ? $input - $cached : null;
        $ordinary = $uncached !== null && $written !== null && $written <= $uncached ? $uncached - $written : null;

        try {
            $this->logger->info('commons.inference.usage', [
                'provider' => 'openai', 'model' => $response['model'] ?? $request['model'],
                'requested_model' => $request['model'], 'response_id' => $response['id'] ?? null,
                'participant_id' => $metadata['participant_id'] ?? null,
                'substrate_id' => $metadata['substrate_id'] ?? null,
                'phase_id' => $state['plan']['phase']['id'] ?? null,
                'phase' => $state['plan']['phase']['number'] ?? null, 'domain' => $state['domain'] ?? null,
                'prompt_version' => ParticipantPromptLayout::VERSION,
                'prompt_cache_key' => $request['prompt_cache_key'],
                'input_tokens' => $input, 'cached_input_tokens' => $cached,
                'uncached_input_tokens' => $uncached, 'cache_write_input_tokens' => $written,
                'ordinary_input_tokens' => $ordinary,
                'output_tokens' => $this->tokens(data_get($response, 'usage.output_tokens')),
                'cache_hit_ratio' => $uncached !== null && $input > 0 ? $cached / $input : null,
                'loaded_receipt_count' => $loadedReceiptCount,
                'request_bytes' => strlen(json_encode($request, JSON_THROW_ON_ERROR)),
            ]);
        } catch (Throwable) {
            // Cost observability must never prevent a contribution or cause a paid retry.
        }
    }

    private function tokens(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }
}
