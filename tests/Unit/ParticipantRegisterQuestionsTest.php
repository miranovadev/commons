<?php

namespace Tests\Unit;

use App\Enums\ContextDomain;
use App\Enums\PhaseKind;
use App\Services\ParticipantRegisterQuestions;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParticipantRegisterQuestionsTest extends TestCase
{
    public static function questions(): array
    {
        $emergence = ['What is active here?', 'What else is possible here?', 'What remains?',
            'What is active here?', 'What else is possible here?', 'What remains?', 'What should persist?'];
        $settlement = ['What is different here?', 'What position can be taken here?', 'What can be held together?',
            'What is different here?', 'What position can be taken here?', 'What can be held together?', 'What should persist?'];
        $cases = [];
        foreach (PhaseKind::cases() as $phase) {
            foreach (ContextDomain::cases() as $domain) {
                $cases[$domain->value.'-'.$phase->name] = [$phase, $domain->registerFor($phase),
                    ($domain === ContextDomain::Emergence ? $emergence : $settlement)[$phase->value - 1]];
            }
        }

        return $cases;
    }

    #[DataProvider('questions')]
    public function test_exact_approved_question_uses_producing_phase_and_write_register(PhaseKind $phase, string $register, string $question): void
    {
        $this->assertSame($question, (new ParticipantRegisterQuestions)->forPhase($phase, $register));
    }

    public function test_register_labels_cannot_invent_a_phase(): void
    {
        $this->expectException(DomainException::class);
        (new ParticipantRegisterQuestions)->forPhase(PhaseKind::Persist, 'canon');
    }
}
