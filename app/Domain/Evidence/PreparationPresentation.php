<?php

namespace App\Domain\Evidence;

use DomainException;

/** Trusted acknowledgement of delivered representations. Never accepts Artifact bodies. */
final readonly class PreparationPresentation
{
    public function __construct(public array $deliveries)
    {
        if (! array_is_list($deliveries) || count($deliveries) < 1 || count($deliveries) > PreparationInput::MAX_SELECTIONS) {
            throw new DomainException('Presentation requires bounded successful deliveries.');
        }
        $bytes = 0;
        $characters = 0;
        $ids = [];
        foreach ($deliveries as $delivery) {
            $fields = ['request_id', 'artifact_id', 'source_type', 'read_type', 'parameters', 'method', 'version',
                'representation', 'delivery_method', 'payload_hash', 'payload_bytes', 'delivery_id', 'boundaries', 'representation_hash', 'receipt_ids'];
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
            if (! is_array($parameters) || array_diff(array_keys($parameters), ['offset', 'max_chars']) !== []
                || collect($parameters)->contains(fn ($value) => ! is_int($value) || $value < 0)
                || ! is_array($bounds) || count($bounds) !== 8 || array_diff(array_keys($bounds), ['unit', 'requested_offset', 'requested_max_chars', 'offset', 'end', 'length', 'total', 'has_more']) !== []
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
            if ($delivery['payload_bytes'] < $bounds['length'] || $delivery['payload_bytes'] > 4 * $bounds['length']
                || $bounds['offset'] !== ($parameters['offset'] ?? 0)
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
            $characters += $bounds['length'];
        }
        if ($characters > min(PreparationInput::MAX_PAYLOAD_BYTES, (int) config('preparation.text_budget'))
            || $bytes > min(PreparationInput::MAX_PAYLOAD_BYTES, (int) config('preparation.delivery_bytes'))) {
            throw new DomainException('Preparation exceeds its combined payload budget; do not truncate silently.');
        }
    }
}
