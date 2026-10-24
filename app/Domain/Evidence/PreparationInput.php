<?php

namespace App\Domain\Evidence;

use DomainException;

/** Authored purpose and exact Receipt/Artifact pairs, without content or authority claims. */
final readonly class PreparationInput
{
    public const MAX_SELECTIONS = 8;

    public const MAX_PAYLOAD_BYTES = 1048576;

    private function __construct(public string $purpose, public array $artifacts) {}

    public static function from(array $input): self
    {
        if (array_diff(array_keys($input), ['purpose', 'artifacts']) !== []
            || ! is_string($input['purpose'] ?? null) || trim($input['purpose']) === ''
            || mb_strlen($input['purpose']) > 240 || str_contains($input['purpose'], "\0")
            || ! is_array($input['artifacts'] ?? null) || ! array_is_list($input['artifacts'])
            || count($input['artifacts']) < 1 || count($input['artifacts']) > self::MAX_SELECTIONS) {
            throw new DomainException('Preparation requires a purpose of 1–240 characters and 1–8 Artifact selections.');
        }
        $seen = [];
        foreach ($input['artifacts'] as $selection) {
            if (! is_array($selection) || array_diff(array_keys($selection), ['receipt_id', 'artifact_id', 'read_type', 'parameters']) !== []
                || ! is_string($selection['receipt_id'] ?? null)
                || ! preg_match('/^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$/D', $selection['receipt_id'])
                || ! is_string($selection['artifact_id'] ?? null)
                || ! preg_match('/^sha256:[a-f0-9]{64}$/D', $selection['artifact_id'])
                || ! is_string($selection['read_type'] ?? null) || ! preg_match('/^[a-z][a-z_]{0,31}$/D', $selection['read_type'])
                || ! is_array($selection['parameters'] ?? [])
                || array_diff(array_keys($selection['parameters'] ?? []), ['offset', 'max_chars']) !== []
                || collect($selection['parameters'] ?? [])->contains(fn ($value) => ! is_int($value) || $value < 0)
                || (isset($selection['parameters']['max_chars']) && $selection['parameters']['max_chars'] < 1)) {
                throw new DomainException('Each selection requires a Receipt ID and canonical Artifact hash.');
            }
            $key = $selection['receipt_id'].':'.self::requestId($selection);
            if (isset($seen[$key])) {
                throw new DomainException('Duplicate Receipt/Artifact selections are not allowed.');
            }
            $seen[$key] = true;
        }

        return new self($input['purpose'], $input['artifacts']);
    }

    /** Equal representations share delivery, while every Receipt association survives. */
    public static function requestId(array $selection): string
    {
        $parameters = $selection['parameters'] ?? [];
        $parameters['offset'] ??= 0;
        ksort($parameters);

        return hash('sha256', json_encode([$selection['artifact_id'], $selection['read_type'], $parameters], JSON_THROW_ON_ERROR));
    }

    public function payload(): array
    {
        return ['purpose' => $this->purpose, 'artifacts' => $this->artifacts];
    }
}
