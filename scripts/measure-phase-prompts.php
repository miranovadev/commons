<?php

/** Offline comparison against merged #187. No database, inference, or provider calls. */
require __DIR__.'/../vendor/autoload.php';

use App\Enums\PhaseKind;
use App\Models\Connection;
use App\Services\OpenAIRequestFactory;
use App\Services\OpenAIResponseSchema;
use App\Services\ParticipantContextSerializer;
use App\Services\ParticipantPhaseInstructions;
use App\Services\ParticipantPromptCore;
use App\Services\ParticipantPromptFactory;
use App\Services\ParticipantPromptLayout;

$baselinePath = $argv[1] ?? throw new InvalidArgumentException('Pass the #187 ParticipantPromptFactory.php export as the first argument.');
$source = file_get_contents($baselinePath);
if (! preg_match("/\\\$system = <<<'PROMPT'\\n(.*?)\\nPROMPT;/s", $source, $match)) {
    throw new RuntimeException('Missing #187 constitution heredoc.');
}
$constitution = $match[1];
if (hash('sha256', $constitution) !== '02b6c08b3d5f7c7884aa3fd7c981a808dae17b5e4cf0692ee4d338e99d328ed3') {
    throw new RuntimeException('Baseline must be the corrected, merged #187 constitution.');
}
// Both architectures use #187's identical packet/evidence/output-schema code.
// Fail rather than silently mislabel a future packet redesign as this baseline.
foreach ([
    'ParticipantPromptLayout' => '83ac5cf8af609f79a2697b8c1414fa90a179ebf36a7003bf51362eb79eac5f27',
    'ParticipantContextSerializer' => 'b06462f39a680a0906fb21e7fb953043a65267bb960a8a43aeb4ff880ae503b5',
    'OpenAIResponseSchema' => '6d51ee68cd43a88576baa6b3b42500b87eabad4112e5986f0e8e2a9fb5817c98',
] as $class => $expectedHash) {
    $contractSource = file_get_contents(__DIR__.'/../app/Services/'.$class.'.php');
    $contractSource = preg_replace("/public const VERSION = '[^']+';/", 'public const VERSION = __IGNORED__;', $contractSource);
    if (hash('sha256', $contractSource) !== $expectedHash) {
        throw new RuntimeException($class.' changed; update the historical comparator before measuring.');
    }
}
$captures = array_slice($argv, 2) ?: [
    __DIR__.'/../tests/Replay/Golden/solo-domain-natural.jsonl.xz',
    __DIR__.'/../tests/Replay/Golden/collective-domain-natural.jsonl.xz',
];
$encode = fn ($value) => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
$normalize = function ($value) use (&$normalize) {
    if (! is_array($value)) {
        return $value;
    }
    if (! array_is_list($value)) {
        ksort($value, SORT_STRING);
    }

    return array_map($normalize, $value);
};
$mean = fn (array $values) => round(array_sum($values) / max(1, count($values)), 2);
$prefix = function (string $a, string $b): int {
    $n = min(strlen($a), strlen($b));
    for ($i = 0; $i < $n && $a[$i] === $b[$i]; $i++) {
    }

    return $i;
};
$connection = (new Connection)->forceFill(['id' => 'connection', 'configuration' => ['model' => 'gpt-5.6', 'instructions' => 'Preserve uncertainty.']]);
$custom = "\n\nConnection instructions:\nPreserve uncertainty.";
$serializer = new ParticipantContextSerializer;
$factory = new ParticipantPromptFactory($serializer);
$schemas = new OpenAIResponseSchema;
$requests = new OpenAIRequestFactory($schemas);
$contracts = new ParticipantPhaseInstructions;
$core = ParticipantPromptCore::TEXT."\n\n".ParticipantPromptLayout::FORMAT_INSTRUCTION;
$oldSystem = $constitution."\n\n".ParticipantPromptLayout::FORMAT_INSTRUCTION.$custom;
$records = $previous = $phaseHistory = $crossParticipant = $crossEvidence = $cycles = $seen = $participants = [];
$stats = ['calls' => 0, 'adjacent_pairs' => 0, 'adjacent_same_phase' => 0, 'adjacent_cross_phase' => 0,
    'adjacent_same_evidence' => 0, 'cross_phase_same_evidence' => 0, 'adjacent_same_schema' => 0,
    'returns_to_previously_seen_phase' => 0, 'calls_with_a_prior_same_phase_call' => 0,
    'prior_same_phase_same_evidence' => 0, 'phase_previously_seen_from_another_participant' => 0,
    'same_phase_evidence_previously_seen_from_another_participant' => 0];
