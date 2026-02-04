<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proposition - Create
    |--------------------------------------------------------------------------
    |
    |
    */
    'proposition' => [
        'create' => [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$id'     => 'https://commons.online/schemas/proposition.create.schema.json',

            'title' => 'Proposition Create',
            'type'  => 'object',

            // Reject unknown keys (tight input discipline)
            'additionalProperties' => false,

            // Required DB fields
            'required' => [
                'register',
                'tier',
                'visibility',
                'statement',
            ],

            'properties' => [

                // Optional: client may supply ULID, otherwise server generates one
                'id' => [
                    'description' => 'Optional client-supplied ULID. If omitted, the server generates one.',
                    'type'        => 'string',
                    'pattern'     => '^[0-9A-HJKMNP-TV-Z]{26}$',
                ],

                // Miranova DoF register
                'register' => [
                    'type' => 'string',
                    'enum' => [
                        'coherence',
                        'entropy',
                        'activation',
                        'salience',
                        'excitation',
                        'modality',
                        'stimulus',
                        'valence',
                        'resonance',
                    ],
                ],

                // Expressive vs Committal tier
                'tier' => [
                    'type' => 'string',
                    'enum' => [
                        'expressive',
                        'committal',
                    ],
                ],

                // Internal vs External visibility
                'visibility' => [
                    'type' => 'string',
                    'enum' => [
                        'internal',
                        'external',
                    ],
                ],

                // Core proposition statement
                'statement' => [
                    'description' => 'The human-readable proposition statement.',
                    'type'        => 'string',
                    'minLength'   => 1,
                    'maxLength'   => 20000,
                ],

                // Optional metadata payload (future-proof hook)
                // 'meta' => [
                //     'description' => 'Optional structured metadata for future use. Keep small; server may impose limits.',
                //     'type'        => ['object', 'null'],
                //     'default'     => null,

                //     // Allow arbitrary metadata keys for now
                //     'additionalProperties' => true,
                // ],
            ],

            // Required properties
            'required' => [
                'register',
                'tier',
                'visibility',
                'statement',
            ],

            // Reusable ULID definition (future schema composition)
            '$defs' => [
                'ulid' => [
                    'type'    => 'string',
                    'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$',
                ],
            ],

            // Example payloads
            'examples' => [
                [
                    'register'   => 'entropy',
                    'tier'       => 'expressive',
                    'visibility' => 'internal',
                    'statement'  => 'Should the Commons require a receipt for any external committal claim?',
                ],
                [
                    'register'   => 'coherence',
                    'tier'       => 'committal',
                    'visibility' => 'external',
                    'statement'    => 'No external commitment without a Receipt.',
                    // 'meta'       => [
                    //     'seed'   => true,
                    //     'source' => 'constitution',
                    // ],
                ],
            ],
        ],
    ]

];
