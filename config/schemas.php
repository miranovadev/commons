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

$receipt = [
    'type' => 'object',
    'additionalProperties' => false,
    'required' => ['type', 'reference'],
    'properties' => [
        'type' => [
            'type' => 'string',
            'enum' => ['citation', 'artefact', 'hyperlink'],
        ],
        'reference' => [
            'type' => 'string',
            'minLength' => 1,
        ],
        'signature' => [
            'type' => 'string',
            'minLength' => 1,
        ],
        'metadata' => [
            'type' => 'object',
            'additionalProperties' => true,
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
    'version' => 2,

    'cache' => [
        'store' => env('COMMONS_SCHEMA_CACHE_STORE'),
        'ttl' => env('COMMONS_SCHEMA_CACHE_TTL'),
    ],

    '$defs' => [
        'ulid' => $ulid,
        'receipt' => $receipt,
    ],

    'actions' => [
        'phase.rest' => [
            'title' => 'Rest on an existing proposition',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action', 'proposition_id'],
            'properties' => [
                'action' => ['const' => 'phase.rest'],
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
        'proposition.reclassify_with_receipt' => [
            ...$proposition(
                'proposition.reclassify',
                'Reclassify the current gate with a receipt',
            ),
            'required' => ['action', 'statement', 'receipt'],
            'properties' => [
                ...$proposition(
                    'proposition.reclassify',
                    'Reclassify the current gate with a receipt',
                )['properties'],
                'receipt' => ['$ref' => '#/$defs/receipt'],
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
        'receipt.create' => [
            'title' => 'Attach a receipt to a proposition',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action', 'proposition_id', 'receipt'],
            'properties' => [
                'action' => ['const' => 'receipt.create'],
                'proposition_id' => $ulid,
                'receipt' => ['$ref' => '#/$defs/receipt'],
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
