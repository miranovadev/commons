<?php

$ulid = [
    'type' => 'string',
    'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$',
];

$proposition = static function (string $action, string $supersession) use ($ulid): array {
    return [
        'title' => ucfirst($supersession).' proposition',
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['action', 'source_proposition_id', 'statement'],
        'properties' => [
            'action' => ['const' => $action],
            'source_proposition_id' => $ulid,
            'statement' => [
                'type' => 'string',
                'minLength' => 1,
                'maxLength' => 20000,
            ],
        ],
    ];
};

return [
    'version' => 1,

    'cache' => [
        'store' => env('COMMONS_SCHEMA_CACHE_STORE'),
        'ttl' => env('COMMONS_SCHEMA_CACHE_TTL'),
    ],

    '$defs' => [
        'ulid' => $ulid,
    ],

    'actions' => [
        'proposition.variant' => $proposition('proposition.variant', 'variant'),
        'proposition.revise' => $proposition('proposition.revise', 'revised'),
        'proposition.reclassify' => [
            ...$proposition('proposition.reclassify', 'reclassified'),
            'properties' => [
                ...$proposition('proposition.reclassify', 'reclassified')['properties'],
                'register' => [
                    'type' => 'string',
                    'enum' => [
                        'activity',
                        'frontier',
                        'canon',
                        'signal',
                        'stance',
                        'concord',
                    ],
                ],
            ],
            'required' => [
                'action',
                'source_proposition_id',
                'register',
                'statement',
            ],
        ],
        'proposition.reconcile' => [
            'title' => 'Reconcile propositions',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action', 'source_proposition_ids', 'statement'],
            'properties' => [
                'action' => ['const' => 'proposition.reconcile'],
                'source_proposition_ids' => [
                    'type' => 'array',
                    'items' => $ulid,
                    'minItems' => 2,
                    'maxItems' => 2,
                    'uniqueItems' => true,
                ],
                'statement' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'maxLength' => 20000,
                ],
            ],
        ],
        'proposition.commit' => [
            'title' => 'Commit proposition',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['action', 'source_proposition_id'],
            'properties' => [
                'action' => ['const' => 'proposition.commit'],
                'source_proposition_id' => $ulid,
            ],
        ],
        'attestation.create' => [
            'title' => 'Create attestation',
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
                'rationale' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'maxLength' => 20000,
                ],
            ],
        ],
        'receipt.create' => [
            'title' => 'Create receipt',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'action',
                'receiptable_type',
                'receiptable_id',
                'type',
                'reference',
            ],
            'properties' => [
                'action' => ['const' => 'receipt.create'],
                'receiptable_type' => [
                    'type' => 'string',
                    'enum' => ['proposition', 'attestation'],
                ],
                'receiptable_id' => $ulid,
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
        ],
    ],
];
