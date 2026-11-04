<?php

namespace App\Domain\Evidence;

use App\Services\Evidence\ArtifactReaderRegistry;
use App\Services\Evidence\RegexSearch;
use DomainException;

/** Trusted acknowledgement of delivered representations. Never accepts Artifact bodies. */
final readonly class PreparationPresentation
{
    public function __construct(public array $deliveries, ?int $characterBudget = null)
    {
        if (! array_is_list($deliveries) || count($deliveries) < 1 || count($deliveries) > PreparationInput::MAX_SELECTIONS) {
            throw new DomainException('Presentation requires bounded successful deliveries.');
        }
        $bytes = 0;
        $ids = [];
        foreach ($deliveries as $delivery) {
            $fields = ['request_id', 'artifact_id', 'source_type', 'read_type', 'parameters', 'method', 'version',
                'representation', 'delivery_method', 'payload_hash', 'payload_bytes', 'delivery_id', 'boundaries', 'representation_hash', 'receipt_ids'];
            $resultSet = is_array($delivery) && ($delivery['representation'] ?? null) === ArtifactReaderRegistry::REGEX_RESULTS;
            if ($resultSet) {
                $fields[] = 'result_set';
            }
            if (! is_array($delivery) || array_diff(array_keys($delivery), $fields) !== [] || count($delivery) !== count($fields)
                || ! is_string($delivery['artifact_id'] ?? null) || ! preg_match('/^sha256:[a-f0-9]{64}$/D', $delivery['artifact_id'])
                || ! is_string($delivery['payload_hash'] ?? null) || ! preg_match('/^sha256:[a-f0-9]{64}$/D', $delivery['payload_hash'])
                || ! is_string($delivery['representation_hash'] ?? null) || ! preg_match('/^sha256:[a-f0-9]{64}$/D', $delivery['representation_hash'])
                || ! is_array($delivery['receipt_ids'] ?? null) || ! array_is_list($delivery['receipt_ids'])
                || count($delivery['receipt_ids']) < 1 || count($delivery['receipt_ids']) > PreparationInput::MAX_SELECTIONS
                || ! is_int($delivery['payload_bytes'] ?? null) || $delivery['payload_bytes'] < 0
                || $delivery['payload_bytes'] > (int) config('preparation.selection_bytes')) {
                throw new DomainException('Delivery must identify the exact bounded representation.');
            }
            if (count(array_unique($delivery['receipt_ids'], SORT_REGULAR)) !== count($delivery['receipt_ids'])
                || collect($delivery['receipt_ids'])->contains(fn ($id) => ! is_string($id) || ! preg_match('/^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$/D', $id))) {
                throw new DomainException('Delivery requires unique source Receipt associations.');
            }
            foreach (['request_id', 'source_type', 'read_type', 'method', 'version', 'representation', 'delivery_method', 'delivery_id'] as $field) {
                if (! is_string($delivery[$field]) || trim($delivery[$field]) === '' || mb_strlen($delivery[$field]) > 160 || str_contains($delivery[$field], "\0")) {
                    throw new DomainException('Delivery identifiers must be bounded and non-empty.');
                }
            }
            $parameters = $delivery['parameters'];
            $bounds = $delivery['boundaries'];
            if (! is_array($parameters)) {
                throw new DomainException('Delivery parameters must be a reading request.');
            }
            $legacyRegex = $delivery['read_type'] === 'regex' && $delivery['representation'] === 'regex';
            ReadingParameters::validate($delivery['read_type'], $parameters, $legacyRegex);
            if ($resultSet) {
                $summary = $delivery['result_set'];
                if ($delivery['read_type'] !== 'regex' || ! is_array($summary) || count($summary) !== 3
                    || ! array_key_exists('total_matches', $summary) || ! is_int($summary['included_matches'] ?? null)
                    || ! is_bool($summary['byte_limited'] ?? null)) {
                    throw new DomainException('Regex delivery requires its bounded enumeration summary.');
                }
                $total = $summary['total_matches'];
                if ($total !== null && (! is_int($total) || $total < 0 || $total > RegexSearch::MAX_MATCHES)) {
                    throw new DomainException('Regex total must be exact or explicitly unknown.');
                }
                $wanted = min($total ?? RegexSearch::MAX_MATCHES, $parameters['limit'] ?? RegexSearch::MAX_MATCHES);
                if ($summary['included_matches'] < 0 || $summary['included_matches'] > $wanted
                    || $summary['byte_limited'] !== ($summary['included_matches'] < $wanted)) {
                    throw new DomainException('Regex delivery must account for every omitted result.');
                }
            }
            $fields = ['unit', 'requested_offset', 'requested_max_chars', 'offset', 'end', 'length', 'total', 'has_more'];
            if ($legacyRegex) {
                $fields[] = 'match';
            }
            if (! is_array($bounds) || count($bounds) !== count($fields) || array_diff(array_keys($bounds), $fields) !== []
                || ($bounds['unit'] ?? null) !== 'unicode-characters' || ! is_int($bounds['offset'] ?? null)
                || ! is_int($bounds['end'] ?? null) || ! is_int($bounds['total'] ?? null)
                || $bounds['offset'] < 0 || $bounds['end'] < $bounds['offset'] || $bounds['total'] < $bounds['end']
                || ($bounds['has_more'] ?? null) !== ($bounds['end'] < $bounds['total'])
                || ($bounds['length'] ?? null) !== $bounds['end'] - $bounds['offset']
                || ($bounds['requested_offset'] ?? null) !== ($parameters['offset'] ?? 0)
                || ($bounds['requested_max_chars'] ?? null) !== ($parameters['max_chars'] ?? null)
                || $delivery['request_id'] !== PreparationInput::requestId($delivery)) {
                throw new DomainException('Delivery must preserve its exact requested selection and boundaries.');
            }
            $offset = $parameters['offset'] ?? 0;
            if ($legacyRegex) {
                $match = $bounds['match'];
                if ($match !== null && (! is_array($match) || count($match) !== 2
                    || ! is_int($match['start'] ?? null) || ! is_int($match['end'] ?? null)
                    || $match['start'] < 0 || $match['start'] > $match['end'] || $match['end'] > $bounds['total'])) {
                    throw new DomainException('Regex match must be a Unicode span in the searched representation.');
                }
                $offset = $match === null ? $bounds['total'] : ReadingParameters::relativeStart($match['start'], $offset, $bounds['total']);
            }
            if ($delivery['payload_bytes'] < $bounds['length'] || $delivery['payload_bytes'] > 4 * $bounds['length']
                || $bounds['offset'] !== $offset
                || $bounds['length'] > min(PreparationInput::MAX_PAYLOAD_BYTES, (int) config('preparation.max_chars'))
                || (isset($parameters['max_chars']) && ($parameters['max_chars'] < 1
                    || $bounds['length'] > $parameters['max_chars']))) {
                throw new DomainException('Delivered boundaries must preserve offsets and bounded requested allocation.');
            }
            if (isset($ids[$delivery['request_id']])) {
                throw new DomainException('Deliver each identical representation once, retaining all Receipt associations.');
            }
            $ids[$delivery['request_id']] = true;
            $bytes += $delivery['payload_bytes'];
        }
        $this->assertCharacterBudget($characterBudget);
        if ($bytes > min(PreparationInput::MAX_PAYLOAD_BYTES, (int) config('preparation.delivery_bytes'))) {
            throw new DomainException('Preparation exceeds its combined payload budget; do not truncate silently.');
        }
    }

    public function assertCharacterBudget(?int $budget): void
    {
        $characters = array_sum(array_column(array_column($this->deliveries, 'boundaries'), 'length'));
        if ($characters > PreparationInput::characterBudget($budget)) {
            throw new DomainException('Preparation exceeds its combined character budget.');
        }
    }
}
