<?php

namespace Tests\Feature;

use App\Domain\Evidence\PreparationInput;
use App\Models\EvidenceExecution;
use App\Services\ContributionService;
use App\Services\Evidence\ArtifactStore;
use App\Services\InputPlanService;
use App\Services\OpenAIResponseSchema;
use App\Services\PreparationService;
use Illuminate\Support\Facades\Storage;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use Tests\Fixtures\PreparationFixture;
use Tests\Fixtures\TextPdf;
use Tests\Support\RefreshEvidenceDatabase;
use Tests\TestCase;

class AdaptivePreparationJourneyTest extends TestCase
{
    use RefreshEvidenceDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('artifacts');
    }

    /** JSON object key order is immaterial; selected/delivered array order and scalar types are not. */
    private function canonical(array $value): array
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonical($item);
            }
        }
        unset($item);
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    public function test_small_snapshot_restricts_allocation_and_trusted_presentation_after_a_budget_increase(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $f->viewer->forceFill(['max_preparation_chars' => 4])->save();
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $fixture->input());
        $f->viewer->forceFill(['max_preparation_chars' => 30000])->save();
        $delivery = $service->delivery($f->viewer, $execution, 'human-text');
        $this->assertSame('pres', $delivery['deliveries'][0]['content']);
        try {
            $service->present($f->viewer, $execution, $fixture->presentation());
            $this->fail('The default-sized presentation exceeded the reserved four characters.');
        } catch (\DomainException $error) {
            $this->assertStringContainsString('combined character budget', $error->getMessage());
        }
        $this->assertNull($execution->fresh()->presented_at);
        $service->acknowledge($f->viewer, $execution, $delivery['presentation_token']);
        $this->assertSame('presented', $execution->fresh()->status);
    }

    public function test_pre_refinement_pending_html_text_keeps_its_version_and_global_allowance(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $html = '<p>Preserved historical markup</p>';
        $input = $fixture->input([$fixture->receipt($html)]);
        $input['artifacts'][0]['read_type'] = 'text';
        $execution = $service->start($f->viewer, $f->phase, $input);
        // A pre-refinement execution has no reading snapshot.
        EvidenceExecution::whereKey($execution->id)->update(['metadata' => null]);
        $f->viewer->forceFill(['max_preparation_chars' => 1])->save();
        $delivery = $service->delivery($f->viewer, $execution->fresh(), 'human-text');
        $this->assertSame($html, $delivery['deliveries'][0]['content']);
        $this->assertSame('2', $delivery['deliveries'][0]['version']);
        $service->acknowledge($f->viewer, $execution->fresh(), $delivery['presentation_token']);
        $this->assertSame('presented', $execution->fresh()->status);
    }

    public function test_v3_execution_can_still_deliver_and_acknowledge_its_signed_first_match_request(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $input = $fixture->input();
        $input['artifacts'][0]['read_type'] = 'regex';
        $input['artifacts'][0]['parameters'] = ['pattern' => 'artifact', 'max_chars' => 5];
        $execution = $service->start($f->viewer, $f->phase, $input);
        // Reproduce the immutable request and reader snapshot of a pre-result-set execution.
        $input['artifacts'][0]['parameters']['offset'] = -2;
        EvidenceExecution::whereKey($execution->id)->update([
            'input' => json_encode($input, JSON_THROW_ON_ERROR),
            'metadata' => json_encode(['reading' => ['text_budget' => 12000, 'native_version' => '3']], JSON_THROW_ON_ERROR),
        ]);
        $execution->refresh();
        $reading = $service->delivery($f->viewer, $execution, 'human-text');
        $result = $reading['deliveries'][0];
        $this->assertSame('3', $result['version']);
        $this->assertSame('d art', $result['content']);
        $this->assertSame(['start' => 10, 'end' => 18], $result['boundaries']['match']);
        $this->assertSame(8, $result['boundaries']['offset']);
        $service->acknowledge($f->viewer, $execution, $reading['presentation_token']);
        $this->assertSame('presented', $execution->fresh()->status);
    }

    public function test_windows_share_only_the_result_set_of_the_same_search(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $source = $fixture->receipt('hit then hit later');
        $input = $fixture->input(array_fill(0, 4, $source));
        $preferences = [
            ['limit' => 1, 'context_chars' => 0, 'max_chars' => 10],
            ['limit' => 1, 'context_chars' => 0, 'offset' => 10],
            ['limit' => 2, 'context_chars' => 0],
            ['limit' => 2, 'context_chars' => 3],
        ];
        foreach ($preferences as $i => $parameters) {
            $input['artifacts'][$i]['read_type'] = 'regex';
            $input['artifacts'][$i]['parameters'] = ['pattern' => 'hit'] + $parameters;
        }
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $input);
        $reading = $service->delivery($f->viewer, $execution, 'inline-text');
        [$first, $next, $both, $context] = $reading['deliveries'];
        $this->assertSame($first['representation_hash'], $next['representation_hash']);
        $this->assertSame($first['representation_hash'], 'sha256:'.hash('sha256', $first['content'].$next['content']));
        $this->assertSame(1, $next['result_set']['included_matches']);
        $this->assertSame(2, $both['result_set']['included_matches']);
        $this->assertNotSame($both['representation_hash'], $context['representation_hash']);
        $this->assertStringContainsString('After: " th"', $context['content']);
        $service->acknowledge($f->viewer, $execution, $reading['presentation_token']);
        $receipt = $service->defer($f->viewer, $execution, 'Compare later.');
        $this->assertSame($this->canonical($input['artifacts']), $this->canonical($receipt->metadata['selections']));
        foreach ($reading['deliveries'] as $i => $delivery) {
            unset($delivery['content']);
            $this->assertSame($this->canonical($delivery), $this->canonical($receipt->metadata['deliveries'][$i]));
        }
    }

    public function test_regex_selections_share_the_frozen_participant_allowance_through_acknowledgement(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $source = $fixture->receipt("💚".str_repeat('a', 16000).'b'.str_repeat('c', 16000));
        $input = $fixture->input([$source, $source, $source]);
        foreach (['a+', 'c+', 'missing'] as $i => $pattern) {
            $input['artifacts'][$i]['read_type'] = 'regex';
            $input['artifacts'][$i]['parameters'] = ['pattern' => $pattern, 'offset' => 0, 'limit' => 1, 'context_chars' => 0];
        }
        foreach ([[null, 12000], [17, 17], [18000, 18000]] as [$limit, $expected]) {
            $f->viewer->forceFill(['max_preparation_chars' => $limit])->save();
            $cap = collect($service->options($f->phase->fresh())[0]['capabilities'])->firstWhere('read_type', 'regex');
            $this->assertSame($expected, $cap['text_budget']);
            $this->assertSame(min($expected, 12000), $cap['max_chars']);
        }
        $schema = app(InputPlanService::class)->forPhase($f->viewer, $f->phase->fresh())['schema'];
        $action = ['action' => 'preparation.start', 'proposition_id' => $f->gate->id, ...$input];
        app(ContributionService::class)->validateSchema($schema, $action);
        $transport = app(OpenAIResponseSchema::class)->fromCommons($schema);
        $options = $transport['properties']['contribution']['anyOf'];
        $prepare = array_find($options, fn ($option) => ($option['properties']['action']['enum'] ?? []) === ['preparation.start']);
        $regex = array_find($prepare['properties']['artifacts']['items']['anyOf'], fn ($option) => $option['properties']['read_type']['enum'] === ['regex']);
        $parameters = $regex['properties']['parameters'];
        $this->assertSame('string', $parameters['properties']['pattern']['type']);
        foreach (['offset', 'max_chars', 'limit', 'context_chars'] as $field) {
            $this->assertContains($field, $parameters['required']);
            $this->assertSame(['integer', 'null'], $parameters['properties'][$field]['type']);
        }
        $example = array_replace(array_fill_keys($prepare['required'], null), $action);
        foreach ($example['artifacts'] as &$item) {
            $item['parameters'] = array_replace(array_fill_keys($parameters['required'], null), $item['parameters']);
        }
        unset($item);
        $valid = fn ($contribution) => app(Validator::class)->validate(Helper::toJSON(['contribution' => $contribution]),
            Helper::toJSON($transport))->isValid();
        $this->assertTrue($valid($example));
        $example['artifacts'][0]['parameters']['limit'] = null;
        $example['artifacts'][0]['parameters']['context_chars'] = null;
        $this->assertTrue($valid($example));
        $example['artifacts'][0]['parameters']['pattern'] = null;
        $this->assertFalse($valid($example));
        $execution = $service->start($f->viewer, $f->phase, $input);
        $human = $service->delivery($f->viewer, $execution, 'human-text');
        [$first, $second, $empty] = array_column(array_column($human['deliveries'], 'boundaries'), 'length');
        $this->assertSame(18000, $first + $second + $empty);
        $this->assertLessThanOrEqual(1, abs($first - $second));
        $this->assertSame(mb_strlen($human['deliveries'][2]['content']), $empty);
        $f->viewer->forceFill(['max_preparation_chars' => 5])->save();
        $automatic = $service->delivery($f->viewer, $execution, 'inline-text');
        foreach ($human['deliveries'] as $index => $reading) {
            $other = $automatic['deliveries'][$index];
            unset($reading['delivery_method'], $other['delivery_method']);
            $this->assertSame($reading, $other);
            $this->assertSame(PreparationInput::requestId($input['artifacts'][$index]), $reading['request_id']);
            $this->assertSame([$source->id], $reading['receipt_ids']);
        }
        $this->assertSame(0, $automatic['deliveries'][2]['result_set']['total_matches']);
        $this->assertNull($execution->fresh()->presented_at);
        $service->acknowledge($f->viewer, $execution->fresh(), $human['presentation_token']);
        $receipt = $service->defer($f->viewer, $execution, 'Compare the second section later.');
        $this->assertSame(18000, $receipt->metadata['reading']['text_budget']);
        $this->assertSame($this->canonical($input['artifacts']), $this->canonical($receipt->metadata['selections']));
        $this->assertNull($receipt->consumption);
    }

    public function test_shared_allocation_is_identical_for_humans_and_autonomous_delivery_and_history_survives_consumption(): void
    {
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $sources = [$fixture->receipt(str_repeat('A', 500)), $fixture->receipt(str_repeat('B', 3000)), $fixture->receipt(str_repeat('C', 15000))];
        $input = $fixture->input($sources);
        foreach ($input['artifacts'] as &$selection) {
            $selection['read_type'] = 'text'; // no parameters is a valid automatic selection
        }
        unset($selection);
        $schema = app(InputPlanService::class)->forPhase($f->viewer, $f->phase)['schema'];
        app(ContributionService::class)->validateSchema($schema, ['action' => 'preparation.start', 'proposition_id' => $f->gate->id, ...$input]);
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $input);
        $human = $service->delivery($f->viewer, $execution, 'human-text');
        $autonomous = $service->delivery($f->viewer, $execution, 'inline-text');
        $this->assertSame([500, 3000, 8500], array_column(array_column($human['deliveries'], 'boundaries'), 'length'));
        foreach ($human['deliveries'] as $index => $delivery) {
            $other = $autonomous['deliveries'][$index];
            unset($delivery['delivery_method'], $other['delivery_method']);
            $this->assertSame($delivery, $other);
        }
        $service->acknowledge($f->viewer, $execution, $human['presentation_token']);
        $receipt = $service->defer($f->viewer, $execution, 'Compare the remaining portion later.');
        $this->assertSame($this->canonical($input['artifacts']), $this->canonical($receipt->metadata['selections']));
        foreach ($human['deliveries'] as $index => $delivery) {
            unset($delivery['content']);
            $this->assertSame($this->canonical($delivery), $this->canonical($receipt->metadata['deliveries'][$index]));
        }
        $this->assertNull($receipt->consumption);
        $this->assertNotNull($f->phase->fresh()->completed_at);
        $this->assertNotNull($f->phase->cycle->fresh()->deferred_at);
        $original = $receipt->fresh()->metadata;
        $fixture->consume($receipt);
        $this->assertSame($original, $receipt->fresh()->metadata);
        $this->assertSame(str_repeat('C', 15000), app(ArtifactStore::class)->read($sources[2]->metadata['artifacts'][0]));
        $this->assertStringNotContainsString(str_repeat('C', 100), json_encode($receipt->metadata));
        $this->assertStringNotContainsString(str_repeat('C', 100), json_encode(app(InputPlanService::class)->forPhase($f->viewer, $f->phase)));
    }

    public function test_oversized_explicit_preferences_survive_bounded_human_and_autonomous_delivery(): void
    {
        config(['preparation.text_budget' => 9000]);
        $fixture = new PreparationFixture;
        $f = $fixture->context;
        $sources = [$fixture->receipt(str_repeat('A', 30000)), $fixture->receipt(str_repeat('B', 30000))];
        $input = $fixture->input($sources);
        $input['artifacts'][0]['parameters'] = ['offset' => 10, 'max_chars' => 20000];
        $input['artifacts'][1]['parameters'] = ['offset' => 20, 'max_chars' => 10000];
        $schema = app(InputPlanService::class)->forPhase($f->viewer, $f->phase)['schema'];
        app(ContributionService::class)->validateSchema($schema, ['action' => 'preparation.start', 'proposition_id' => $f->gate->id, ...$input]);
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $input);
        $human = $service->delivery($f->viewer, $execution, 'human-text');
        $autonomous = $service->delivery($f->viewer, $execution, 'inline-text');
        $this->assertSame([6000, 3000], array_column(array_column($human['deliveries'], 'boundaries'), 'length'));
        foreach ($human['deliveries'] as $index => $delivery) {
            $this->assertSame($delivery['boundaries'], $autonomous['deliveries'][$index]['boundaries']);
            $this->assertSame($delivery['content'], $autonomous['deliveries'][$index]['content']);
            $this->assertTrue($delivery['boundaries']['has_more']);
            $this->assertSame($input['artifacts'][$index]['parameters']['offset'], $delivery['boundaries']['offset']);
        }
        $service->acknowledge($f->viewer, $execution, $human['presentation_token']);
        $receipt = $service->defer($f->viewer, $execution, 'A useful bounded reading.');
        $this->assertSame($this->canonical($input), $this->canonical($execution->fresh()->input));
        $this->assertSame($this->canonical($input['artifacts']), $this->canonical($receipt->metadata['selections']));
        $this->assertSame([20000, 10000], array_column(array_column($receipt->metadata['deliveries'], 'boundaries'), 'requested_max_chars'));
        $this->assertSame([6010, 3020], array_column(array_column($receipt->metadata['deliveries'], 'boundaries'), 'end'));
    }

    public function test_duplicate_associations_share_budget_but_read_types_and_ranges_remain_distinct(): void
    {
        config(['preparation.text_budget' => 100]);
        $fixture = new PreparationFixture;
        $body = '<p>'.str_repeat('X', 1000).'</p>';
        $first = $fixture->receipt($body);
        $second = $fixture->receipt($body);
        $input = $fixture->input([$first, $second]);
        $input['artifacts'][1]['parameters'] = ['offset' => 0]; // equivalent default
        $input['artifacts'][] = [...$input['artifacts'][0], 'read_type' => 'sanitised', 'parameters' => ['offset' => 3, 'max_chars' => 10]];
        $input['artifacts'][] = [...$input['artifacts'][0], 'parameters' => ['offset' => 5]];
        $this->assertSame(PreparationInput::requestId($input['artifacts'][0]), PreparationInput::requestId($input['artifacts'][1]));
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $input);
        $delivery = $service->delivery($f->viewer, $execution, 'human-text');
        $this->assertCount(3, $delivery['deliveries']);
        $this->assertSame([45, 10, 45], array_column(array_column($delivery['deliveries'], 'boundaries'), 'length'));
        $this->assertSame([$first->id, $second->id], $delivery['deliveries'][0]['receipt_ids']);
        $this->assertSame([0, 3, 5], array_column(array_column($delivery['deliveries'], 'boundaries'), 'offset'));
        $service->acknowledge($f->viewer, $execution, $delivery['presentation_token']);
        $receipt = $service->contribute($f->viewer, $execution, ['action' => 'proposition.variant', 'statement' => 'Develop the separately examined representations.']);
        $this->assertSame('contributed', $receipt->metadata['outcome']['type']);
        $this->assertNotNull($f->phase->fresh()->completed_at);
        $this->assertNull($receipt->consumption);
    }

    public function test_allocation_failure_identifies_the_actual_offending_selection(): void
    {
        $fixture = new PreparationFixture;
        $second = $fixture->receipt('A different body');
        $input = $fixture->input([$fixture->source, $second]);
        $input['artifacts'][0]['parameters'] = ['offset' => 1000];
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $input);
        try {
            $service->delivery($f->viewer, $execution, 'human-text');
            $this->fail('Invalid offset was accepted.');
        } catch (\DomainException) {
            $failure = $execution->fresh()->metadata['failure'];
            $this->assertSame($input['artifacts'][0]['artifact_id'], $failure['artifact_id']);
            $this->assertSame('invalid_parameters', $failure['classification']);
            $this->assertSame('read', $execution->fresh()->receipt->metadata['failure']['stage']);
        }
    }

    public function test_pdf_failure_preserves_precise_reader_identity_in_a_truthful_failure_receipt(): void
    {
        $fixture = new PreparationFixture;
        $source = $fixture->receipt(TextPdf::make([null]));
        $input = $fixture->input([$source]);
        $input['artifacts'][0]['read_type'] = 'text';
        $f = $fixture->context;
        $service = app(PreparationService::class);
        $execution = $service->start($f->viewer, $f->phase, $input);
        try {
            $service->delivery($f->viewer, $execution, 'inline-text');
            $this->fail('Image-only PDF claimed text delivery.');
        } catch (\DomainException) {
            $execution->refresh();
            $this->assertSame('failed', $execution->status);
            $this->assertSame('failed', $execution->receipt->metadata['outcome']['type']);
            $this->assertFalse($execution->receipt->metadata['content_verified']);
            $this->assertSame([], $execution->receipt->metadata['deliveries']);
            $this->assertNull($execution->presentation);
            $this->assertSame('no_extractable_text', $execution->metadata['failure']['classification']);
            $this->assertSame('commons-poppler-text', $execution->metadata['failure']['method']);
            $this->assertSame('pdf', $execution->metadata['failure']['source_type']);
            $this->assertMatchesRegularExpression('/^1\/poppler-/', $execution->metadata['failure']['version']);
            $this->assertNull($f->phase->fresh()->completed_at);
            $this->assertSame(1, EvidenceExecution::where('type', 'prepare')->count());
        }
    }
}
