import type { AttestationDirection, Substrate } from './types';

export const paletteColors = ['dark', 'gray', 'red', 'pink', 'grape', 'violet', 'indigo', 'blue', 'cyan', 'teal', 'green', 'lime', 'yellow', 'orange'] as const;
export type PaletteColor = typeof paletteColors[number];
export interface SubstratePresentation {
  primary_color: PaletteColor;
  positive: { label: string; color: PaletteColor };
  negative: { label: string; color: PaletteColor };
}
export type PresentationInput = Partial<Omit<SubstratePresentation, AttestationDirection>> & {
  positive?: Partial<SubstratePresentation['positive']>;
  negative?: Partial<SubstratePresentation['negative']>;
};
export const defaultPresentation: SubstratePresentation = {
  primary_color: 'indigo',
  positive: { label: 'Support', color: 'violet' },
  negative: { label: 'Refute', color: 'blue' },
};
export function resolvePresentation(value?: PresentationInput): SubstratePresentation {
  return { ...defaultPresentation, ...value,
    positive: { ...defaultPresentation.positive, ...value?.positive },
    negative: { ...defaultPresentation.negative, ...value?.negative },
  };
}
export function presentationFor(substrate?: Substrate): SubstratePresentation {
  return resolvePresentation(substrate?.configuration?.presentation);
}
