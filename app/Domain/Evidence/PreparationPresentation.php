<?php

namespace App\Domain\Evidence;

use DomainException;

/** Trusted delivery acknowledgement from a reader/interaction adapter, never Participant input. */
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
            if (! is_array($delivery) || array_diff(array_keys($delivery), [
                'artifact_id', 'method', 'version', 'representation', 'payload_hash', 'payload_bytes', 'delivery_id',
            ]) !== [] || count($delivery) !== 7
                || ! is_string($delivery['artifact_id'] ?? null) || ! preg_match('/^sha256:[a-f0-9]{64}$/D', $delivery['artifact_id'])
                || ! is_string($delivery['payload_hash'] ?? null) || ! preg_match('/^sha256:[a-f0-9]{64}$/D', $delivery['payload_hash'])
                || ! is_int($delivery['payload_bytes'] ?? null) || $delivery['payload_bytes'] < 1) {
                throw new DomainException('Delivery must identify the Artifact and exact supplied representation.');
            }
            foreach (['method', 'version', 'representation', 'delivery_id'] as $field) {
                if (! is_string($delivery[$field]) || trim($delivery[$field]) === '' || mb_strlen($delivery[$field]) > 160
                    || str_contains($delivery[$field], "\0")) {
                    throw new DomainException('Delivery identifiers must be bounded and non-empty.');
                }
            }
            if (isset($ids[$delivery['artifact_id']])) {
                throw new DomainException('Deliver each unique Artifact once, retaining all selected Receipt associations.');
            }
            $ids[$delivery['artifact_id']] = true;
            $bytes += $delivery['payload_bytes'];
        }
        if ($bytes > PreparationInput::MAX_PAYLOAD_BYTES) {
            throw new DomainException('Preparation exceeds its combined cognitive payload budget; do not truncate silently.');
        }
    }
}
