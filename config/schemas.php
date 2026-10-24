<?php

$ulid = [
    'type' => 'string',
    'pattern' => '^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$',
];

$statement = [
    'type' => 'string',
    'minLength' => 1,
    'maxLength' => 20000,
];

$instrument = [
    'description' => 'Citation references a substrate object; hyperlink retrieves a public URL; web_search finds external resources; proposition_search observes bounded historical lineage, including meaningful empty results. load_file with method download preserves a public HTTP(S) resource as an immutable Artifact. All produce ordinary Receipts. Load File Receipts contain acquisition metadata and are not loadable; other readable Instrument Receipts load for their creator. Loading does not grant action permission.',
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
                'kind' => ['type' => 'string', 'enum' => ['citation', 'hyperlink']],
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
    'version' => 32,

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
        'preparation.defer' => [
            'description' => 'Complete this Preparation without a Proposition, recording your reason and preserving the current Phase.',
            'title' => 'Defer after Preparation', 'type' => 'object', 'additionalProperties' => false,
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
            'description' => 'Classify the selected source using the selected Receipt, preserving its statement.',
            'type' => 'object',
            'title' => 'Reclassify the Gate without changing its statement',
            'additionalProperties' => false,
            'required' => ['action', 'proposition_id', 'receipt_id'],
            'properties' => [
                'action' => ['const' => 'proposition.reclassify'],
                'proposition_id' => $ulid,
                'receipt_id' => $ulid,
            ],
        ],
        'proposition.reconcile' => [
            'description' => 'Create a result with the Gate and selected counterpart as causal parents, preserving the Gate statement.',
            'title' => 'Reconcile an admissible counterpart with the current Gate statement',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action', 'other_proposition_id'],
            'properties' => [
                'action' => ['const' => 'proposition.reconcile'],
                'other_proposition_id' => $ulid,
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
            'description' => 'Observe the current Gate in Persist or fallback Disperse once per Cycle. Record its Receipt without advancing the Phase.',
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
