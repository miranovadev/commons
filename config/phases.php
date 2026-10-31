<?php

return [
    1 => [
        'name' => 'distinguish',
        'description' => 'Explore existing Distinctions first. When a fresh Variant is lawful, Instrument and choose Variant or Defer.',
        'gate' => 'participant-position',
        'actions' => [
            'instrument.execute',
            'proposition.variant',
            'attestation.create',
            'proposition.traverse',
        ],
    ],
    2 => [
        'name' => 'persist',
        'description' => 'Explore existing persistence first. For a fresh Variant, Prepare a readable Artifact or Instrument when none is readable, then Variant or Defer.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'instrument.execute',
            'preparation.start',
            'proposition.variant',
            'proposition.revise',
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
        ],
    ],
    3 => [
        'name' => 'constrain',
        'description' => 'Strengthen or classify the selected Proposition. Grounding is acquired during Persist.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'proposition.revise',
            'proposition.reclassify',
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
        'description' => 'Explore existing structure first. For a fresh Variant choose Instrument or Preparation, then Variant or Defer. Revision remains independent.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'preparation.start',
            'instrument.execute',
            'proposition.variant',
            'proposition.revise',
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
        ],
    ],
    5 => [
        'name' => 'mediate',
        'description' => 'Keep the causal Read and ordinary local Reconcile path. Dominant Correspondence across homologous Reads may add immediate Gate counterparts from other Regions or Horizons. Reconcile requires coherent retained Correspondence component directions on both existing Gates and matching resolved Correspondence directions between them. The causal Gate remains visible when inadmissible. Attest an immediate Write descendant when offered.',
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
        'description' => 'Preserve the current causal Gate under its ordinary Bias rules. Dominant homologous Chirality may add Reclassify sources; use only the Cycle-entry Receipt catalogue. Existing causal Writes retain their ordinary actions and Traverse rules.',
        'gate' => 'previous-phase-position',
        'actions' => [
            'proposition.reclassify',
            'attestation.create',
            'proposition.traverse',
        ],
        'constraints' => [
            'proposition.reclassify' => [
                'local-lineage-or-dominant-homologous-chirality',
                'unary-selection-fresh-history',
                'completed-receipt-and-effective-attestation',
            ],
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
