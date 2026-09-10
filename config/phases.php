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
        'description' => 'Open a new expressive branch, execute an instrument, or attest an immediate Write descendant.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'proposition.variant',
            'instrument.execute',
            'attestation.create',
        ],
        'constraints' => [
            'instrument.execute' => ['write-register'],
        ],
    ],
    5 => [
        'name' => 'mediate',
        'description' => 'Read the previous Disperse Gate and its Correspondence-aligned siblings; reconcile their immediate Gate children, or attest an immediate Write descendant.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'proposition.reconcile',
            'attestation.create',
        ],
        'constraints' => [
            'proposition.reconcile' => [
                'immediate-child-of-correspondence-read',
            ],
        ],
    ],
    6 => [
        'name' => 'bias',
        'description' => 'Reconcile a same-parent sibling matching the lineage’s dominant aggregate lean, execute an instrument, or attest an immediate Write descendant.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'proposition.reconcile',
            'instrument.execute',
            'attestation.create',
        ],
        'constraints' => [
            'proposition.reconcile' => [
                'same-parent-gate-sibling',
                'dominant-active-lineage-lean',
            ],
            'instrument.execute' => ['write-register'],
        ],
    ],
    7 => [
        'name' => 'order',
        'description' => 'Commit when grounding and Depth/Cadence allow, or attest an immediate Write descendant.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'proposition.commit',
            'attestation.create',
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
