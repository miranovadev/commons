<?php

namespace App\Domain\Evidence;

/** Request-local evidence gathered before entering the contribution transaction. */
final readonly class InstrumentObservation
{
    public function __construct(
        public array $input,
        public array $metadata = [],
        public ?VerifiedHyperlink $verification = null,
        public ?SearchObservation $search = null,
        public ?PropositionCollection $propositions = null,
    ) {}
}
