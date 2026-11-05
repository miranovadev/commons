<?php

use App\Models\Proposition;
use App\Services\Evidence\InstrumentInvocation;

$ulid = [
    'type' => 'string',
    'pattern' => '^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$',
];

$statement = [
    'type' => 'string',
    'minLength' => 1,
    'maxLength' => Proposition::MAX_STATEMENT_LENGTH,
];

$instrument = [
    'description' => InstrumentInvocation::CITATION_GUIDANCE.' A hyperlink retrieves a public URL; web_search finds external resources; proposition_search observes bounded historical lineage, including meaningful empty results. load_file with method download preserves a public HTTP(S) resource as an immutable Artifact. All produce ordinary Receipts. Load File Receipts contain acquisition metadata and are not loadable; other readable Instrument Receipts load for their creator. Loading does not grant action permission.',
    'anyOf' => [
        [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['kind', 'method', 'reference'],
            'properties' => [
                'kind' => ['const' => 'load_file'], 'method' => ['const' => 'download'],
                'reference' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 4096, 'pattern' => '^https?://'],
            ],
        ],
        [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['kind', 'reference'],
            'properties' => [
                'kind' => ['const' => 'citation'],
                'reference' => ['type' => 'string', 'pattern' => InstrumentInvocation::CITATION_PATTERN,
                    'minLength' => 26, 'maxLength' => 26, 'description' => InstrumentInvocation::CITATION_GUIDANCE],
            ],
        ],
        [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['kind', 'reference'],
            'properties' => [
                'kind' => ['const' => 'hyperlink'],
                'reference' => ['type' => 'string', 'minLength' => 1],
            ],
        ],
        [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['kind', 'query'],
            'properties' => [
                'kind' => ['const' => 'web_search'],
                'query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 1000, 'pattern' => '^[^\\x00]*[^\\s\\x00][^\\x00]*$'],
            ],
        ],
        [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['kind'],
            'properties' => [
                'kind' => ['const' => 'proposition_search'],
                'query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 1000, 'pattern' => '^[^\\x00]*[^\\s\\x00][^\\x00]*$'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 5],
            ],
        ],
    ],
];

$proposition = static function (string $action, string $title) use ($statement): array {
    return [
        'title' => $title,
        'description' => 'Create a new proposition from the current Gate.',
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['action', 'statement'],
        'properties' => [
            'action' => ['const' => $action],
            'statement' => $statement,
        ],
    ];
};

return [
    'version' => 37,

    'cache' => [
        'store' => env('COMMONS_SCHEMA_CACHE_STORE'),
        'ttl' => env('COMMONS_SCHEMA_CACHE_TTL'),
    ],

    'options' => [
        'limit' => (int) env('COMMONS_INPUT_OPTION_LIMIT', 10),
    ],

    '$defs' => [
        'ulid' => $ulid,
        'instrument' => $instrument,
    ],

    'actions' => [
        'preparation.start' => [
            'description' => 'Read selected preserved Artifacts for a stated purpose, then contribute or explicitly defer in the same Disperse Phase.',
            'title' => 'Prepare Artifacts', 'type' => 'object', 'additionalProperties' => false,
            'required' => ['action', 'proposition_id', 'purpose', 'artifacts'],
            'properties' => [
                'action' => ['const' => 'preparation.start'], 'proposition_id' => $ulid,
                'purpose' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 240],
                'artifacts' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 8, 'uniqueItems' => true,
                    'items' => ['type' => 'object']],
            ],
        ],
        'acquisition.defer' => [
            'description' => 'Complete this Preparation without a Proposition, recording your reason and completing the current Cycle.',
            'title' => 'Defer this attempt', 'type' => 'object', 'additionalProperties' => false,
            'required' => ['action', 'reason'],
            'properties' => ['action' => ['const' => 'acquisition.defer'],
                'reason' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 400]],
        ],
        'preparation.defer' => [
            'description' => 'Complete this Preparation without a Proposition, recording your reason and completing the current Cycle.',
            'title' => 'Defer this attempt', 'type' => 'object', 'additionalProperties' => false,
            'required' => ['action', 'reason'],
            'properties' => ['action' => ['const' => 'preparation.defer'],
                'reason' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 400]],
        ],
        'proposition.traverse' => [
            'description' => 'Move to an available continuation.',
            'title' => 'Continue through an admissible Write',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action', 'proposition_id'],
            'properties' => [
                'action' => ['const' => 'proposition.traverse'],
                'proposition_id' => $ulid,
            ],
        ],
        'participant.rest' => [
            'description' => 'End this Cycle and allow recovery.',
            'title' => 'Rest',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action'],
            'properties' => ['action' => ['const' => 'participant.rest']],
        ],
        'proposition.variant' => $proposition(
            'proposition.variant',
            'Create a variant from the current gate',
        ),
        'proposition.revise' => [
            'description' => 'Replace the selected proposition with a revised statement.',
            'title' => 'Revise a proposition in the current write register',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action', 'proposition_id', 'statement'],
            'properties' => [
                'action' => ['const' => 'proposition.revise'],
                'proposition_id' => $ulid,
                'statement' => $statement,
            ],
        ],
        'proposition.reclassify' => [
            'description' => 'Select one eligible Receipt and interpret the source using that evidence and causal context. You may keep the source wording if the evidence supports it.',
            'type' => 'object',
            'title' => 'Reclassify an eligible Proposition',
            'additionalProperties' => false,
            'required' => ['action', 'proposition_id', 'receipt_id', 'statement'],
            'properties' => [
                'action' => ['const' => 'proposition.reclassify'],
                'proposition_id' => $ulid,
                'receipt_id' => $ulid,
                'statement' => [...$statement, 'pattern' => '\S'],
            ],
        ],
        'proposition.reconcile' => [
            'description' => 'Write your interpretation of the relationship between the current Gate and the selected counterpart. Both become causal parents. No Receipt is consumed.',
            'title' => 'Reconcile two Propositions',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action', 'other_proposition_id', 'statement'],
            'properties' => [
                'action' => ['const' => 'proposition.reconcile'],
                'other_proposition_id' => $ulid,
                'statement' => [...$statement, 'pattern' => '\S'],
            ],
        ],
        'attestation.create' => [
            'description' => 'Record Support or Refute for the selected proposition, with an optional rationale.',
            'title' => 'Create an attestation',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action', 'proposition_id', 'direction'],
            'properties' => [
                'action' => ['const' => 'attestation.create'],
                'proposition_id' => $ulid,
                'direction' => [
                    'type' => 'string',
                    'enum' => ['support', 'refute'],
                ],
                'rationale' => $statement,
            ],
        ],
        'instrument.execute' => [
            'description' => 'Acquire at the current Gate for this generative opportunity. Use Citation for a Proposition or Attestation ULID and Hyperlink for a public URL.',
            'title' => 'Observe the current eligible Gate',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action', 'proposition_id', 'instrument'],
            'properties' => [
                'action' => ['const' => 'instrument.execute'],
                'proposition_id' => $ulid,
                'instrument' => ['$ref' => '#/$defs/instrument'],
            ],
        ],
        'proposition.commit' => [
            'description' => 'Preserve an admissible proposition as a new canonical boundary.',
            'title' => 'Commit a Closure candidate',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action', 'proposition_id'],
            'properties' => [
                'action' => ['const' => 'proposition.commit'],
                'proposition_id' => $ulid,
            ],
        ],
    ],
];
