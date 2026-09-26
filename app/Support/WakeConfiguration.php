<?php

namespace App\Support;

use App\Enums\WakeOffsetMode;
use App\Enums\WakeTermination;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class WakeConfiguration
{
    public const DEFAULT_TIMEOUT_SECONDS = 600;

    public const MAX_TIMEOUT_SECONDS = 1800;

    public static function resolve(?array $configuration): array
    {
        return array_replace_recursive([
            'trigger' => [
                'mode' => 'manual',
                'offset' => ['mode' => 'seconds', 'seconds' => 0, 'random_from' => 0, 'random_to' => 60],
            ],
            'wake' => ['termination' => 'phase', 'timeout_seconds' => self::DEFAULT_TIMEOUT_SECONDS],
        ], $configuration ?? []);
    }

    public static function rules(string $prefix = 'configuration'): array
    {
        return [
            "$prefix.wake" => ['sometimes', 'array'],
            "$prefix.wake.termination" => ['sometimes', Rule::enum(WakeTermination::class)],
            "$prefix.wake.timeout_seconds" => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_TIMEOUT_SECONDS],
            "$prefix.trigger.offset" => ['sometimes', 'array'],
            "$prefix.trigger.offset.mode" => ['sometimes', Rule::enum(WakeOffsetMode::class)],
            "$prefix.trigger.offset.seconds" => ['sometimes', 'integer', 'between:0,60'],
            "$prefix.trigger.offset.random_from" => ['sometimes', 'integer', 'between:0,60'],
            "$prefix.trigger.offset.random_to" => ['sometimes', 'integer', 'between:0,60'],
        ];
    }

    /** Validate the effective range, including defaults and the persisted half of a PATCH. */
    public static function validateRange(Validator $validator, array $configuration, string $prefix = 'configuration'): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }
        $offset = self::resolve($configuration)['trigger']['offset'];
        if ($offset['mode'] === 'random' && $offset['random_from'] > $offset['random_to']) {
            $validator->errors()->add("$prefix.trigger.offset.random_to", 'To must be greater than or equal to From.');
        }
    }
}
