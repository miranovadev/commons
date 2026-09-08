<?php

return [
    1 => [
        'name' => 'distinguish',
        'description' => 'Create or select the first expressive distinction from the current committal Gate.',
        'gate' => 'participant-position',
        'actions' => [
            'proposition.variant',
            'proposition.select',
        ],
    ],
    2 => [
        'name' => 'persist',
        'description' => 'Develop the selected distinction through variation, revision, reclassification, or attestation.',
        'gate' => 'previous-phase-selection',
        'actions' => [
            'proposition.variant',
            'proposition.revise',
            'proposition.reclassify',
            'attestation.create',
            'proposition.select',
        ],
        'constraints' => [
            'proposition.reclassify' => ['gate-owner'],
        ],
    ],
    3 => [
        'name' => 'constrain',
        'description' => 'Strengthen or classify the selected Proposition, using an instrument when entering the committal register.',
        'gate' => 'previous-phase-selection',
        'actions' => [
            'proposition.revise',
            'proposition.reclassify_with_instrument',
            'attestation.create',
            'proposition.select',
        ],
        'constraints' => [
            'proposition.reclassify_with_instrument' => [
                'gate-owner',
                'instrument-required',
            ],
        ],
    ],
    4 => [
        'name' => 'disperse',
        'description' => 'Open a new expressive branch, execute an instrument, or select an existing active head.',
        'gate' => 'previous-phase-selection',
        'actions' => [
            'proposition.variant',
            'instrument.execute',
            'proposition.select',
        ],
        'constraints' => [
            'instrument.execute' => ['write-register'],
        ],
    ],
    5 => [
        'name' => 'mediate',
        'description' => 'Reconcile the current Gate with one admissible counterpart, or select an existing mediated Proposition.',
        'gate' => 'previous-phase-selection',
        'actions' => [
            'proposition.reconcile',
            'proposition.select',
        ],
        'constraints' => [
            'proposition.reconcile' => [
                'homeostatically-admissible-counterpart',
            ],
        ],
    ],
    6 => [
        'name' => 'bias',
        'description' => 'Reconcile the current Gate, execute an instrument, or select an existing Proposition in the write register.',
        'gate' => 'previous-phase-selection',
        'actions' => [
            'proposition.reconcile',
            'instrument.execute',
            'proposition.select',
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
        'description' => 'Commit the resulting expressive Proposition when the Closure gates permit it.',
        'gate' => 'previous-phase-selection',
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
