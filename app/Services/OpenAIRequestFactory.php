<?php

namespace App\Services;

use App\Models\Connection;

final readonly class OpenAIRequestFactory
{
    public function __construct(private OpenAIResponseSchema $schemas) {}

    public function make(Connection $connection, array $prompt, array $metadata = []): array
    {
        $model = $connection->configuration['model'];
        // Known supported families only. Earlier/unknown models retain implicit caching.
        $explicit = preg_match('/^gpt-(?:5\.6|6)(?:[-.]|$)/', $model) === 1;
        $content = fn (string $text): array => [['type' => 'input_text', 'text' => $text,
            ...($explicit ? ['prompt_cache_breakpoint' => ['mode' => 'explicit']] : [])]];
        // Pool the complete instruction catalog and custom constitution within a substrate.
        // The selected Phase changes the prefix, not its routing family. State and schema stay out.
        $family = [$model, ParticipantPromptLayout::VERSION, $prompt['instruction_family'],
            $metadata['substrate_id'] ?? $connection->getKey()];

        return [
            'model' => $model,
            'input' => [
                ['role' => 'system', 'content' => $explicit ? $content($prompt['system']) : $prompt['system']],
                ['role' => 'user', 'content' => $explicit ? $content($prompt['evidence']) : $prompt['evidence']],
                ['role' => 'user', 'content' => $prompt['user']],
            ],
            'text' => ['format' => [
                'type' => 'json_schema', 'name' => 'commons_phase_contribution', 'strict' => true,
                'schema' => $this->schemas->fromCommons($prompt['schema']),
            ]],
            'prompt_cache_key' => ParticipantPromptLayout::VERSION.':'.substr(hash('sha256', json_encode($family, JSON_THROW_ON_ERROR)), 0, 40),
            ...($explicit ? ['prompt_cache_options' => ['mode' => 'explicit']] : []),
        ];
    }
}
