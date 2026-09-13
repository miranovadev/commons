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
    'description' => 'Cite (citation): an existing Proposition or Attestation ID in this substrate. Creating a Cite Receipt does not itself grant Reinforcement. Hyperlink: the server fetches a public HTTP(S) resource and verifies retrieval, not the truth of its claims. Verification produces evidence, not active Reinforcement. Use receipt.read when offered to load qualifying evidence into persistent Participant context.',
    'type' => 'object',
    'additionalProperties' => false,
    'required' => ['kind', 'reference'],
    'properties' => [
        'kind' => [
            'type' => 'string',
            'enum' => ['citation', 'hyperlink'],
        ],
        'reference' => [
            'type' => 'string',
            'minLength' => 1,
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
    'version' => 17,

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
        'receipt.read' => [
            'title' => 'Load encountered Receipt evidence into persistent context',
            'description' => 'Load one qualifying Receipt. This leaves the Phase and Gate unchanged. Loaded Receipts persist across Cycles; repeated or equivalent evidence does not multiply Reinforcement.',
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['action', 'receipt_id'],
            'properties' => ['action' => ['const' => 'receipt.read'], 'receipt_id' => $ulid],
        ],
        'proposition.traverse' => [
            'title' => 'Traverse an attested immediate Write descendant',
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
        'proposition.reclassify' => $proposition(
            'proposition.reclassify',
            'Reclassify the current gate',
        ),
        'proposition.bias' => [
            'title' => 'Select one dominant-direction Gate into fresh Concord/Canon',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action', 'proposition_id'],
            'properties' => [
                'action' => ['const' => 'proposition.bias'],
                'proposition_id' => $ulid,
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
