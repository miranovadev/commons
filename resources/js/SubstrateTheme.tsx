import { createContext, useContext, useMemo, type ReactNode } from 'react';
import { DEFAULT_THEME, MantineProvider } from '@mantine/core';
import { theme } from './theme';
import { defaultPresentation, type SubstratePresentation } from './substratePresentation';

const PresentationContext = createContext(defaultPresentation);
export const useSubstratePresentation = () => useContext(PresentationContext);

export function substrateTheme(presentation: SubstratePresentation) {
  return { ...theme, primaryColor: 'primary', colors: {
    ...theme.colors,
    primary: DEFAULT_THEME.colors[presentation.primary_color],
    attestationPositive: DEFAULT_THEME.colors[presentation.positive.color],
    attestationNegative: DEFAULT_THEME.colors[presentation.negative.color],
  } };
}

export function SubstrateTheme({ presentation = defaultPresentation, children }: {
  presentation?: SubstratePresentation; children: ReactNode;
}) {
  const selectedTheme = useMemo(() => substrateTheme(presentation), [presentation]);
  return <PresentationContext.Provider value={presentation}>
    <MantineProvider theme={selectedTheme} defaultColorScheme="auto">{children}</MantineProvider>
  </PresentationContext.Provider>;
}
