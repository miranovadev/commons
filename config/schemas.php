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
    'description' => 'Cite references a known substrate object. Creating a Cite Receipt does not itself grant Reinforcement. Hyperlink fetches a known public URL and verifies retrieval, not the truth of its claims. Web Search discovers resources from a query. Instrument execution records an immutable Receipt; it never loads context or grants Reinforcement automatically.',
    'anyOf' => [
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
    ],
];

$proposition = static function (string $action, string $title) use ($statement): array {
    return [
        'title' => $title,
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
    'version' => 24,

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
        'proposition.traverse' => [
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
            'title' => 'Rest in a Tragic Region and end this Cycle',
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
            'title' => 'Execute an instrument against a proposition',
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


