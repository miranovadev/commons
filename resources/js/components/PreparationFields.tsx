import { Button, Group, NumberInput, Select, Stack, Text } from '@mantine/core';
import type { PreparationOption, PreparationSelection } from '../types';

export function PreparationFields({ options, value, onChange }: { options: PreparationOption[]; value: PreparationSelection[]; onChange: (items: PreparationSelection[]) => void }) {
  function update(index: number, patch: Partial<PreparationSelection>) { onChange(value.map((item, i) => i === index ? { ...item, ...patch } : item)); }
  return <Stack gap="sm">
    <Text size="sm">Choose up to eight Artifact representations. Source Receipts may already be consumed. Reading lengths are allocated automatically unless you set a preferred maximum. Oversized requests share the safe reading budget.</Text>
    {value.map((selection, index) => {
      const option = options.find(item => item.receipt_id === selection.receipt_id && item.artifact_id === selection.artifact_id);
      return <Stack key={index} gap="xs">
        <Select label={`Artifact ${index + 1}`} required searchable value={`${selection.receipt_id}:${selection.artifact_id}`}
          data={options.map(item => ({ value: `${item.receipt_id}:${item.artifact_id}`, label: `${item.reference} · ${item.source_type} · ${item.receipt_id}` }))}
          onChange={key => { const chosen = options.find(item => `${item.receipt_id}:${item.artifact_id}` === key); if (chosen) update(index, { receipt_id: chosen.receipt_id, artifact_id: chosen.artifact_id, read_type: chosen.capabilities.find(c => c.read_type === 'text')?.read_type ?? chosen.capabilities[0].read_type, parameters: { offset: 0 } }); }} />
        <Select label="Read Type" required value={selection.read_type} data={option?.capabilities.map(item => item.read_type) ?? []}
          onChange={type => { if (type) update(index, { read_type: type, parameters: { offset: 0 } }); }} />
        <Group grow>
          <NumberInput label="Character offset" min={0} value={selection.parameters?.offset ?? 0} allowDecimal={false}
            onChange={offset => update(index, { parameters: { ...selection.parameters, offset: Number(offset) } })} />
          <NumberInput label="Maximum characters (optional)" placeholder="Automatic" min={1} max={Number.MAX_SAFE_INTEGER} allowDecimal={false} value={selection.parameters?.max_chars ?? ''}
            onChange={max => { const parameters = { ...selection.parameters }; if (max === '') delete parameters.max_chars; else parameters.max_chars = Number(max); update(index, { parameters }); }} />
        </Group>
        <Button variant="subtle" color="gray" size="compact-xs" onClick={() => onChange(value.filter((_, i) => i !== index))}>Remove selection</Button>
      </Stack>;
    })}
    <Button variant="light" disabled={value.length >= 8 || options.length === 0} onClick={() => {
      const option = options[0];
      onChange([...value, { receipt_id: option.receipt_id, artifact_id: option.artifact_id, read_type: option.capabilities.find(c => c.read_type === 'text')?.read_type ?? option.capabilities[0].read_type, parameters: { offset: 0 } }]);
    }}>Add Artifact</Button>
  </Stack>;
}
