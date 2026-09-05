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
        'constraints' => [
            'proposition.revise' => ['gate-owner'],
            'proposition.reclassify' => ['gate-owner'],
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
            'attestation.create',
            'phase.rest',
        ],
        'constraints' => [
            'proposition.revise' => ['gate-owner'],
            'proposition.reclassify_with_receipt' => [
                'gate-owner',
                'receipt-required',
            ],
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
            'receipt.create' => ['write-register'],
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
            'proposition.reconcile' => [
                'homeostatically-admissible-counterpart',
            ],
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
            'proposition.reconcile' => [
                'homeostatically-admissible-counterpart',
            ],
            'receipt.create' => ['write-register'],
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
            'proposition.commit' => [
                'depth-cadence-and-hysteresis-admissible',
            ],
        ],
    ],
];
