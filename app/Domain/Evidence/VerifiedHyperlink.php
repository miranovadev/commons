<?php

namespace App\Domain\Evidence;

use Carbon\CarbonImmutable;

/** Produced by the verifier, never hydrated from participant input or Receipt metadata. */
final readonly class VerifiedHyperlink
{
    public function __construct(
        public string $resource,
        public string $fingerprint,
        public CarbonImmutable $observedAt,
        public ?CarbonImmutable $lastModified = null,
        public ?string $contentExcerpt = null,
        public bool $excerptTruncated = false,
        public array $artifacts = [],
        public array $description = [],
    ) {}

    public function metadata(): array
    {
        return [
            'validation' => 'http-content-v1', 'content_verified' => true,
            'resource' => $this->resource, 'fingerprint' => $this->fingerprint,
            'observed_at' => $this->observedAt->toIso8601String(),
            'last_modified' => $this->lastModified?->toIso8601String(),
            'version_clock' => 'server-observed',
            'content_excerpt' => $this->contentExcerpt, 'excerpt_truncated' => $this->excerptTruncated,
            ...$this->description,
            'artifacts' => $this->artifacts,
        ];
    }
}
