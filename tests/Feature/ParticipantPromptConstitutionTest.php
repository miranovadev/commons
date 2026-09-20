<?php

namespace Tests\Feature;

use App\Enums\PhaseKind;
use App\Models\Connection;
use App\Services\InputSchemaService;
use App\Services\ParticipantPromptFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ParticipantPromptConstitutionTest extends TestCase
{
    public static function semanticRules(): array
    {
        return [
            'movement requires causal edges' => [[
                'Forward participant movement follows explicit causal continuations from the current Gate; recovery follows recorded causal ancestry backward.',
                'Commit Region or Context Horizon membership, phase/register compatibility, Homeostatic calculation membership, historical visibility and prior Commitment never create a movement edge.',
                'No edge means no movement.',
                'An authorized contribution may create a forward edge; Traverse follows an existing edge.',
            ], [
                'selectable Propositions still obey phase and Commit Region rules.',
            ]],
            'Mediate preserves reconciliation across lineages' => [[
                'Mediate may compare the current Read anchor against compatible Reads elsewhere in the same Commit Region under these Correspondence rules.',
                'Only schema-authorized other_proposition_id values are reconciliation counterparts, including compatible foreign-lineage Disperse Gates.',
                'They are reconciliation operands, never Traverse targets or movement destinations.',
                'Reconcile(current Gate, counterpart) copies the current Gate statement exactly and creates a new causal result with explicit hard parent edges from both inputs.',
            ], []],
            'Bias separates calculation from operational options' => [[
                'Bias evaluates its regional Homeostatic Gate population internally, but the participant-facing operational Gate is the current causal Gate.',
                'Other regional Stance/Frontier Propositions are calculation inputs only, never participant-facing gate_options.',
                'Bias Write and Traverse options are causal continuations of the current Gate.',
            ], [
                'Bias also exposes existing Writes across its Commit Region.',
                'Bias gate_options contains active Gate-equivalent Stance/Frontier Propositions in the same Commit Region',
            ]],
            'successful routes preserve their existing edges' => [[
                'The frozen classification remains traversable only from the exact Gate whose existing causal continuation produced it, subject to ordinary traversal prerequisites.',
                'Successful-route continuity preserves an edge; it does not create one.',
                'its frozen Bias route remains traversable only through its existing causal continuation from the exact originating Gate.',
            ], [
                'Bias can traverse its existing classification when it leads to an operational Commit; Order then traverses that Commit.',
                'Bias Traverse selects a personally attested mutable Write, or a frozen classification with an operational Commit, and advances to Order.',
                'while its frozen Bias route remains traversable.',
            ]],
            'Closure evaluates each local Write without hiding it' => [[
                'Order exposes the causally local Bias-produced expressive Writes associated with that selected/current Gate, including legitimate same-position revisions, regardless of Closure admissibility.',
                "Closure, performed during Order — Write, evaluates each causally local Bias-produced expressive Write independently using that Write's own Depth, Cadence, lineage/history and participation/evidence state.",
                'Closure is not a region-wide cross-lineage comparison.',
                'Closure inadmissibility removes Commit availability only; the local Write remains visible.',
                'proposition.commit is available only where that Write satisfies the full Commitment prerequisites',
                'choose a schema-authorized local Bias-produced expressive Write',
            ], [
                'Gate contains admissible uncommitted Concord/Canon, and Write contains existing Committal Concord/Canon.',
                'choose an authorized expressive Gate;',
                'Closure is a region-wide cross-lineage comparison.',
            ]],
            'Order stays terminal with only direct Committal Traverse' => [[
                'Order remains terminal: only schema-authorized Commit or direct Committal Traverse is available, or exclusive Rest when neither remains.',
                'Order introduces no new Attestation, Instrument, Revision or other mutation actions.',
                'All grounding and attestation happen before Order.',
                'If a local expressive Write already has its own direct Committal child, only that exact Committal may expose proposition.traverse.',
                "Another Write's Committal is never a Traverse target.",
            ], [
                'Order offers only Commit on an admissible Gate or Traverse on a Committal Write.',
            ]],
            'schema remains authoritative' => [[
                'The supplied action schema is authoritative for available actions and targets.',
                'These instructions explain the current state; they do not authorize additional actions or targets.',
                'Your response is constrained by the supplied JSON Schema.',
            ], []],
        ];
    }

    #[DataProvider('semanticRules')]
    public function test_generated_constitution_preserves_authoritative_semantics(array $required, array $stale): void
    {
        $prompt = app(ParticipantPromptFactory::class)->make(new Connection, [
            'status' => 'ready',
            'plan' => [
                'phase' => ['id' => 'order', 'number' => 7, 'kind' => 'order'],
                'schema' => app(InputSchemaService::class)->forPhase(PhaseKind::Order),
            ],
        ]);

        foreach ($required as $meaning) {
            $this->assertStringContainsString($meaning, $prompt['system']);
        }
        foreach ($stale as $meaning) {
            $this->assertStringNotContainsString($meaning, $prompt['system']);
        }
    }
}
