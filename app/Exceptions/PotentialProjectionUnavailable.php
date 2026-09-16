<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PotentialProjectionUnavailable extends ConflictHttpException
{
    public function __construct(public readonly string $substrateId)
    {
        parent::__construct('The active field projection is pending maintenance. Run commons:potential-rebuild '.$substrateId.'.');
    }
}
