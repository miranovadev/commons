<?php

/** Offline only. Pipe existing replay JSONL into this script; no database or API calls. */
require __DIR__.'/../vendor/autoload.php';

use App\Models\Connection;
use App\Services\OpenAIRequestFactory;
use App\Services\OpenAIResponseSchema;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipantPromptLayout;

$connection = new Connection;
$connection->configuration = ['model' => $argv[1] ?? 'gpt-5.4', 'instructions' => 'Preserve uncertainty.'];
$serializer = new ParticipantContextSerializer;
$prompts = new ParticipantPromptFactory($serializer);
$schemas = new OpenAIResponseSchema;
$requests = new OpenAIRequestFactory($schemas);
$encode = fn ($value) => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
$bytes = fn ($value) => strlen($encode($value));
$prefix = function (string $a, string $b): int {
    $length = min(strlen($a), strlen($b));
    for ($i = 0; $i < $length && $a[$i] === $b[$i]; $i++) {
    }

    return $i;
};
$normalize = function ($value) use (&$normalize) {
    if (! is_array($value)) {
        return $value;
    }
    if (! array_is_list($value)) {
        ksort($value, SORT_STRING);
    }

    return array_map($normalize, $value);
};
$previous = $families = $records = [];
while (($line = fgets(STDIN)) !== false) {
    $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
    $state = $row['before'] ?? null;
    if (($state['status'] ?? null) !== 'ready') {
        continue;
    }
    $legacy = $serializer->inference($state);
    $prompt = $prompts->make($connection, $state);
    $after = $requests->make($connection, $prompt);
    $restored = json_decode($prompt['user'], true, flags: JSON_THROW_ON_ERROR);
    $bodies = array_column(json_decode($prompt['evidence'], true)['loaded_receipts'], null, 'id');
    foreach ($restored['plan']['receipts'] as &$receipt) {
        $receipt = [...($bodies[$receipt['id']] ?? []), ...$receipt];
    }
    unset($receipt);
    if ($normalize($legacy) !== $normalize($restored) || $prompt['schema'] !== $legacy['plan']['schema']) {
        throw new RuntimeException('Cognition mismatch at capture '.count($records));
    }
    // Reproduce main 3f55314's exact request construction, including both schemas.
    $oldSystem = str_replace("\n\n".ParticipantPromptLayout::FORMAT_INSTRUCTION, '', $prompt['system']);
    $before = ['model' => $connection->configuration['model'], 'input' => [
        ['role' => 'system', 'content' => $oldSystem], ['role' => 'user', 'content' => $encode($legacy)],
    ], 'text' => $after['text']];
    $oldText = $oldSystem."\n".$encode($legacy);
    $newText = $prompt['system']."\n".$prompt['evidence']."\n".$prompt['user'];
    $participant = $row['participant'];
    $old = $previous[$participant] ?? null;
    $schema = $encode($after['text']);
    $sameSchema = $old !== null ? $schema === $old['schema'] : null;
    $familyKey = $participant.':'.hash('sha256', $schema);
    $priorFamily = $families[$familyKey] ?? null;
    $families[$familyKey] = ['before' => $oldText, 'after' => $newText];
    $previous[$participant] = ['before' => $oldText, 'after' => $newText, 'schema' => $schema];
    $sections = ['commons_instructions' => strlen(explode("\n\nConnection instructions:", $oldSystem)[0]),
        'participant_configuration' => strlen($oldSystem) - strlen(explode("\n\nConnection instructions:", $oldSystem)[0]),
        'origin_substrate_separate' => 0, 'loaded_receipt_block_after' => strlen($prompt['evidence'])];
    foreach ($legacy['plan'] as $name => $value) {
        $sections[$name] = $bytes($value);
    }
    $sections['provider_response_schema'] = $bytes($after['text']);
    $record = ['sample' => count($records), 'phase' => $state['plan']['phase']['number'],
        'loaded_receipts' => $prompt['loaded_receipt_count'], 'identical_response_schema_to_previous' => $sameSchema,
        'earlier_same_schema_for_participant' => $priorFamily !== null,
        'section_bytes' => $sections];
    foreach (['before' => [$before, $oldText], 'after' => [$after, $newText]] as $label => [$request, $text]) {
        $record[$label] = [
            // Guzzle's JSON transport uses default escaping; measure the full wire body.
            'request_bytes' => strlen(json_encode($request, JSON_THROW_ON_ERROR)),
            'input_json_bytes' => $bytes($request['input']),
            'visible_text_bytes' => strlen($text),
            'rough_input_tokens_bytes_div_4' => (strlen($text) + strlen($schema)) / 4,
            'common_visible_prefix_bytes' => $old !== null ? $prefix($old[$label], $text) : null,
            // Conservative structural diagnostic, NOT OpenAI's internal rendered prompt.
            'schema_compatible_prefix_bytes' => $sameSchema ? $prefix($old[$label], $text) : ($old !== null ? 0 : null),
            'previous_same_schema_prefix_bytes_no_ttl' => $priorFamily !== null ? $prefix($priorFamily[$label], $text) : 0,
            'provider_input_tokens' => null, 'provider_cached_input_tokens' => null,
            'provider_uncached_input_tokens' => null, 'provider_output_tokens' => null,
            'provider_cache_hit_ratio' => null,
        ];
    }
    $records[] = $record;
}
if ($records === []) {
    throw new RuntimeException('No ready before-states in stdin.');
}
$mean = fn ($values) => $values === [] ? null : round(array_sum($values) / count($values), 2);
$summary = [];
foreach (['all' => $records, 'receipt_heavy_10_plus' => array_values(array_filter($records, fn ($r) => $r['loaded_receipts'] >= 10))] as $group => $items) {
    $summary[$group] = ['samples' => count($items)];
    foreach (['before', 'after'] as $label) {
        foreach (array_keys($records[0][$label]) as $metric) {
            $values = array_values(array_filter(array_column(array_column($items, $label), $metric), fn ($v) => $v !== null));
            $summary[$group][$label][$metric] = $mean($values);
        }
    }
}
$maxLoaded = array_reduce($records, fn ($best, $r) => $best === null || $r['loaded_receipts'] > $best['loaded_receipts'] ? $r : $best);
$report = [
    'baseline_commit' => '3f55314eb3e9b374c8fd9038eba601a276a4925b',
    'prompt_version' => ParticipantPromptLayout::VERSION, 'model_for_request_shape_only' => $connection->configuration['model'],
    'method' => 'Historical natural captures through the current allowlisted serializer; no provider calls. Bytes/4 is a rough estimate, not a tokenizer. Prefix bytes exclude provider rendering and cache boundaries. No live cache savings are claimed.',
    'equivalent_packets' => count($records),
    'consecutive_pairs' => count(array_filter($records, fn ($r) => $r['identical_response_schema_to_previous'] !== null)),
    'identical_schema_pairs' => count(array_filter($records, fn ($r) => $r['identical_response_schema_to_previous'] === true)),
    'earlier_same_schema_samples' => count(array_filter($records, fn ($r) => $r['earlier_same_schema_for_participant'])),
    'means' => $summary,
    'representative' => ['first' => $records[0], 'most_loaded_receipts' => $maxLoaded],
];
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
