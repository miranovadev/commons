<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class SubstratePresentation
{
    public const COLORS = ['dark', 'gray', 'red', 'pink', 'grape', 'violet', 'indigo', 'blue', 'cyan', 'teal', 'green', 'lime', 'yellow', 'orange'];

    public const DEFAULTS = [
        'primary_color' => 'indigo',
        'positive' => ['label' => 'Support', 'color' => 'violet'],
        'negative' => ['label' => 'Refute', 'color' => 'blue'],
    ];

    public static function resolve(?array $configuration): array
    {
        return array_replace_recursive(self::DEFAULTS, $configuration['presentation'] ?? []);
    }

    public static function rules(): array
    {
        $prefix = 'configuration.presentation';

        return [
            $prefix => ['sometimes', 'array:primary_color,positive,negative'],
            "$prefix.primary_color" => ['sometimes', 'required', Rule::in(self::COLORS)],
            "$prefix.positive" => ['sometimes', 'array:label,color'],
            "$prefix.negative" => ['sometimes', 'array:label,color'],
            "$prefix.positive.label" => ['sometimes', 'required', 'string', 'max:40'],
            "$prefix.negative.label" => ['sometimes', 'required', 'string', 'max:40'],
            "$prefix.positive.color" => ['sometimes', 'required', Rule::in(self::COLORS)],
            "$prefix.negative.color" => ['sometimes', 'required', Rule::in(self::COLORS)],
        ];
    }
}
