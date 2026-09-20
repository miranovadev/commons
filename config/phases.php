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
        'description' => 'Develop the selected distinction structurally, or support its existing viable Write.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'receipt.read',
            'proposition.variant',
            'proposition.revise',
            'instrument.execute',
            'receipt.replay',
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
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
        'description' => 'Open a structurally needed expressive opportunity, generate shared evidence, attest, or traverse an already-attested Write.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'receipt.read',
            'proposition.variant',
            'proposition.revise',
            'instrument.execute',
            'receipt.replay',
            'attestation.create',
            'proposition.traverse',
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
        'description' => 'Classify the current causal Stance/Frontier Gate when its retained direction agrees with its own ancestry, or act on its causal Write continuations. Regional Bias calculation candidates are not operational targets.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'receipt.read',
            'proposition.reclassify',
            'instrument.execute',
            'receipt.replay',
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
            'proposition.reclassify' => [
                'lineage-direction-agreement',
                'unary-selection-fresh-history',
                'completed-receipt-and-effective-attestation',
            ],
            'instrument.execute' => ['write-register'],
        ],
    ],
    7 => [
        'name' => 'order',
        'description' => 'Inspect local expressive Writes, Commit an admissible Write, or Traverse its own direct Committal continuation. Commit or Committal Traverse completes the Cycle.',
        'gate' => 'previous-phase-position',
        'actions' => ['proposition.commit', 'proposition.traverse'],
        'constraints' => [
            'proposition.commit' => ['effective-gate-attestation', 'completed-gate-or-effective-attestation-receipt', 'depth-cadence-and-hysteresis-admissible'],
            'proposition.traverse' => ['direct-committal-of-causal-write'],
        ],
    ],
];
