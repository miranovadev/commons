<?php

return [
    1 => [
        'name' => 'distinguish',
        'actions' => [
            'proposition.variant',
        ],
    ],

    // These phases remain deliberately unconfigured until their write
    // constraints have been settled.
    2 => [
        'name' => 'persist',
        'actions' => [],
    ],
    3 => [
        'name' => 'constrain',
        'actions' => [],
    ],
    4 => [
        'name' => 'disperse',
        'actions' => [],
    ],

    5 => [
        'name' => 'mediate',
        'actions' => [
            'proposition.reconcile',
            'proposition.revise',
            'proposition.reclassify',
        ],
        'constraints' => [
            'reconcile' => 'touched-cycle',
            'revise' => 'owner',
            'reclassify' => 'owner',
        ],
    ],
    6 => [
        'name' => 'bias',
        'actions' => [
            'attestation.create',
            'receipt.create',
        ],
    ],
    7 => [
        'name' => 'order',
        'actions' => [
            'proposition.commit',
        ],
    ],
];
