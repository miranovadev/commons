<?php

return [
    1 => [
        'name' => 'distinguish',
        'description' => 'Create a distinction or traverse an existing local Expressive Proposition from the current Committal Gate.',
        'gate' => 'participant-position',
        'actions' => [
            'proposition.variant',
            'instrument.execute',
            'attestation.create',
            'proposition.traverse',
        ],
    ],
    2 => [
        'name' => 'persist',
        'description' => 'Develop the selected distinction structurally, or support its existing viable Write.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'proposition.variant',
            'proposition.revise',
            'instrument.execute',
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
            'proposition.revise',
            'proposition.reclassify',
            'instrument.execute',
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
            'proposition.variant',
            'proposition.revise',
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
        'description' => 'Keep the causal Read and ordinary local Reconcile path. Dominant Correspondence across homologous Reads may add immediate Gate counterparts from other Regions or Horizons. Reconcile requires coherent retained Correspondence component directions on both existing Gates and matching resolved Correspondence directions between them. The causal Gate remains visible when inadmissible. Attest an immediate Write descendant when offered.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'proposition.reconcile',
            'instrument.execute',
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
        'description' => 'Preserve the current causal Gate under its ordinary Bias rules. Dominant homologous Chirality may add Reclassify sources; use only the Cycle-entry Receipt catalogue. Existing causal Writes retain their ordinary actions and Traverse rules.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'proposition.reclassify',
            'instrument.execute',
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
            'proposition.reclassify' => [
                'local-lineage-or-dominant-homologous-chirality',
                'unary-selection-fresh-history',
                'completed-receipt-and-effective-attestation',
            ],
            'instrument.execute' => ['write-register'],
        ],
    ],
    7 => [
        'name' => 'order',
        'description' => 'Commit an admissible local Write, Traverse its direct Committal continuation, or Traverse a dominant-direction homologous Committal. The selected Committal becomes the next Cycle entry.',
        'gate' => 'previous-phase-position',
        'actions' => ['proposition.commit', 'proposition.traverse'],
        'constraints' => [
            'proposition.commit' => ['effective-gate-attestation', 'completed-gate-or-effective-attestation-receipt', 'depth-cadence-and-hysteresis-admissible'],
            'proposition.traverse' => ['direct-committal-of-causal-write-or-dominant-homologous-committal'],
        ],
    ],
];
