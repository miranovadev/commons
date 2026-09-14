import { Box, ColorSwatch, Group, Select, Stack, Text, TextInput } from '@mantine/core';
import { paletteColors, type PaletteColor, type SubstratePresentation } from '../substratePresentation';

export function SubstratePresentationFields({ value, onChange }: {
  value: SubstratePresentation; onChange: (value: SubstratePresentation) => void;
}) {
  const data = paletteColors.map(color => ({ value: color, label: color[0].toUpperCase() + color.slice(1) }));
  function colorSelect(label: string, color: PaletteColor, change: (color: PaletteColor) => void) {
    return <Select label={label} data={data} value={color} allowDeselect={false}
      leftSection={<ColorSwatch size={16} color={`var(--mantine-color-${color}-6)`} />}
      renderOption={({ option }) => <Group gap="xs"><ColorSwatch size={16} color={`var(--mantine-color-${option.value}-6)`} />{option.label}</Group>}
      onChange={next => { if (next) change(next as PaletteColor); }} />;
  }
  return <Stack gap="sm">
    {colorSelect('Primary colour', value.primary_color, primary_color => onChange({ ...value, primary_color }))}
    <Group gap="xs" aria-label="Domain colour preview">
      <Box px="sm" py={5} style={{ borderRadius: 6, background: `var(--mantine-color-${value.primary_color}-0)`, color: `var(--mantine-color-${value.primary_color}-9)` }}>Emergence</Box>
      <Box px="sm" py={5} style={{ borderRadius: 6, background: `var(--mantine-color-${value.primary_color}-9)`, color: 'var(--mantine-color-gray-1)' }}>Settlement</Box>
    </Group>
    {(['positive', 'negative'] as const).map(direction => <Stack gap="xs" key={direction}>
      <Text size="sm" fw={600}>{direction === 'positive' ? 'Positive' : 'Negative'} Attestation</Text>
      <TextInput label={`${direction === 'positive' ? 'Positive' : 'Negative'} label`} required maxLength={40}
        value={value[direction].label} onChange={event => onChange({ ...value, [direction]: { ...value[direction], label: event.currentTarget.value } })} />
      {colorSelect(`${direction === 'positive' ? 'Positive' : 'Negative'} colour`, value[direction].color,
        color => onChange({ ...value, [direction]: { ...value[direction], color } }))}
    </Stack>)}
  </Stack>;
}
