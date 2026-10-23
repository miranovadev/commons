<?php

namespace App\Domain\Evidence;

/** Immutable, request-local acquisition result; never contains the downloaded body. */
final readonly class DownloadObservation
{
    public function __construct(public string $sourceUrl, public array $metadata) {}

    public function successful(): bool
    {
        return ($this->metadata['content_verified'] ?? false) === true;
    }

    public function identity(): string
    {
        return hash('sha256', "load-file:download:v1\n".$this->sourceUrl."\n".
            ($this->successful()
                ? $this->metadata['effective_url']."\n".$this->metadata['artifacts'][0]['hash']
                : 'retrieval-failed'));
    }
}
