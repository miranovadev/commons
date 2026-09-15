export type GraphRailState = 'open' | 'collapsed' | 'locked';
export type GraphRailAction = 'select' | 'collapse' | 'lock' | 'open' | 'unlock';

export function graphRailReducer(state: GraphRailState, action: GraphRailAction): GraphRailState {
  switch (action) {
    case 'select': return state === 'locked' ? 'locked' : 'open';
    case 'collapse': return 'collapsed';
    case 'lock': return 'locked';
    case 'unlock': return 'collapsed';
    case 'open': return 'open';
  }
}
