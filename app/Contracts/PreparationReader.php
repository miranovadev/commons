<?php

namespace App\Contracts;

/**
 * Stage 2D adapters register under `preparation.readers`. No production adapter
 * exists in Stage 2C. Capability checks are pure and must not perform I/O.
 * Support must reflect the reader's actual capabilities, not a MIME-header promise.
 */
interface PreparationReader
{
    public function method(): string;

    public function version(): string;

    public function supports(array $artifact, string $representation): bool;
}
