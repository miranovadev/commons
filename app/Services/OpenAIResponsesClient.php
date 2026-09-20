<?php

namespace App\Services;

use App\Models\Connection;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

final readonly class OpenAIResponsesClient
{
    public function __construct(
        private HttpFactory $http,
        private ParticipantPromptFactory $prompts,
        private OpenAIRequestFactory $requests,
        private InferenceUsageLogger $usage,
    ) {}

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function contribute(Connection $connection, array $state, array $metadata = []): array
    {
        $apiKey = data_get($connection->credentials, 'api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('The OpenAI connection has no API key.');
        }

        $model = data_get($connection->configuration, 'model');

        if (! is_string($model) || $model === '') {
            throw new RuntimeException('The OpenAI connection has no model.');
        }

        $prompt = $this->prompts->make($connection, $state);
        $request = $this->requests->make($connection, $prompt, $metadata);
        $response = $this->http
            ->baseUrl(rtrim((string) config('services.openai.base_url'), '/'))
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('services.openai.connect_timeout', 10))
            ->timeout((int) config('services.openai.timeout', 90))
            ->retry(3, 250, throw: false)
            ->post('responses', $request)
            ->throw()
            ->json();

        $this->usage->record($response, $request, $state, $prompt['loaded_receipt_count'], $metadata);

        $text = $this->outputText($response);
        $output = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        $contribution = is_array($output)
            ? ($output['contribution'] ?? null)
            : null;

        if (! is_array($contribution)) {
            throw new RuntimeException(
                'OpenAI returned no structured Commons contribution.'
            );
        }

        return $this->withoutNulls($contribution);
    }

    /** @param array<string, mixed> $response */
    private function outputText(array $response): string
    {
        foreach ($response['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new RuntimeException(
                        'OpenAI refused the contribution: '.
                        ($content['refusal'] ?? 'No reason was supplied.'),
                    );
                }

                if (($content['type'] ?? null) === 'output_text'
                    && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        throw new RuntimeException('OpenAI returned no output text.');
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withoutNulls(array $values): array
    {
        return array_filter(
            array_map(
                fn (mixed $value): mixed => is_array($value)
                    ? $this->withoutNulls($value)
                    : $value,
                $values,
            ),
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
