<?php

namespace Tests\Support;

use App\Models\Receipt;

/** Executes an authored transcript. Never chooses an action from runtime eligibility. */
abstract class ScriptedParticipationTestCase extends ParticipationHttpTestCase
{
    private array $scriptIds = [];

    private array $script = [];

    private array $scriptParticipants = [];

    private array $scriptStates = [];

    private array $scriptResponses = [];

    private int $scriptCursor = 0;

    protected function replayParticipationScript(string $file, ?int $count = null): array
    {
        $this->script = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR)['steps'];
        $this->script = ParticipationScript::creatorLoads(ParticipationScript::expand($this->script));
        $this->startSubstrate();

        return $this->continueParticipationScript($count ?? count($this->script));
    }

    protected function continueParticipationScript(int $count): array
    {
        $participants = &$this->scriptParticipants;
        $states = &$this->scriptStates;
        for ($offset = 0; $offset < $count; $offset++) {
            $index = $this->scriptCursor++;
            $this->assertArrayHasKey($index, $this->script, 'Script has no next step');
            $step = $this->script[$index];
            $label = 'Script step '.$index.' ('.$step['participant'].' / '.$step['operation'].')';
            $name = $step['participant'];
            if ($step['operation'] === 'enter') {
                $this->assertArrayNotHasKey($name, $participants, $label);
                $participants[$name] = $this->joinParticipant($name);
                $actual = $this->enterParticipant($participants[$name], $step['origin']);
            } else {
                $this->assertSame('contribute', $step['operation'], $label);
                $before = $this->readParticipation($participants[$name]);
                $expectedBefore = isset($step['before']['previous_after'])
                    ? $this->script[$step['before']['previous_after']]['after'] : $step['before'];
                $this->bindScriptIds($step['bind_before'] ?? [], $before, $label.' before', $expectedBefore);
                $this->assertScriptState($expectedBefore, $before, $label.' before');
                $actual = $this->contribute($participants[$name], $this->resolveScriptReferences($step['input']), $before);
            }
            $this->bindScriptIds($step['bind'], $actual, $label, $step['after']);
            $this->assertScriptState($step['after'], $actual, $label.' after');
            $states[$name] = $actual;
            $this->scriptResponses[$index] = $actual;
        }

        return ['participants' => $participants, 'states' => $states, 'responses' => $this->scriptResponses];
    }

    protected function nextScriptInput(): array
    {
        $this->assertArrayHasKey($this->scriptCursor, $this->script);
        $step = $this->script[$this->scriptCursor];
        $this->assertSame('contribute', $step['operation']);

        return $this->resolveScriptReferences($step['input']);
    }

    protected function assertParticipationScriptComplete(): void
    {
        $this->assertSame(count($this->script), $this->scriptCursor, 'Every predefined action must execute');
    }

    private function bindScriptIds(array $bindings, array $actual, string $label, array $expected): void
    {
        // New identities are bound only at declared response paths. Reusing an old
        // identity where this script expects a new entity fails immediately.
        $projection = ParticipationScript::state($actual);
        foreach ($bindings as $alias => $path) {
            // Archived transcripts bind new observations at their old catalogue index.
            // Stage A moves historical/attached identity to proposition references.
            // Resolve only that declared subject/type, never discover an action.
            if (array_slice($path, 0, 2) === ['plan', 'receipts']) {
                $old = $expected['plan']['receipts'][$path[2]];
                $retained = $old['status'] === 'loaded'
                    || ($old['availability'] === 'available' && empty($old['attached_proposition_id']));
                if ($retained) {
                    $prefix = $expected;
                    $prefix['plan']['receipts'] = array_slice($expected['plan']['receipts'], 0, $path[2]);
                    $path[2] = count(ParticipationScript::stageA($prefix)['plan']['receipts']);
                } else {
                    $subject = $this->resolveScriptReferences($old['attached_proposition_id'] ?? $old['proposition_id']);
                    $references = collect(['lineage', 'read_options', 'gate_options', 'write_options'])
                        ->flatMap(fn ($population) => $actual['plan'][$population] ?? [])
                        ->where('id', $subject)->flatMap(fn ($node) => $node['receipts'] ?? [])
                        ->where('type', $old['type'])->unique('id');
                    $references = end($path) === 'id'
                        ? $references->whereNotIn('id', array_values($this->scriptIds))->values()
                        : $references->where('id', $this->resolveScriptReferences($old['id']))->values();
                    $this->assertCount(1, $references, $label.' declared Receipt reference '.$alias);
                    // Origin Horizon identity is ledger provenance, no longer prompt Receipt metadata.
                    $projection['stage_a_reference'] = end($path) === 'context_horizon_id'
                        ? Receipt::findOrFail($references->first()['id'])->context_horizon_id
                        : $references->first()[end($path)];
                    $path = ['stage_a_reference'];
                }
            }
            $id = $projection;
            foreach ($path as $key) {
                $this->assertIsArray($id, $label.' binding '.$alias);
                $this->assertArrayHasKey($key, $id, $label.' binding '.$alias);
                $id = $id[$key];
            }
            $this->assertIsString($id, $label);
            $this->assertMatchesRegularExpression('/^[0-9a-hjkmnp-tv-z]{26}$/i', $id, $label);
            $this->assertArrayNotHasKey($alias, $this->scriptIds, $label);
            $this->assertNotContains($id, $this->scriptIds, $label.' expected a fresh identity');
            $this->scriptIds[$alias] = $id;
        }
    }

    private function assertScriptState(array $expected, array $actual, string $label): void
    {
        $this->assertSame(
            ParticipationScript::canonicalizeParents($this->resolveScriptReferences(ParticipationScript::stageA($expected))),
            ParticipationScript::canonicalizeParents(ParticipationScript::state($actual)), $label);
    }

    private function resolveScriptReferences(mixed $value): mixed
    {
        if (is_array($value) && array_keys($value) === ['$ref']) {
            $this->assertArrayHasKey($value['$ref'], $this->scriptIds, 'Unknown scripted identity');

            return $this->scriptIds[$value['$ref']];
        }
        if (is_array($value)) {
            return array_map(fn ($child) => $this->resolveScriptReferences($child), $value);
        }

        return $value;
    }
}
