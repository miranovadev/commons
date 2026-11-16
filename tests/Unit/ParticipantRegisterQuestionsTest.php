<?php

namespace Tests\Unit;

use App\Enums\ContextDomain;
use App\Enums\HorizonCloudMode;
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
    public function test_unobserved_cloud_preserves_approved_question(PhaseKind $phase, string $register, string $question): void
    {
        $this->assertSame($question, (new ParticipantRegisterQuestions)->forPhase($phase, $register));
    }

    public function test_every_domain_phase_and_cloud_combination_has_its_own_question(): void
    {
        $questions = new ParticipantRegisterQuestions;
        $nonOrder = [];
        foreach (ContextDomain::cases() as $domain) {
            foreach (PhaseKind::cases() as $phase) {
                foreach (HorizonCloudMode::cases() as $cloud) {
                    $question = $questions->forPhase($phase, $domain->registerFor($phase), $cloud);
                    $this->assertTrue(str_starts_with($question, 'What') || str_starts_with($question, 'Which') || str_starts_with($question, 'Where'));
                    $this->assertStringEndsWith('?', $question);
                    if ($phase === PhaseKind::Order) {
                        $this->assertSame('What should persist?', $question);
                    } else {
                        $nonOrder[] = $question;
                    }
                }
            }
        }
        $this->assertCount(24, array_unique($nonOrder));
    }

    public function test_cloud_changes_questions_without_changing_write_register(): void
    {
        $questions = new ParticipantRegisterQuestions;
        foreach (ContextDomain::cases() as $domain) {
            foreach (PhaseKind::cases() as $phase) {
                if ($phase === PhaseKind::Order) {
                    continue;
                }
                $register = $domain->registerFor($phase);
                $this->assertNotSame(
                    $questions->forPhase($phase, $register, HorizonCloudMode::Dwell),
                    $questions->forPhase($phase, $register, HorizonCloudMode::Journey),
                );
            }
        }
    }

    public function test_register_labels_cannot_invent_a_phase(): void
    {
        $this->expectException(DomainException::class);
        (new ParticipantRegisterQuestions)->forPhase(PhaseKind::Persist, 'canon', HorizonCloudMode::Journey);
    }
}
