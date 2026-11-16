<?php

namespace App\Services;

use App\Enums\ContextDomain;
use App\Enums\HorizonCloudMode;
use App\Enums\PhaseKind;
use DomainException;

/**
 * C1 Write attention questions, extended by an independent Horizon-cloud axis.
 *
 * Phase identity is never inferred from a repeated Write-register label.
 * Neither this vocabulary nor a cloud transition changes action eligibility.
 */
final class ParticipantRegisterQuestions
{
    // Each Domain retains its vocabulary, each Phase its operational position,
    // and the cloud changes the perspective rather than the available actions.
    private const QUESTIONS = [
        'emergence' => [
            1 => ['dwell' => 'Which activity warrants attention here?', 'journey' => 'What activity has not yet been distinguished?'],
            2 => ['dwell' => 'Which possibilities are supported here?', 'journey' => 'What other possibilities could emerge here?'],
            3 => ['dwell' => 'What remains after examination?', 'journey' => 'What remains unresolved or unexplored?'],
            4 => ['dwell' => 'Which activity has developed meaningfully?', 'journey' => 'Where else could this activity lead?'],
            5 => ['dwell' => 'Which possibilities can be related?', 'journey' => 'What relationship has not yet been considered?'],
            6 => ['dwell' => 'What interpretation is supported here?', 'journey' => 'What alternative interpretation remains possible?'],
        ],
        'settlement' => [
            1 => ['dwell' => 'Which differences are consequential?', 'journey' => 'What difference remains undiscovered?'],
            2 => ['dwell' => 'Which position is warranted?', 'journey' => 'What alternative position is possible?'],
            3 => ['dwell' => 'What withstands examination?', 'journey' => 'What fails to hold together?'],
            4 => ['dwell' => 'What distinguishes these continuations?', 'journey' => 'Where else might these differences lead?'],
            5 => ['dwell' => 'Which relationships can be reconciled?', 'journey' => 'What relationship has not been considered?'],
            6 => ['dwell' => 'What interpretation is supported?', 'journey' => 'What alternative interpretation could hold?'],
        ],
    ];

    // The current C1 questions remain available as a control for comparisons.
    private const LEGACY = [
        'activity' => 'What is active here?',
        'frontier' => 'What else is possible here?',
        'canon' => 'What remains?',
        'signal' => 'What is different here?',
        'stance' => 'What position can be taken here?',
        'concord' => 'What can be held together?',
    ];

    public function forPhase(
        PhaseKind $phase,
        string $writeRegister,
        ?HorizonCloudMode $cloud = null,
    ): string {
        $domain = collect(ContextDomain::cases())
            ->first(fn (ContextDomain $candidate) => $candidate->registerFor($phase) === $writeRegister);

        if ($domain === null) {
            throw new DomainException('The Write register does not match its producing Phase.');
        }

        if ($phase === PhaseKind::Order) {
            return 'What should persist?';
        }

        // Until an actual, historically valid Horizon-cloud observation exists,
        // never infer its direction from a register or silently invent a mode.
        if ($cloud === null) {
            return self::LEGACY[$writeRegister];
        }

        return self::QUESTIONS[$domain->value][$phase->value][$cloud->value];
    }
}
