import { createTheme, DEFAULT_THEME, rem } from '@mantine/core';

export const theme = createTheme({
  primaryColor: 'primary',
  colors: { primary: DEFAULT_THEME.colors.indigo, attestationPositive: DEFAULT_THEME.colors.violet, attestationNegative: DEFAULT_THEME.colors.blue },
  primaryShade: { light: 6, dark: 4 },
  defaultRadius: 'md',
  fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
  headings: { fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif', fontWeight: '650' },
  components: {
    Button: { defaultProps: { radius: 'md' } },
    Card: { defaultProps: { radius: 'lg' } },
    TextInput: { defaultProps: { radius: 'md' } },
    PasswordInput: { defaultProps: { radius: 'md' } },
    Textarea: { defaultProps: { radius: 'md' } },
  },
  spacing: { xl: rem(32) },
});
