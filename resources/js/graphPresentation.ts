import { createContext } from 'react';

/** Portals and the graph rail share the active lens; other workspaces stay Indigo. */
export const GraphTensionPalette = createContext<'default' | 'analytical'>('default');
