<?php

return [
    1 => [
        'name' => 'distinguish',
        'description' => 'Create a distinction or traverse an existing local Expressive Proposition from the current Committal Gate.',
        'gate' => 'participant-position',
        'actions' => [
            'proposition.variant',
            'attestation.create',
            'proposition.traverse',
        ],
    ],
    2 => [
        'name' => 'persist',
        'description' => 'Develop the current distinction through variation, revision, reclassification, or attestation.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'proposition.variant',
            'proposition.revise',
            'proposition.reclassify',
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
            'proposition.reclassify' => ['gate-owner'],
        ],
    ],
    3 => [
        'name' => 'constrain',
        'description' => 'Strengthen or classify the selected Proposition. Grounding is a separate Instrument action.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'proposition.revise',
            'proposition.reclassify',
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
            'proposition.reclassify' => [
                'gate-owner',
            ],
        ],
    ],
    4 => [
        'name' => 'disperse',
        'description' => 'Open a new expressive branch, execute an instrument, or traverse an existing local Expressive Proposition.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'proposition.variant',
            'instrument.execute',
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
            'instrument.execute' => ['write-register'],
        ],
    ],
    5 => [
        'name' => 'mediate',
        'description' => 'Reconcile the current Gate with one admissible counterpart, or traverse an existing local Expressive Proposition.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'proposition.reconcile',
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
            'proposition.reconcile' => [
                'homeostatically-admissible-counterpart',
            ],
        ],
    ],
    6 => [
        'name' => 'bias',
        'description' => 'Reconcile the current Gate, execute an instrument, or traverse an existing Expressive Proposition in the Write register.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'proposition.reconcile',
            'instrument.execute',
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
            'proposition.reconcile' => [
                'homeostatically-admissible-counterpart',
            ],
            'instrument.execute' => ['write-register'],
        ],
    ],
    7 => [
        'name' => 'order',
        'description' => 'Commit when grounding and Depth/Cadence allow, or use an admissible local Expressive continuation.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'proposition.commit',
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
            'proposition.commit' => [
                'effective-gate-attestation',
                'completed-gate-or-effective-attestation-receipt',
                'depth-cadence-and-hysteresis-admissible',
            ],
        ],
    ],
];
