<?php

namespace App\Enums;

enum ReceiptCapability: string
{
    case Readable = 'readable';
    case Replayable = 'replayable';

    /** Capabilities are independent; unknown/archival Instruments grant neither. */
    public static function forInstrument(string $kind): array
    {
        return match ($kind) {
            'citation', 'tragedy' => [self::Readable->value],
            'hyperlink', 'web_search' => [self::Readable->value, self::Replayable->value],
            default => [],
        };
    }
}
