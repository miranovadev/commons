<?php

return [
    1 => [
        'name' => 'distinguish',
        'gate' => 'highest-potential-charge',
        'write_registers' => [
            'emergence' => 'activity',
            'settlement' => 'signal',
        ],
        'actions' => [
            'proposition.variant',
            'phase.rest',
        ],
    ],
    2 => [
        'name' => 'persist',
        'gate' => 'previous-phase-selection',
        'write_registers' => [
            'emergence' => 'frontier',
            'settlement' => 'stance',
        ],
        'actions' => [
            'proposition.variant',
            'proposition.revise',
            'proposition.reclassify',
            'attestation.create',
            'phase.rest',
        ],
    ],
    3 => [
        'name' => 'constrain',
        'gate' => 'previous-phase-selection',
        'write_registers' => [
            'emergence' => 'canon',
            'settlement' => 'concord',
        ],
        'actions' => [
            'proposition.revise',
            'proposition.reclassify_with_receipt',
            'phase.rest',
        ],
        'constraints' => [
            'reclassify' => 'receipt-required',
        ],
    ],
    4 => [
        'name' => 'disperse',
        'gate' => 'previous-phase-selection',
        'write_registers' => [
            'emergence' => 'activity',
            'settlement' => 'signal',
        ],
        'actions' => [
            'proposition.variant',
            'receipt.create',
            'phase.rest',
        ],
        'constraints' => [
            'receipt' => 'write-register',
        ],
    ],
    5 => [
        'name' => 'mediate',
        'gate' => 'previous-phase-selection',
        'write_registers' => [
            'emergence' => 'frontier',
            'settlement' => 'stance',
        ],
        'actions' => [
            'proposition.reconcile',
            'phase.rest',
        ],
        'constraints' => [
            'reconcile' => 'homeostatically-admissible-counterpart',
        ],
    ],
    6 => [
        'name' => 'bias',
        'gate' => 'previous-phase-selection',
        'write_registers' => [
            'emergence' => 'canon',
            'settlement' => 'concord',
        ],
        'actions' => [
            'proposition.reconcile',
            'receipt.create',
            'phase.rest',
        ],
        'constraints' => [
            'reconcile' => 'homeostatically-admissible-counterpart',
            'receipt' => 'write-register',
        ],
    ],
    7 => [
        'name' => 'order',
        'gate' => 'previous-phase-selection',
        'write_registers' => [],
        'actions' => [
            'proposition.commit',
        ],
        'constraints' => [
            'commit' => 'depth-cadence-and-hysteresis-admissible',
        ],
    ],
];
