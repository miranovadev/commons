<?php

namespace App\Exceptions;

use DomainException;

final class ArtifactReadingException extends DomainException
{
    public function __construct(public readonly string $classification, string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
