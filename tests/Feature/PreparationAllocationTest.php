<?php

namespace Tests\Feature;

use App\Domain\Evidence\PreparationInput;
use App\Domain\Evidence\PreparationPresentation;
use App\Exceptions\ArtifactReadingException;
use App\Services\Evidence\ArtifactReaderRegistry;
use App\Services\Evidence\PreparationAllocation;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PreparationAllocationTest extends TestCase
{
    private function allocate(array $contents, array $parameters = [], int $budget = 12000): array
    {
        config(['preparation.text_budget' => $budget]);
        $reader = app(ArtifactReaderRegistry::class);

        return app(PreparationAllocation::class)->allocate(array_map(fn ($body, $index) => $reader->represent($body, 'text/plain', 'text', $parameters[$index] ?? [], 'inline-text'), $contents, array_keys($contents)));
    }

    public static function allocations(): array
    {
        return [
            'equal large' => [[15000, 15000, 15000], [], 12000, [4000, 4000, 4000]],
            'water filling' => [[500, 3000, 15000], [], 12000, [500, 3000, 8500]],
            'all fit' => [[500, 3000, 1500], [], 12000, [500, 3000, 1500]],
            'remainder order' => [[50, 50, 50], [], 10, [4, 3, 3]],
            'multiple redistributions' => [[1, 2, 100, 100], [], 12, [1, 2, 5, 4]],
            'explicit' => [[15000], [['max_chars' => 6000]], 12000, [6000]],
            'explicit maximum respected' => [[10000, 50], [['max_chars' => 100]], 12000, [100, 50]],
            'mixed' => [[10000, 10000, 10000], [['max_chars' => 2000]], 12000, [2000, 5000, 5000]],
            'explicit short redistributes' => [[800, 10000, 10000], [['max_chars' => 2000]], 12000, [800, 5600, 5600]],
            'explicit beyond EOF' => [[20, 100], [['max_chars' => 100]], 60, [20, 40]],
            'offset only' => [[500, 15000], [['offset' => 400]], 12000, [100, 11900]],
            'offset and length' => [[500, 15000], [['offset' => 400, 'max_chars' => 40]], 12000, [40, 11960]],
            'end offset' => [[50, 50], [['offset' => 50]], 100, [0, 50]],
            'empty' => [[0, 0, 100], [], 50, [0, 0, 50]],
        ];
    }

    #[DataProvider('allocations')]
    public function test_deterministic_water_filling(array $lengths, array $parameters, int $budget, array $expected): void
    {
        $contents = array_map(fn ($length) => str_repeat('x', $length), $lengths);
        $results = $this->allocate($contents, $parameters, $budget);
        $this->assertSame($expected, array_column(array_column($results, 'boundaries'), 'length'));
        $this->assertSame($results, $this->allocate($contents, $parameters, $budget));
        foreach ($results as $index => $result) {
            $bounds = $result['boundaries'];
            $this->assertSame($parameters[$index]['max_chars'] ?? null, $bounds['requested_max_chars']);
            $this->assertSame($bounds['offset'] + $bounds['length'], $bounds['end']);
            $this->assertSame($lengths[$index], $bounds['total']);
            $this->assertSame($bounds['end'] < $bounds['total'], $bounds['has_more']);
            $this->assertSame('sha256:'.hash('sha256', $contents[$index]), $result['representation_hash']);
        }
    }

    public function test_unicode_and_byte_constraints_redistribute_to_selections_that_still_fit(): void
    {
        config(['preparation.selection_bytes' => 8, 'preparation.delivery_bytes' => 15]);
        $results = $this->allocate([str_repeat('💚', 10), str_repeat('é', 10), str_repeat('x', 10)], budget: 30);
        $this->assertSame(['💚💚', 'éé', 'xxx'], array_column($results, 'content'));
        $this->assertSame([2, 2, 3], array_column(array_column($results, 'boundaries'), 'length'));
        $this->assertSame([8, 4, 3], array_column($results, 'payload_bytes'));
        config(['preparation.selection_bytes' => 5, 'preparation.delivery_bytes' => 50]);
        $results = $this->allocate([str_repeat('💚', 10), str_repeat('x', 10)], budget: 10);
        $this->assertSame(['💚', 'xxxxx'], array_column($results, 'content'));
    }

    public function test_no_partial_unicode_character_is_delivered_at_a_byte_boundary(): void
    {
        config(['preparation.delivery_bytes' => 5]);
        $result = $this->allocate(['💚💚', 'éé', 'abc'], budget: 20);
        $this->assertSame(['💚', '', 'a'], array_column($result, 'content'));
        $this->assertSame(5, array_sum(array_column($result, 'payload_bytes')));
    }

    public static function invalidBudgets(): array
    {
        return [
            'explicit total characters' => [['abcdefgh', 'abcdefgh'], [['max_chars' => 8], ['max_chars' => 8]], 10, []],
            'explicit per-selection bytes' => [['💚💚'], [['max_chars' => 2]], 10, ['preparation.selection_bytes' => 7]],
            'explicit combined bytes' => [['💚', 'é'], [['max_chars' => 1], ['max_chars' => 1]], 10, ['preparation.delivery_bytes' => 5]],
            'offset beyond EOF' => [['abc'], [['offset' => 4]], 10, []],
        ];
    }

    #[DataProvider('invalidBudgets')]
    public function test_invalid_explicit_ranges_are_rejected_without_silent_shortening(array $contents, array $parameters, int $budget, array $limits): void
    {
        config($limits);
        $this->expectException(ArtifactReadingException::class);
        $this->allocate($contents, $parameters, $budget);
    }

    public function test_documented_example_satisfies_the_real_input_and_presentation_contracts(): void
    {
        $example = json_decode(file_get_contents(base_path('docs/evidence/artifact-readers-stage-3c/example.json')), true, flags: JSON_THROW_ON_ERROR);
        $request = $example['request'];
        unset($request['action'], $request['proposition_id']);
        $input = PreparationInput::from($request);
        $presentation = new PreparationPresentation($example['receipt']['metadata']['deliveries']);
        $this->assertSame($input->artifacts, $example['receipt']['metadata']['selections']);
        $this->assertSame([500, 3000, 8500], array_column(array_column($presentation->deliveries, 'boundaries'), 'length'));
        foreach ($presentation->deliveries as $index => $delivery) {
            $body = str_repeat(chr(65 + $index), [500, 3000, 15000][$index]);
            $this->assertSame('sha256:'.hash('sha256', $body), $delivery['representation_hash']);
            $this->assertSame('sha256:'.hash('sha256', substr($body, 0, $delivery['boundaries']['length'])), $delivery['payload_hash']);
        }
    }

    public function test_raw_and_sanitised_offsets_address_their_own_unchanged_representation(): void
    {
        $reader = app(ArtifactReaderRegistry::class);
        $body = '<p>A💚BC</p>';
        $raw = $reader->read($body, 'text/html', 'raw', ['offset' => 3, 'max_chars' => 2], 'inline-text');
        $clean = $reader->read($body, 'text/html', 'sanitised', ['offset' => 1], 'human-text');
        $this->assertSame('A💚', $raw['content']);
        $this->assertSame('💚BC', $clean['content']);
        $this->assertNotSame($raw['representation_hash'], $clean['representation_hash']);
        $this->assertSame(4, $clean['boundaries']['total']);
        $this->assertSame($body, $reader->read($body, 'text/html', 'raw', [], 'human-text')['content']);
    }
}
