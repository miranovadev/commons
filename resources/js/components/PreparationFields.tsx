import { Button, Group, NumberInput, Select, Stack, Text } from '@mantine/core';
import type { PreparationOption, PreparationSelection } from '../types';

export function PreparationFields({ options, value, onChange }: { options: PreparationOption[]; value: PreparationSelection[]; onChange: (items: PreparationSelection[]) => void }) {
  function update(index: number, patch: Partial<PreparationSelection>) { onChange(value.map((item, i) => i === index ? { ...item, ...patch } : item)); }
  return <Stack gap="sm">
    <Text size="sm">Choose up to eight Artifact representations. Source Receipts may already be consumed.</Text>
    {value.map((selection, index) => {
      const option = options.find(item => item.receipt_id === selection.receipt_id && item.artifact_id === selection.artifact_id);
      const capability = option?.capabilities.find(item => item.read_type === selection.read_type);
      return <Stack key={index} gap="xs">
        <Select label={`Artifact ${index + 1}`} required searchable value={`${selection.receipt_id}:${selection.artifact_id}`}
          data={options.map(item => ({ value: `${item.receipt_id}:${item.artifact_id}`, label: `${item.reference} · ${item.source_type} · ${item.receipt_id}` }))}
          onChange={key => { const chosen = options.find(item => `${item.receipt_id}:${item.artifact_id}` === key); if (chosen) update(index, { receipt_id: chosen.receipt_id, artifact_id: chosen.artifact_id, read_type: 'text', parameters: { offset: 0, max_chars: chosen.capabilities.find(c => c.read_type === 'text')?.parameters.max_chars ?? 4000 } }); }} />
        <Select label="Read Type" required value={selection.read_type} data={option?.capabilities.map(item => item.read_type) ?? []}
          onChange={type => { if (type) update(index, { read_type: type, parameters: type === 'text' ? { offset: 0, max_chars: option?.capabilities.find(c => c.read_type === 'text')?.parameters.max_chars ?? 4000 } : undefined }); }} />
        {selection.read_type === 'text' && <Group grow>
          <NumberInput label="Character offset" min={0} value={selection.parameters?.offset ?? 0} allowDecimal={false}
            onChange={offset => update(index, { parameters: { ...selection.parameters, offset: Number(offset), max_chars: selection.parameters?.max_chars ?? 4000 } })} />
          <NumberInput label="Maximum characters" min={1} max={capability?.max_chars ?? 8000} allowDecimal={false} value={selection.parameters?.max_chars ?? 4000}
            onChange={max => update(index, { parameters: { ...selection.parameters, max_chars: Number(max) } })} />
        </Group>}
        <Button variant="subtle" color="gray" size="compact-xs" onClick={() => onChange(value.filter((_, i) => i !== index))}>Remove selection</Button>
      </Stack>;
    })}
    <Button variant="light" disabled={value.length >= 8 || options.length === 0} onClick={() => {
      const option = options[0];
      onChange([...value, { receipt_id: option.receipt_id, artifact_id: option.artifact_id, read_type: 'text', parameters: { offset: 0, max_chars: option.capabilities.find(c => c.read_type === 'text')?.parameters.max_chars ?? 4000 } }]);
    }}>Add Artifact</Button>
  </Stack>;
}
