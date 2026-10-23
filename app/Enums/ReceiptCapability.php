<?php

namespace App\Enums;

enum ReceiptCapability: string
{
    case Readable = 'readable';
    case Replayable = 'replayable';

    /** Capabilities are independent; unknown/archival Instruments grant neither. */
    public static function forReceipt(string $kind): array
    {
        return match ($kind) {
            'citation', 'tragedy', 'proposition_search', 'preparation' => [self::Readable->value],
            'load_file' => [self::Replayable->value],
            'hyperlink', 'web_search' => [self::Readable->value, self::Replayable->value],
            default => [],
        };
    }
}
