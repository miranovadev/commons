<?php

return [
    1 => [
        'name' => 'distinguish',
        'description' => 'Create or select the first expressive distinction from the current committal Gate.',
        'gate' => 'participant-position',
        'actions' => [
            'proposition.variant',
            'proposition.traverse',
            'participant.rest',
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
            'proposition.traverse',
            'participant.rest',
        ],
        'constraints' => [
            'proposition.reclassify' => ['gate-owner'],
        ],
    ],
    3 => [
        'name' => 'constrain',
        'description' => 'Strengthen or classify the selected Proposition. Grounding is a separate Instrument action.',
        'gate' => 'previous-phase-selection',
        'actions' => [
            'proposition.revise',
            'proposition.reclassify',
            'attestation.create',
            'proposition.traverse',
            'participant.rest',
        ],
        'constraints' => [
            'proposition.reclassify' => [
                'gate-owner',
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
            'proposition.traverse',
            'participant.rest',
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
            'proposition.traverse',
            'participant.rest',
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
            'proposition.traverse',
            'participant.rest',
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
        'description' => 'Commit the grounded expressive Proposition when Depth and Cadence have aligned hysteretic directions.',
        'gate' => 'previous-phase-selection',
        'actions' => [
            'proposition.commit',
            'proposition.traverse',
            'participant.rest',
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
