<?php

namespace Tests\Feature;

use App\Contracts\WebSearchProvider;
use App\Domain\Evidence\SearchObservation;
use DomainException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebSearchProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.web_search.provider' => 'tavily', 'services.tavily.api_key' => 'private-test-key']);
        Http::preventStrayRequests();
    }

    public function test_provider_returns_only_bounded_normalized_resources(): void
    {
        $results = array_map(fn ($i) => ['title' => str_repeat('t', 400), 'url' => 'https://EXAMPLE.com/'.$i.'?page=2#ignored',
            'content' => str_repeat('x', 1500), 'score' => .5, 'published_date' => '2026-09-14', 'raw_content' => 'excluded'], range(1, 7));
        Http::fake(['api.tavily.com/search' => Http::response(['results' => $results, 'answer' => 'excluded', 'request_id' => 'request-123'])]);
        $observation = app(WebSearchProvider::class)->search(str_repeat('q', SearchObservation::QUERY_LIMIT));
        $this->assertCount(5, $observation->results);
        $this->assertSame([1, 2, 3, 4, 5], array_column($observation->results, 'rank'));
        $this->assertSame(300, mb_strlen($observation->results[0]['title']));
        $this->assertSame(1200, mb_strlen($observation->results[0]['excerpt']));
        $this->assertTrue($observation->results[0]['excerpt_truncated']);
        $this->assertSame('https://example.com/1?page=2', $observation->results[0]['url']);
        $this->assertSame('example.com', $observation->results[0]['source']);
        $this->assertSame(.5, $observation->results[0]['relevance']);
        $this->assertSame('2026-09-14', $observation->results[0]['published_at']);
        $this->assertSame('request-123', $observation->requestId);
        $this->assertStringNotContainsString('excluded', json_encode($observation->evidence()));
        Http::assertSentCount(1);
    }

    public static function failures(): array
    {
        return [
            'unauthorized' => [['detail' => 'private-test-key'], 401],
            'rate limited' => [['detail' => 'private-test-key'], 429],
            'server failure' => [['detail' => 'private-test-key'], 500],
            'redirect' => [[], 302],
            'not JSON' => ['<html>private-test-key</html>', 200],
            'missing results' => [['answer' => 'not evidence'], 200],
            'non-list results' => [['results' => ['bad' => []]], 200],
            'malformed result' => [['results' => [['url' => 'https://example.com']]], 200],
            'unsafe scheme' => [['results' => [['title' => 'x', 'url' => 'javascript:alert(1)', 'content' => 'x']]], 200],
            'NUL content' => [['results' => [['title' => 'x', 'url' => 'https://example.com', 'content' => "x\0"]]], 200],
            'oversized' => [str_repeat('x', 262145), 200],
        ];
    }

    #[DataProvider('failures')]
    public function test_provider_errors_are_bounded_and_sanitized(array|string $body, int $status): void
    {
        Http::fake(['api.tavily.com/search' => Http::response($body, $status)]);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Web Search is temporarily unavailable.');
        app(WebSearchProvider::class)->search('query');
    }

    public function test_connection_failure_is_sanitized(): void
    {
        Http::fake(['api.tavily.com/search' => Http::failedConnection('private-test-key')]);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Web Search is temporarily unavailable.');
        app(WebSearchProvider::class)->search('query');
    }

    public function test_unsupported_configuration_does_not_send_credentials(): void
    {
        Http::fake();
        config(['services.web_search.provider' => 'unsupported']);
        try {
            app(WebSearchProvider::class)->search('query');
            $this->fail('An unconfigured provider must fail.');
        } catch (DomainException $error) {
            $this->assertSame('Web Search is not configured.', $error->getMessage());
        }
        Http::assertNothingSent();
    }
}
