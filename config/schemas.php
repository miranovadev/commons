<?php

$ulid = [
    'type' => 'string',
    'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$',
];

$statement = [
    'type' => 'string',
    'minLength' => 1,
    'maxLength' => 20000,
];

$instrument = [
    'type' => 'object',
    'additionalProperties' => false,
    'required' => ['kind', 'reference'],
    'properties' => [
        'kind' => [
            'type' => 'string',
            'enum' => ['citation', 'artefact', 'hyperlink'],
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
    'version' => 4,

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
        'proposition.select' => [
            'title' => 'Select an existing proposition and rest this phase',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action', 'proposition_id'],
            'properties' => [
                'action' => ['const' => 'proposition.select'],
                'proposition_id' => $ulid,
            ],
        ],
        'proposition.variant' => $proposition(
            'proposition.variant',
            'Create a variant from the current gate',
        ),
        'proposition.revise' => $proposition(
            'proposition.revise',
            'Revise the current gate',
        ),
        'proposition.reclassify' => $proposition(
            'proposition.reclassify',
            'Reclassify the current gate',
        ),
        'proposition.reclassify_with_instrument' => [
            ...$proposition(
                'proposition.reclassify',
                'Reclassify the current gate using an instrument',
            ),
            'required' => ['action', 'statement', 'instrument'],
            'properties' => [
                ...$proposition(
                    'proposition.reclassify',
                    'Reclassify the current gate using an instrument',
                )['properties'],
                'instrument' => ['$ref' => '#/$defs/instrument'],
            ],
        ],
        'proposition.reconcile' => [
            'title' => 'Reconcile the current gate with another proposition',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action', 'other_proposition_id', 'statement'],
            'properties' => [
                'action' => ['const' => 'proposition.reconcile'],
                'other_proposition_id' => $ulid,
                'statement' => $statement,
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
            'title' => 'Commit the current gate',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action'],
            'properties' => [
                'action' => ['const' => 'proposition.commit'],
            ],
        ],
    ],
];
