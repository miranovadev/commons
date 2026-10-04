<?php

namespace App\Exceptions;

use DomainException;

final class DeferredChiralityCadenceBoundary extends DomainException
{
    public function __construct(public readonly array $intervals, public readonly ?string $propositionId = null)
    {
        parent::__construct('Stage 1 Attestation Cadence has undefined mature zero-mean CV; Stage 2 resolution is deferred. Proposition: '.($propositionId ?? 'calculator').'; intervals: '.json_encode($intervals));
    }
}
