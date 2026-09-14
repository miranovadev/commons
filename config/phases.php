<?php

return [
    1 => [
        'name' => 'distinguish',
        'description' => 'Create a distinction or traverse an existing local Expressive Proposition from the current Committal Gate.',
        'gate' => 'participant-position',
        'actions' => [
            'receipt.read',
            'proposition.variant',
            'instrument.execute',
            'receipt.replay',
            'attestation.create',
            'proposition.traverse',
        ],
    ],
    2 => [
        'name' => 'persist',
        'description' => 'Variant another Participant’s Gate once, reclassify a receipted and attested Gate, or interact with eligible existing Writes.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'receipt.read',
            'proposition.variant',
            'proposition.revise',
            'proposition.reclassify',
            'instrument.execute',
            'receipt.replay',
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
            'proposition.variant' => ['gate-non-owner'],
            'proposition.reclassify' => ['completed-receipt-and-effective-attestation'],
            'instrument.execute' => ['write-register'],
        ],
    ],
    3 => [
        'name' => 'constrain',
        'description' => 'Strengthen or classify the selected Proposition. Grounding is a separate Instrument action.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'receipt.read',
            'proposition.revise',
            'proposition.reclassify',
            'instrument.execute',
            'receipt.replay',
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
            'proposition.reclassify' => [
                'completed-receipt-and-effective-attestation',
            ],
        ],
    ],
    4 => [
        'name' => 'disperse',
        'description' => 'Open one expressive branch per Participant at this Gate, revise an owned Write, execute an instrument, or attest a Write descendant.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'receipt.read',
            'proposition.variant',
            'proposition.revise',
            'instrument.execute',
            'receipt.replay',
            'attestation.create',
        ],
        'constraints' => [
            'instrument.execute' => ['write-register'],
        ],
    ],
    5 => [
        'name' => 'mediate',
        'description' => 'Read Correspondence-aligned Propositions across the Commit Region; reconcile their immediate Gate children, or attest an immediate Write descendant.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'receipt.read',
            'proposition.reconcile',
            'instrument.execute',
            'receipt.replay',
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
        'description' => 'Select one active Commit Region Stance/Frontier whose retained direction agrees with the aggregate lean and hard-supersede it into fresh Concord/Canon, execute an instrument, or attest an immediate Write descendant.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'receipt.read',
            'proposition.bias',
            'instrument.execute',
            'receipt.replay',
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
            'proposition.bias' => [
                'dominant-active-stance-lean',
                'unary-selection-fresh-history',
                'completed-receipt-and-effective-attestation',
            ],
            'instrument.execute' => ['write-register'],
        ],
    ],
    7 => [
        'name' => 'order',
        'description' => 'Commit a grounded Write with aligned Depth/Cadence, instrument or attest. Read keeps Order open; productive moves complete the Cycle.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'receipt.read',
            'proposition.commit',
            'instrument.execute',
            'receipt.replay',
            'attestation.create',
        ],
        'constraints' => [
            'proposition.commit' => [
                'effective-gate-attestation',
                'completed-gate-or-effective-attestation-receipt',
                'counted-participant-reinforcement',
                'depth-cadence-and-hysteresis-admissible',
            ],
        ],
    ],
];
