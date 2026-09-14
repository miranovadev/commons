<?php

return [
    1 => [
        'name' => 'distinguish',
        'description' => 'Create a distinction or traverse an existing local Expressive Proposition from the current Committal Gate.',
        'gate' => 'participant-position',
        'actions' => [
            'receipt.read',
            'proposition.variant',
            'attestation.create',
            'proposition.traverse',
        ],
    ],
    2 => [
        'name' => 'persist',
        'description' => 'Variant another Participant’s Gate once, reclassify an owned Gate, or interact with eligible existing Writes.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'receipt.read',
            'proposition.variant',
            'proposition.revise',
            'proposition.reclassify',
            'instrument.execute',
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
            'proposition.variant' => ['gate-non-owner'],
            'proposition.reclassify' => ['gate-owner'],
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
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
            'proposition.reclassify' => [
                'gate-owner',
                'completed-gate-or-effective-attestation-receipt',
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
            'attestation.create',
        ],
        'constraints' => [
            'proposition.bias' => [
                'dominant-active-stance-lean',
                'unary-selection-fresh-history',
            ],
            'instrument.execute' => ['write-register'],
        ],
    ],
    7 => [
        'name' => 'order',
        'description' => 'Select a Commit Region Write candidate with aligned Depth/Cadence; commit when grounded, ground with an Instrument, or attest an eligible candidate. Reading loads context without ending the Phase; other successful contributions complete the Cycle.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'receipt.read',
            'proposition.commit',
            'instrument.execute',
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