foreach ($captures as $captureIndex => $capture) {
    $process = proc_open(['xz', '-dc', $capture], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => STDERR], $pipes);
    if (! is_resource($process)) {
        throw new RuntimeException('Cannot open capture.');
    }
    while (($line = fgets($pipes[1])) !== false) {
        $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        $state = $row['before'] ?? null;
        if (($state['status'] ?? null) !== 'ready') {
            continue;
        }
        $participant = $captureIndex.':'.$row['participant'];
        $participants[$participant] = true;
        $phase = PhaseKind::from($state['plan']['phase']['number']);
        $packet = $serializer->inference($state);
        $prompt = $factory->make($connection, $state);
        $current = $requests->make($connection, $prompt);
        $restored = json_decode($prompt['user'], true, flags: JSON_THROW_ON_ERROR);
        $bodies = array_column(json_decode($prompt['evidence'], true)['loaded_receipts'], null, 'id');
        foreach ($restored['plan']['receipts'] as &$receipt) {
            $receipt = [...($bodies[$receipt['id']] ?? []), ...$receipt];
        }
        unset($receipt);
        if ($normalize($packet) !== $normalize($restored) || $prompt['schema'] !== $packet['plan']['schema']
            || $schemas->fromCommons($packet['plan']['schema']) !== $current['text']['format']['schema']) {
            throw new RuntimeException('Packet/action/output-schema mismatch at call '.$stats['calls']);
        }
        $phaseText = $contracts->forPhase($phase, isset($prompt['schema']['$defs']['actions']['participant.rest'])).$custom;
        $schemaText = $encode($current['text']);
        $schemaHash = hash('sha256', $schemaText);
        $evidenceHash = hash('sha256', $prompt['evidence']);
        $old = $previous[$participant] ?? null;
        $stats['calls']++;
        if ($old !== null) {
            $same = $old['phase'] === $phase->value;
            $stats['adjacent_pairs']++;
            $stats[$same ? 'adjacent_same_phase' : 'adjacent_cross_phase']++;
            $stats['adjacent_same_evidence'] += (int) ($old['evidence'] === $evidenceHash);
            $stats['cross_phase_same_evidence'] += (int) (! $same && $old['evidence'] === $evidenceHash);
            $stats['adjacent_same_schema'] += (int) ($old['schema'] === $schemaHash);
            $stats['returns_to_previously_seen_phase'] += (int) (! $same && isset($phaseHistory[$participant][$phase->value]));
        }
        $priorPhase = $phaseHistory[$participant][$phase->value] ?? null;
        if ($priorPhase !== null) {
            $stats['calls_with_a_prior_same_phase_call']++;
            $stats['prior_same_phase_same_evidence'] += (int) ($priorPhase['evidence'] === $evidenceHash);
        }
        $stats['phase_previously_seen_from_another_participant'] += (int) (count(array_diff(array_keys($crossParticipant[$captureIndex][$phase->value] ?? []), [$participant])) > 0);
        $stats['same_phase_evidence_previously_seen_from_another_participant'] += (int) (count(array_diff(array_keys($crossEvidence[$captureIndex][$phase->value][$evidenceHash] ?? []), [$participant])) > 0);
        $crossParticipant[$captureIndex][$phase->value][$participant] = true;
        $crossEvidence[$captureIndex][$phase->value][$evidenceHash][$participant] = true;
        $previous[$participant] = $phaseHistory[$participant][$phase->value] = ['phase' => $phase->value, 'evidence' => $evidenceHash, 'schema' => $schemaHash];
        $cycle = $participant.':'.$state['cycle']['id'];
        $cycleValues = ['before' => strlen($oldSystem), 'after' => strlen($prompt['system'])];
        foreach ($cycleValues as $label => $size) {
            $cycles[$cycle][$label] = ($cycles[$cycle][$label] ?? 0) + $size;
        }
        $record = ['phase' => $phase->value, 'loaded' => $prompt['loaded_receipt_count']];
        foreach (['187', 'phase_first', 'evidence_first_2', 'evidence_first_3', 'phase_first_phase_key'] as $mode) {
            $messages = match ($mode) {
                '187' => [['system', $oldSystem], ['user', $prompt['evidence']], ['user', $prompt['user']]],
                'phase_first', 'phase_first_phase_key' => [['system', $prompt['system']], ['user', $prompt['evidence']], ['user', $prompt['user']]],
                default => [['system', $core], ['user', $prompt['evidence']], ['system', $phaseText], ['user', $prompt['user']]],
            };
            $lastBoundary = $mode === 'evidence_first_3' ? 2 : 1;
            $visible = implode("\n", array_column($messages, 1));
            $total = (array_sum(array_map(fn ($m) => strlen($m[1]), $messages)) + strlen($schemaText)) / 4;
            $input = [];
            foreach ($messages as $i => [$role, $text]) {
                $input[] = ['role' => $role, 'content' => $i <= $lastBoundary
                    ? [['type' => 'input_text', 'text' => $text, 'prompt_cache_breakpoint' => ['mode' => 'explicit']]] : $text];
            }
            $request = [...$current, 'input' => $input];
            if ($mode === '187') {
                $request['prompt_cache_key'] = 'commons-prefix-v1:'.substr(hash('sha256', json_encode([
                    'gpt-5.6', 'commons-prefix-v1', $oldSystem, 'connection',
                ], JSON_THROW_ON_ERROR)), 0, 40);
            }
            if ($mode === 'phase_first' && $request !== $current) {
                throw new RuntimeException('Modeled selected layout differs from the production request.');
            }
            $record[$mode] = ['instruction_bytes' => $mode === '187' ? strlen($oldSystem) : strlen($prompt['system']),
                'request_bytes' => strlen(json_encode($request, JSON_THROW_ON_ERROR)), 'rough_input_tokens' => $total,
                'shared_visible_prefix_bytes' => isset($old['visible'][$mode]) ? $prefix($old['visible'][$mode], $visible) : null,
                'same_phase_visible_prefix_bytes' => $old !== null && $old['phase'] === $phase->value ? $prefix($old['visible'][$mode], $visible) : null,
                'cross_phase_visible_prefix_bytes' => $old !== null && $old['phase'] !== $phase->value ? $prefix($old['visible'][$mode], $visible) : null];
            $previous[$participant]['visible'][$mode] = $visible;
            foreach (['participant', 'substrate'] as $scope) {
                $pool = $mode.':'.$scope.':'.($scope === 'participant' ? $participant : $captureIndex)
                    .($mode === 'phase_first_phase_key' ? ':'.$phase->value : '');
                $prefixMessages = [];
                $bytes = strlen($schemaText);
                $read = $cacheEnd = 0;
                $keys = [];
                foreach ($messages as $i => $message) {
                    if ($i > $lastBoundary) {
                        break;
                    }
                    $prefixMessages[] = $message;
                    $bytes += strlen($message[1]);
                    // Eligibility is approximate too: bytes/4 is not provider tokenization.
                    if ($bytes / 4 < 1024) {
                        continue;
                    }
                    $cacheEnd = $bytes / 4;
                    $key = hash('sha256', $schemaText.$encode($prefixMessages));
                    $keys[] = $key;
                    if (isset($seen[$pool][$key])) {
                        $read = max($read, $cacheEnd);
                    }
                }
                foreach ($keys as $key) {
                    $seen[$pool][$key] = true;
                }
                // Assume one incremental write through the longest boundary; overlapping
                // writes are not counted twice. Provider accounting may differ.
                $write = max(0, $cacheEnd - $read);
                $ordinary = $total - $read - $write;
                $record[$mode][$scope] = ['read_rough_tokens' => $read, 'write_rough_tokens' => $write,
                    'modeled_hit' => (int) ($read > 0), 'cold_cache_cost_units' => $total + .25 * $cacheEnd,
                    'retained_cache_cost_units' => $ordinary + .1 * $read + 1.25 * $write];
            }
        }
        $records[] = $record;
    }
    fclose($pipes[1]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Capture decompression failed.');
    }
}
if (count($records) !== 1230) {
    throw new RuntimeException('Expected the existing 1,230-state corpus.');
}
$summary = [];
foreach (['all' => $records, 'receipt_heavy_10_plus' => array_values(array_filter($records, fn ($r) => $r['loaded'] >= 10))] as $group => $items) {
    $summary[$group]['calls'] = count($items);
    foreach (['187', 'phase_first', 'evidence_first_2', 'evidence_first_3', 'phase_first_phase_key'] as $mode) {
        $values = array_column($items, $mode);
        foreach (['instruction_bytes', 'request_bytes', 'rough_input_tokens', 'shared_visible_prefix_bytes', 'same_phase_visible_prefix_bytes', 'cross_phase_visible_prefix_bytes'] as $metric) {
            $summary[$group][$mode][$metric] = $mean(array_values(array_filter(array_column($values, $metric), fn ($v) => $v !== null)));
        }
        foreach (['participant', 'substrate'] as $scope) {
            $scoped = array_column($values, $scope);
            foreach (array_keys($scoped[0]) as $metric) {
                $summary[$group][$mode][$scope][$metric] = $metric === 'modeled_hit' ? array_sum(array_column($scoped, $metric)) : $mean(array_column($scoped, $metric));
            }
        }
    }
}
$phaseSizes = [];
foreach (PhaseKind::cases() as $phase) {
    $items = array_values(array_filter($records, fn ($r) => $r['phase'] === $phase->value));
    $phaseSizes[$phase->name] = ['calls' => count($items), 'contract_bytes' => strlen($contracts->forPhase($phase)),
        'contract_with_rest_bytes' => strlen($contracts->forPhase($phase, true)),
        'mean_system_bytes' => $mean(array_column(array_column($items, 'phase_first'), 'instruction_bytes'))];
}
$report = ['baseline_commit' => '5fc2fb9be8b096a22206fa639edb7fee61cce794',
    'baseline_constitution_sha256' => hash('sha256', $constitution), 'baseline_constitution_bytes' => strlen($constitution),
    'core_bytes' => strlen(ParticipantPromptCore::TEXT), 'layout_version' => ParticipantPromptLayout::VERSION,
    'model_for_shape_only' => 'gpt-5.6', 'equivalent_packets' => count($records),
    'method' => 'Bytes are measured; tokens are bytes/4 estimates over visible text plus response format, excluding hidden rendering. Historical inference calls are not production wakes. Prefixes are diagnostic, not cache hits. Model requires exact schema and complete message-prefix equality at eligible boundaries; minimum eligibility also uses estimated tokens. No TTL, eviction, routing loss or request concurrency. Participant scope is conservative; substrate scope assumes identical custom instructions. Read rate 0.1, write rate 1.25, ordinary rate 1 are illustrative relative units, not measured bills. Incremental writes count unique tokens through the longest boundary once. No provider calls or production telemetry are available.',
    'recurrence' => [...$stats, 'participants' => count($participants)], 'phases' => $phaseSizes,
    'observed_cycle_instruction_totals' => ['cycles' => count($cycles), 'mean_before_bytes' => $mean(array_column($cycles, 'before')), 'mean_after_bytes' => $mean(array_column($cycles, 'after')),
        'caveat' => 'Observed Cycle fragments, which may start/end mid-Cycle; not measured complete-wake totals.'],
    'means' => $summary,
];
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
