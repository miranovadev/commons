<?php

namespace App\Services;

use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use DomainException;

/** C1's approved attention questions; action descriptions remain separate. */
final class ParticipantRegisterQuestions
{
    private const QUESTIONS = [
        'activity' => 'What is active here?',
        'frontier' => 'What else is possible here?',
        'canon' => 'What remains?',
        'signal' => 'What is different here?',
        'stance' => 'What position can be taken here?',
        'concord' => 'What can be held together?',
    ];

    public function forPhase(PhaseKind $phase, string $writeRegister): string
    {
        if (! in_array($writeRegister, array_map(fn (ContextDomain $domain) => $domain->registerFor($phase), ContextDomain::cases()), true)) {
            throw new DomainException('The Write register does not match its producing Phase.');
        }

        return $phase === PhaseKind::Order ? 'What should persist?' : self::QUESTIONS[$writeRegister];
    }
}
