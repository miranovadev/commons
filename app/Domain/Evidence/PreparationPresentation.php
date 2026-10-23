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
        $ids = [];
        foreach ($deliveries as $delivery) {
            $fields = ['request_id', 'artifact_id', 'source_type', 'read_type', 'parameters', 'method', 'version',
                'representation', 'delivery_method', 'payload_hash', 'payload_bytes', 'delivery_id', 'boundaries'];
            if (! is_array($delivery) || array_diff(array_keys($delivery), $fields) !== [] || count($delivery) !== count($fields)
                || ! is_string($delivery['artifact_id'] ?? null) || ! preg_match('/^sha256:[a-f0-9]{64}$/D', $delivery['artifact_id'])
                || ! is_string($delivery['payload_hash'] ?? null) || ! preg_match('/^sha256:[a-f0-9]{64}$/D', $delivery['payload_hash'])
                || ! is_int($delivery['payload_bytes'] ?? null) || $delivery['payload_bytes'] < 0
                || $delivery['payload_bytes'] > (int) config('preparation.selection_bytes')) {
                throw new DomainException('Delivery must identify the exact bounded representation.');
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
                || ! is_array($bounds) || count($bounds) !== 5 || array_diff(array_keys($bounds), ['unit', 'offset', 'end', 'total', 'has_more']) !== []
                || ($bounds['unit'] ?? null) !== 'unicode-characters' || ! is_int($bounds['offset'] ?? null)
                || ! is_int($bounds['end'] ?? null) || ! is_int($bounds['total'] ?? null)
                || $bounds['offset'] < 0 || $bounds['end'] < $bounds['offset'] || $bounds['total'] < $bounds['end']
                || ($bounds['has_more'] ?? null) !== ($bounds['end'] < $bounds['total'])
                || $delivery['request_id'] !== PreparationInput::requestId($delivery)) {
                throw new DomainException('Delivery must preserve its exact requested selection and boundaries.');
            }
            if (isset($ids[$delivery['request_id']])) {
                throw new DomainException('Deliver each identical representation once, retaining all Receipt associations.');
            }
            $ids[$delivery['request_id']] = true;
            $bytes += $delivery['payload_bytes'];
        }
        if ($bytes > min(PreparationInput::MAX_PAYLOAD_BYTES, (int) config('preparation.delivery_bytes'))) {
            throw new DomainException('Preparation exceeds its combined payload budget; do not truncate silently.');
        }
    }
}
